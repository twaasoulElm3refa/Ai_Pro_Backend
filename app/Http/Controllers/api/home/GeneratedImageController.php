<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Models\GeneratedImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneratedImageController extends Controller
{
    public function preview(GeneratedImage $image): Response
    {
        $this->authorizeImage($image);

        return $this->previewResponse($image, 'private, max-age=3600');
    }

    public function homePreview(GeneratedImage $image): Response
    {
        if (! str_starts_with(strtolower($image->content_type), 'image/')) {
            abort(404);
        }

        if (! Storage::disk($image->disk)->exists($image->path)) {
            abort(404);
        }

        return $this->previewResponse($image, 'private, max-age=18000, immutable');
    }

    private function previewResponse(GeneratedImage $image, string $cacheControl): Response
    {

        $headers = [
            'Content-Type' => $image->content_type,
            'Content-Disposition' => 'inline; filename="'.$image->filename.'"',
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
        ];

        if (config("filesystems.disks.{$image->disk}.driver") === 'local') {
            $response = response()->file(
                Storage::disk($image->disk)->path($image->path),
                $headers
            );
        } else {
            $response = Storage::disk($image->disk)->response(
                $image->path,
                $image->filename,
                $headers
            );
        }

        // BinaryFileResponse may normalize Cache-Control to public; retain the
        // intended private policy because generated images belong to one user.
        $response->headers->set('Cache-Control', $cacheControl);

        return $response;
    }

    public function download(GeneratedImage $image): StreamedResponse
    {
        $this->authorizeImage($image);

        return Storage::disk($image->disk)->download(
            $image->path,
            $image->filename,
            [
                'Content-Type' => $image->content_type,
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function authorizeImage(GeneratedImage $image): void
    {
        if ((int) $image->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if (! Storage::disk($image->disk)->exists($image->path)) {
            abort(404);
        }
    }
}
