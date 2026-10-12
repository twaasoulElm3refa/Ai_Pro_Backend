<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientCreditsException;
use PHPUnit\Framework\TestCase;

class InsufficientCreditsExceptionTest extends TestCase
{
    public function test_it_recognizes_structured_provider_error_and_preserves_amounts(): void
    {
        $exception = InsufficientCreditsException::fromPayload([
            'detail' => [
                'code' => 'INSUFFICIENT_CREDITS',
                'available_credits' => 9676,
                'required_credits' => 22000,
                'shortfall_credits' => 12324,
                'selected_model_id' => 188,
            ],
        ], 422);

        $this->assertNotNull($exception);
        $this->assertSame(422, $exception->httpStatus);
        $this->assertSame([
            'code' => 'INSUFFICIENT_CREDITS',
            'available_credits' => 9676,
            'required_credits' => 22000,
            'shortfall_credits' => 12324,
        ], $exception->payload());
    }

    public function test_zero_missing_and_unrelated_errors(): void
    {
        $exception = InsufficientCreditsException::fromPayload([
            'error' => ['code' => 'INSUFFICIENT_CREDITS', 'available_credits' => 0, 'required_credits' => 5],
        ], 400);

        $this->assertSame(0, $exception->availableCredits);
        $this->assertSame(5, $exception->shortfallCredits);
        $this->assertNull(InsufficientCreditsException::fromPayload(['code' => 'OTHER_ERROR']));
        $this->assertNull(InsufficientCreditsException::fromPayload(['detail' => 'not an object']));
        $this->assertNull(InsufficientCreditsException::fromPayload([
            'code' => 'INSUFFICIENT_CREDITS', 'available_credits' => '0', 'required_credits' => NAN,
        ])->shortfallCredits);
    }
}
