<?php

namespace App\Http\Middleware;

use App\Models\ActivityRequest;
use App\Services\Activity\ActivityContext;
use App\Services\Activity\ActivityRecorder;
use App\Services\Activity\ActivityRequestClassifier;
use App\Services\Activity\ActorSnapshotFactory;
use App\Services\Activity\SensitiveDataSanitizer;
use App\Services\Analytics\WebsiteAnalyticsRecorder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class RecordActivityRequest
{
    public function __construct(
        private readonly ActivityContext $context,
        private readonly ActorSnapshotFactory $actors,
        private readonly ActivityRequestClassifier $classifier,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->excluded($request) || ! $this->tablesAvailable()) {
            return $next($request);
        }

        $id = (string) Str::ulid();
        $startedAt = CarbonImmutable::now('UTC');
        $startedNs = hrtime(true);
        $actorBefore = $this->actors->current($request);
        $strict = ! $request->isMethodSafe();

        try {
            ActivityRequest::query()->create(array_merge($actorBefore, [
                'id' => $id,
                'ip_address' => $this->clientIp($request),
                'method' => strtoupper($request->method()),
                'path' => '/'.ltrim($request->path(), '/'),
                'query_data' => $this->sanitizer->sanitize($request->query->all()),
                'input_data' => $this->requestInput($request),
                'user_agent' => trim((string) $request->userAgent()) ?: null,
                'referer' => trim((string) $request->headers->get('referer')) ?: null,
                'started_at' => $startedAt,
            ]));
        } catch (Throwable $exception) {
            Log::error('Activity request could not be initialized', ['exception' => $exception]);
            if ($strict) {
                abort(503, 'Pencatatan aktivitas sedang tidak tersedia. Perubahan data dibatalkan.');
            }

            return $next($request);
        }

        $this->context->activate($id);
        $startingTransactionLevel = DB::transactionLevel();

        try {
            if ($strict) {
                DB::beginTransaction();
            }

            $response = $next($request);
            if ($strict && $this->context->hasAuditFailure()) {
                throw new RuntimeException('Detail audit mutasi gagal disimpan.');
            }
            $validationFailed = $this->hasValidationErrors($request, $response);
            if ($validationFailed) {
                $request->attributes->set('activity.validation_failed', true);
                app(ActivityRecorder::class)->record(
                    'validation',
                    'request.validation_failed',
                    description: 'Input request gagal divalidasi.',
                    metadata: ['http_status' => $response->getStatusCode()],
                );
            } elseif ($response->getStatusCode() >= 400) {
                app(ActivityRecorder::class)->record(
                    $response->getStatusCode() === 403 ? 'security' : 'request',
                    $this->responseAction($response->getStatusCode()),
                    description: 'Request selesai dengan status HTTP '.$response->getStatusCode().'.',
                    metadata: ['http_status' => $response->getStatusCode()],
                );
            } elseif (($redirectFailure = $this->redirectFailureAction($request, $response)) !== null) {
                $request->attributes->set(
                    'activity.forced_outcome',
                    in_array($redirectFailure, ['request.access_denied', 'request.authentication_required'], true) ? 'denied' : 'failed',
                );
                app(ActivityRecorder::class)->record(
                    str_contains($redirectFailure, 'denied') || str_contains($redirectFailure, 'authentication') ? 'security' : 'request',
                    $redirectFailure,
                    description: 'Request dialihkan karena tidak dapat diselesaikan.',
                    metadata: ['redirect_to' => (string) $response->headers->get('Location')],
                );
            }
            if ($strict && $response->getStatusCode() >= 500) {
                throw new RuntimeException('Request mutasi gagal sebelum transaksi dapat diselesaikan.');
            }
            $actorAfter = $this->actors->current($request);
            $actor = $this->actors->preferAuthenticated($actorBefore, $actorAfter);
            $this->finalize($id, $request, $response, $actor, $startedNs);

            try {
                app(WebsiteAnalyticsRecorder::class)->record($id, $request, $response);
            } catch (Throwable $analyticsException) {
                Log::warning('Website analytics could not be recorded', [
                    'activity_request_id' => $id,
                    'exception' => $analyticsException,
                ]);
            }

            if ($strict) {
                DB::commit();
            }

            $this->context->runCommitCallbacks();

            $response->headers->set('X-Activity-Request-Id', $id);
            $this->context->clear();

            return $response;
        } catch (Throwable $exception) {
            while (DB::transactionLevel() > $startingTransactionLevel) {
                DB::rollBack();
            }
            $this->context->runRollbackCallbacks();

            if (($actorBefore['actor_type'] ?? 'anonymous') === 'anonymous' && Auth::check()) {
                Auth::logout();
            }

            try {
                app(ActivityRecorder::class)->record(
                    $exception instanceof ValidationException ? 'validation' : 'security',
                    $this->exceptionAction($exception),
                    description: $this->safeErrorMessage($exception),
                    metadata: ['exception' => $exception::class],
                );
                $actorAfter = $this->actors->current($request);
                $actor = $this->actors->preferAuthenticated($actorBefore, $actorAfter);
                $this->finalize($id, $request, null, $actor, $startedNs, $exception);
            } catch (Throwable $auditException) {
                Log::error('Activity request failure could not be finalized', [
                    'activity_request_id' => $id,
                    'exception' => $auditException,
                ]);
            }

            $this->context->clear();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    private function finalize(
        string $id,
        Request $request,
        ?Response $response,
        array $actor,
        int $startedNs,
        ?Throwable $exception = null,
    ): void {
        $classification = $this->classifier->classify($request);
        $content = $response?->getContent();
        $contentSize = is_string($content) ? strlen($content) : null;
        $headerSize = $response?->headers->get('Content-Length');
        if (is_numeric($headerSize)) {
            $contentSize = max(0, (int) $headerSize);
        }

        ActivityRequest::query()->whereKey($id)->update(array_merge($actor, $classification, [
            'http_status' => $response?->getStatusCode() ?? $this->exceptionStatus($exception),
            'outcome' => $this->classifier->outcome($response, $exception, $request),
            'redirect_to' => trim((string) ($response?->headers->get('Location') ?? '')) ?: null,
            'response_content_type' => trim((string) ($response?->headers->get('Content-Type') ?? '')) ?: null,
            'response_size' => $contentSize,
            'duration_ms' => round((hrtime(true) - $startedNs) / 1_000_000, 3),
            'error_type' => $exception !== null ? $exception::class : null,
            'error_message' => $exception !== null ? $this->safeErrorMessage($exception) : null,
            'completed_at' => CarbonImmutable::now('UTC'),
        ]));
    }

    private function excluded(Request $request): bool
    {
        return $request->is('up', 'assets/*', 'build/*', 'storage/*', '@vite/*')
            || in_array($request->path(), ['favicon.ico', 'robots.txt'], true);
    }

    private function tablesAvailable(): bool
    {
        try {
            return Schema::hasTable('aktivitas');
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private function requestInput(Request $request): ?array
    {
        $fields = $this->sanitizer->sanitize($request->request->all());
        $files = $this->sanitizer->sanitize($request->allFiles());
        $input = [];
        if ($fields !== []) {
            $input['fields'] = $fields;
        }
        if ($files !== []) {
            $input['files'] = $files;
        }

        return $input !== [] ? $input : null;
    }

    private function clientIp(Request $request): ?string
    {
        $ip = trim((string) ($request->server('REMOTE_ADDR') ?? $request->ip()));

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private function exceptionStatus(?Throwable $exception): int
    {
        if ($exception instanceof ValidationException) {
            return $exception->status;
        }
        if ($exception instanceof TokenMismatchException) {
            return 419;
        }
        if ($exception instanceof AuthorizationException) {
            return 403;
        }
        if ($exception instanceof AuthenticationException) {
            return 401;
        }
        if ($exception instanceof ModelNotFoundException) {
            return 404;
        }

        return $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            return 'Operasi database gagal.';
        }
        $message = preg_replace('/\s+/', ' ', trim($exception->getMessage())) ?? '';
        if ($message === '') {
            return 'Request gagal diproses.';
        }

        $message = preg_replace(
            '/\b(password|passwd|token|secret|authorization|cookie|session)\b\s*[:=]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $message,
        ) ?? $message;
        $message = preg_replace('/\bBearer\s+[^\s,;]+/i', 'Bearer [REDACTED]', $message) ?? $message;

        return function_exists('mb_substr') ? mb_substr($message, 0, 1000) : substr($message, 0, 1000);
    }

    private function exceptionAction(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ValidationException => 'request.validation_failed',
            $exception instanceof TokenMismatchException => 'request.csrf_failed',
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => 'request.access_denied',
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => 'request.not_found',
            default => 'request.exception',
        };
    }

    private function responseAction(int $status): string
    {
        return match (true) {
            $status === 401 => 'request.authentication_required',
            $status === 403 => 'request.access_denied',
            $status === 404 => 'request.not_found',
            $status === 419 => 'request.csrf_failed',
            $status === 422 => 'request.validation_failed',
            $status >= 500 => 'request.server_error',
            default => 'request.failed',
        };
    }

    private function hasValidationErrors(Request $request, Response $response): bool
    {
        if (! $response->isRedirect() || ! $request->hasSession()) {
            return false;
        }

        $errors = $request->session()->get('errors');

        return $errors instanceof ViewErrorBag && $errors->any();
    }

    private function redirectFailureAction(Request $request, Response $response): ?string
    {
        if (! $response->isRedirect()) {
            return null;
        }

        $location = trim((string) $response->headers->get('Location'));
        if (preg_match('/(?:^|[?&])error=access_denied(?:&|$)/', $location) === 1) {
            return 'request.access_denied';
        }
        $targetPath = trim((string) parse_url($location, PHP_URL_PATH), '/');
        if ($targetPath === 'login' && ! $request->is('login')) {
            return 'request.authentication_required';
        }
        if (preg_match('/(?:^|[?&])(error|material_error)=/', $location) === 1) {
            return 'request.failed';
        }

        return null;
    }
}
