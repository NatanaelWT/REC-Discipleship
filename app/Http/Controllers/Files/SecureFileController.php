<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Requests\SecureFiles\ShowSecureFileRequest;
use App\Services\Activity\ActivityRecorder;
use App\Services\SecureFiles\SecureFilePreviewData;
use App\Services\SecureFiles\SecureFileResolver;
use App\Services\SecureFiles\SecureFileStreamer;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SecureFileController extends Controller
{
    public function show(
        ShowSecureFileRequest $request,
        SecureFileResolver $resolver,
        SecureFilePreviewData $previewData,
        SecureFileStreamer $streamer,
        ActivityRecorder $activity,
    ): Response|StreamedResponse|View {
        try {
            $file = $resolver->resolve($request);
            $activity->record(
                'file',
                $file->download ? 'secure_file.downloaded' : 'secure_file.previewed',
                'secure_file',
                hash('sha256', $file->relativePath),
                $file->downloadName,
                $file->download ? 'File aman diunduh.' : 'File aman dibuka.',
                metadata: [
                    'relative_path' => $file->relativePath,
                    'name' => $file->downloadName,
                    'size_bytes' => $file->contentLength,
                    'mime_type' => $file->mimeType,
                    'sha256' => is_file($file->fullPath) ? hash_file('sha256', $file->fullPath) : null,
                ],
            );

            if ($previewData->shouldPreview($request, $file)) {
                return view('files.secure-preview', $previewData->forRequest($request, $file));
            }

            return $streamer->stream($file);
        } catch (HttpExceptionInterface $exception) {
            return response($exception->getMessage(), $exception->getStatusCode());
        }
    }
}
