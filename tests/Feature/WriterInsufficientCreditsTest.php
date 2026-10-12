<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Exceptions\InsufficientCreditsException;
use App\Services\AiArabicWriterService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WriterInsufficientCreditsTest extends TestCase
{
    public function test_non_success_provider_response_preserves_credit_error_without_retry(): void
    {
        config()->set('services.aiarabic.url', 'https://provider.example.test');
        Http::fake(['*' => Http::response([
            'detail' => [
                'code' => 'INSUFFICIENT_CREDITS',
                'available_credits' => 9676,
                'required_credits' => 22000,
                'shortfall_credits' => 12324,
            ],
        ], 422)]);

        try {
            (new AiArabicWriterService())->generateReplyWithUsage(['user_message' => 'Test prompt'], 'tasks/text-writer');
            $this->fail('Expected a credit exception.');
        } catch (InsufficientCreditsException $exception) {
            $this->assertSame(422, $exception->httpStatus);
            $this->assertSame(9676, $exception->availableCredits);
            $this->assertSame(22000, $exception->requiredCredits);
            $this->assertSame(12324, $exception->shortfallCredits);
        }

        Http::assertSentCount(1);
    }

    public function test_other_provider_errors_keep_the_existing_exception_type(): void
    {
        config()->set('services.aiarabic.url', 'https://provider.example.test');
        Http::fake(['*' => Http::response(['detail' => ['code' => 'OTHER_ERROR']], 422)]);

        $this->expectException(AiServiceException::class);
        (new AiArabicWriterService())->generateReplyWithUsage(['user_message' => 'Test prompt'], 'tasks/text-writer');
    }
}
