<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Requests\SecureFiles\ShowSecureFileRequest;
use App\Services\SecureFiles\SecureFilePreviewData;
use App\Services\SecureFiles\SecureFileResolver;
use App\Services\SecureFiles\SecureFileStreamer;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SecureFileController extends Controller
{
    public function show(
        ShowSecureFileRequest $request,
        SecureFileResolver $resolver,
        SecureFilePreviewData $previewData,
        SecureFileStreamer $streamer,
    ): BinaryFileResponse|Response|View {
        try {
            $file = $resolver->resolve($request);
            if ($previewData->shouldPreview($request, $file)) {
                return view('files.secure-preview', $previewData->forRequest($request, $file));
            }

            return $streamer->stream($file);
        } catch (HttpExceptionInterface $exception) {
            return response($exception->getMessage(), $exception->getStatusCode());
        }
    }
}
