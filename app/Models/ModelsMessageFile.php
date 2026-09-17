<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelsMessageFile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ModelsMessage::class, 'models_message_id');
    }

    public function attachmentPayload(): array
    {
        $type = str_starts_with(strtolower($this->content_type), 'image/')
            ? 'image'
            : (str_starts_with(strtolower($this->content_type), 'video/') ? 'video' : 'file');
        $url = route('free-ai-model-files.content', ['fileId' => $this->file_id], false);

        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'filename' => $this->filename,
            'content_type' => $this->content_type,
            'size_bytes' => $this->size_bytes,
            'type' => $type,
            'url' => $url,
            'download_url' => $url.'?download=1',
            'metadata' => $this->metadata,
        ];
    }
}
