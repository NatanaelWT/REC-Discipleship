<?php

namespace App\Services\SecureFiles;

use App\Http\Requests\SecureFiles\ShowSecureFileRequest;

class SecureFilePreviewData
{
    public function shouldPreview(ShowSecureFileRequest $request, SecureFile $file): bool
    {
        return ! $file->download && ! $request->rawRequested() && $this->isTopLevelNavigation($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function forRequest(ShowSecureFileRequest $request, SecureFile $file): array
    {
        return [
            'settings' => ['church_name' => app_church_name()],
            'title' => $file->isPdf() ? 'Preview PDF' : 'Preview File',
            'bodyClass' => $file->isPdf() ? 'page-file-preview-standalone' : 'page-file-preview',
            'file' => $file,
            'previewTitle' => $this->previewTitle($file),
            'rawUrl' => secure_upload_url($file->relativePath, false, '', true),
            'downloadUrl' => secure_upload_url($file->relativePath, true, $file->downloadName),
            'backUrl' => $this->backUrl($request),
            'backOnClick' => 'if (window.history.length > 1) { window.history.back(); return false; }',
        ];
    }

    private function previewTitle(SecureFile $file): string
    {
        $previewTitle = trim($file->downloadName);
        if ($previewTitle === '') {
            $previewTitle = basename($file->fullPath);
        }

        return $previewTitle !== '' ? $previewTitle : 'File';
    }

    private function backUrl(ShowSecureFileRequest $request): string
    {
        $backUrl = '/';
        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer === '' || ! is_same_origin_url($referer)) {
            return $backUrl;
        }

        $parsedReferer = @parse_url($referer);
        if (! is_array($parsedReferer)) {
            return $backUrl;
        }

        $refererPath = trim((string) ($parsedReferer['path'] ?? ''));
        $refererQuery = trim((string) ($parsedReferer['query'] ?? ''));
        $refererFragment = trim((string) ($parsedReferer['fragment'] ?? ''));
        $refererPage = '';
        if ($refererQuery !== '') {
            $queryParams = [];
            parse_str($refererQuery, $queryParams);
            if (is_array($queryParams)) {
                $refererPage = trim((string) ($queryParams['page'] ?? ''));
            }
        }

        $securePath = route('secure-file.show', [], false);
        if ($refererPath === '' || $refererPath === $securePath || $refererPage === 'secure_file') {
            return $backUrl;
        }

        $candidateBackUrl = $refererPath;
        if ($refererQuery !== '') {
            $candidateBackUrl .= '?' . $refererQuery;
        }
        if ($refererFragment !== '') {
            $candidateBackUrl .= '#' . $refererFragment;
        }

        return $candidateBackUrl !== '' ? $candidateBackUrl : $backUrl;
    }

    private function isTopLevelNavigation(ShowSecureFileRequest $request): bool
    {
        $destination = strtolower(trim((string) $request->headers->get('sec-fetch-dest', '')));
        if ($destination !== '') {
            return $destination === 'document';
        }

        $mode = strtolower(trim((string) $request->headers->get('sec-fetch-mode', '')));
        if ($mode !== '') {
            return $mode === 'navigate';
        }

        return false;
    }
}
