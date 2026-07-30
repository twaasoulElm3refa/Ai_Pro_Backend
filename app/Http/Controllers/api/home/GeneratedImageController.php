<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Models\GeneratedImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneratedImageController extends Controller
{
    public function preview(GeneratedImage $image): StreamedResponse
    {
        $this->authorizeImage($image);

        return Storage::disk($image->disk)->response(
            $image->path,
            $image->filename,
            [
                'Content-Type' => $image->content_type,
                'Content-Disposition' => 'inline; filename="'.$image->filename.'"',
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
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
