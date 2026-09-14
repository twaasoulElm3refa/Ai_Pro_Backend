<?php

namespace App\Services\FreeAiModels;

use RuntimeException;

class FreeAiChatException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode)
    {
        parent::__construct($message);
    }
}
