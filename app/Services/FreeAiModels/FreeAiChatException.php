<?php

namespace App\Services\FreeAiModels;

use RuntimeException;

class FreeAiChatException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $retryAfter = null
    )
    {
        parent::__construct($message);
    }
}
