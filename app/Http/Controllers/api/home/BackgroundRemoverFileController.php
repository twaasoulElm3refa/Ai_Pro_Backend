<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Models\GeneratedImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackgroundRemoverFileController extends Controller
{
    public function preview(GeneratedImage $file): StreamedResponse
    {
        $this->authorizeFile($file);

        return Storage::disk($file->disk)->response($file->path, $file->filename, [
            'Content-Type' => $file->content_type,
            'Content-Disposition' => 'inline; filename="'.$file->filename.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(GeneratedImage $file): StreamedResponse
    {
        $this->authorizeFile($file);

        return Storage::disk($file->disk)->download($file->path, $file->filename, [
            'Content-Type' => $file->content_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeFile(GeneratedImage $file): void
    {
        abort_unless((int) $file->user_id === (int) auth()->id(), 403);
        abort_unless((int) $file->sub_tool_id === 22, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);
    }
}
