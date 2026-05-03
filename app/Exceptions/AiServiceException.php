<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AiServiceException extends RuntimeException
{
    public function __construct(
        string $message = 'AI service request failed.',
        protected array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return $this->context;
    }
}
