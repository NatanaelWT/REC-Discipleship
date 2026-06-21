<?php

namespace App\Services\Activity;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ActivityRequestClassifier
{
    /** @return array{route_name:?string,category:string,action:string,subject_type:?string,subject_id:?string} */
    public function classify(Request $request): array
    {
        $route = $request->route();
        $routeName = $route instanceof Route ? $route->getName() : null;
        $routeName = is_string($routeName) && trim($routeName) !== '' ? trim($routeName) : null;
        $action = $routeName ?? strtolower($request->method()).' '.$request->path();

        [$subjectType, $subjectId] = $this->subject($route);

        return [
            'route_name' => $routeName,
            'category' => $this->category($routeName, $request),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ];
    }

    public function outcome(?Response $response, ?\Throwable $exception = null, ?Request $request = null): string
    {
        if ($exception !== null) {
            return $exception instanceof AuthorizationException
                || $exception instanceof AccessDeniedHttpException
                ? 'denied'
                : 'failed';
        }

        if ($request?->attributes->get('activity.validation_failed') === true) {
            return 'failed';
        }
        $forcedOutcome = $request?->attributes->get('activity.forced_outcome');
        if (in_array($forcedOutcome, ['failed', 'denied'], true)) {
            return $forcedOutcome;
        }

        $status = $response?->getStatusCode() ?? 500;
        if (in_array($status, [401, 403], true)) {
            return 'denied';
        }
        if ($status >= 400) {
            return 'failed';
        }

        $location = trim((string) ($response?->headers->get('Location') ?? ''));
        if ($location !== '' && preg_match('/(?:^|[?&])(error|material_error)=/', $location) === 1) {
            return 'failed';
        }

        return 'succeeded';
    }

    private function category(?string $routeName, Request $request): string
    {
        if ($routeName === null) {
            return 'security';
        }
        if (str_starts_with($routeName, 'auth.')) {
            return 'auth';
        }
        if (str_contains($routeName, 'import')) {
            return 'import';
        }
        if (str_contains($routeName, 'export') || str_contains($routeName, 'download') || str_ends_with($routeName, '.image')) {
            return 'export';
        }
        if (str_starts_with($routeName, 'materials.') || $routeName === 'secure-file.show') {
            return 'file';
        }
        if (str_starts_with($routeName, 'developer.')) {
            return 'developer';
        }
        if (str_starts_with($routeName, 'public.')) {
            return $request->isMethodSafe() ? 'navigation' : 'public_submission';
        }
        if ($request->isMethodSafe()) {
            return 'navigation';
        }

        return 'data';
    }

    /** @return array{0:?string,1:?string} */
    private function subject(mixed $route): array
    {
        if (! $route instanceof Route) {
            return [null, null];
        }

        foreach ($route->parameters() as $key => $value) {
            if ($value instanceof Model) {
                return [$value->getTable(), (string) $value->getKey()];
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                return [(string) $key, trim((string) $value)];
            }
        }

        return [null, null];
    }
}
