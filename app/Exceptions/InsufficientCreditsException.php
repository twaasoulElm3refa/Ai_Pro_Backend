<?php

namespace App\Exceptions;

use RuntimeException;

final class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int|float|null $availableCredits,
        public readonly int|float|null $requiredCredits,
        public readonly int|float|null $shortfallCredits,
        public readonly int $httpStatus = 402,
    ) {
        parent::__construct('Insufficient credits for this operation.');
    }

    public static function fromPayload(mixed $payload, int $status = 402): ?self
    {
        if (! is_array($payload)) {
            return null;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        foreach ([$payload, $payload['detail'] ?? null, $payload['error'] ?? null, $data, $data['detail'] ?? null, $data['error'] ?? null] as $candidate) {
            if (! is_array($candidate) || ($candidate['code'] ?? null) !== 'INSUFFICIENT_CREDITS') {
                continue;
            }

            $available = self::credit($candidate['available_credits'] ?? null);
            $required = self::credit($candidate['required_credits'] ?? null);
            $shortfall = self::credit($candidate['shortfall_credits'] ?? null);

            return new self(
                $available,
                $required,
                $shortfall ?? ($available !== null && $required !== null ? max(0, $required - $available) : null),
                $status >= 400 && $status <= 599 ? $status : 402,
            );
        }

        return null;
    }

    public function payload(): array
    {
        return [
            'code' => 'INSUFFICIENT_CREDITS',
            'available_credits' => $this->availableCredits,
            'required_credits' => $this->requiredCredits,
            'shortfall_credits' => $this->shortfallCredits,
        ];
    }

    private static function credit(mixed $value): int|float|null
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        return is_finite((float) $value) && $value >= 0 ? $value : null;
    }
}
