<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Models\ModelsMessageFile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class FreeAiModelFileController extends Controller
{
    public function content(Request $request, string $fileId): Response
    {
        $file = ModelsMessageFile::query()
            ->with('message.conversation')
            ->where('file_id', $fileId)
            ->first();
        $conversation = $file?->message?->conversation;

        if (! $file || ! $conversation
            || (int) $conversation->user_id !== (int) $request->user()->id
            || $conversation->selected_model_source !== 'general_media') {
            abort(404);
        }

        $url = $this->resolveTrustedDownloadUrl($file->download_url);
        $key = trim((string) config('services.aiarabic.internal_api_key'));
        if ($url === null || $key === '') {
            abort(503, 'Generated media is temporarily unavailable.');
        }

        try {
            $upstream = Http::withHeaders(['X-Internal-Api-Key' => $key])
                ->accept('*/*')
                ->connectTimeout(10)
                ->timeout(180)
                ->get($url);
        } catch (ConnectionException) {
            abort(504, 'Unable to retrieve generated media.');
        }

        if (! $upstream->successful()) {
            $status = $upstream->status() === 429 ? 429 : 502;
            $response = response('Unable to retrieve generated media.', $status);
            if ($status === 429 && $upstream->header('Retry-After')) {
                $response->headers->set('Retry-After', $upstream->header('Retry-After'));
            }

            return $response;
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $filename = str_replace(['"', "\r", "\n"], '', basename($file->filename));

        return response($upstream->body(), 200, [
            'Content-Type' => $file->content_type ?: 'application/octet-stream',
            'Content-Length' => (string) strlen($upstream->body()),
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveTrustedDownloadUrl(string $downloadUrl): ?string
    {
        $baseUrl = rtrim((string) config(
            'services.aiarabic.base_url',
            config('services.aiarabic.public_base_url', 'https://api.aiarabic.com')
        ), '/');
        $url = filter_var($downloadUrl, FILTER_VALIDATE_URL)
            ? $downloadUrl
            : (str_starts_with($downloadUrl, '/tasks/generated-files/download/')
                ? $baseUrl.$downloadUrl
                : null);
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        $baseParts = parse_url($baseUrl);
        if (! is_array($parts) || ! is_array($baseParts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $baseScheme = strtolower((string) ($baseParts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $baseHost = strtolower((string) ($baseParts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $basePort = (int) ($baseParts['port'] ?? ($baseScheme === 'https' ? 443 : 80));

        return in_array($scheme, ['http', 'https'], true)
            && $scheme === $baseScheme
            && $host !== ''
            && hash_equals($baseHost, $host)
            && $port === $basePort
            ? $url
            : null;
    }
}
