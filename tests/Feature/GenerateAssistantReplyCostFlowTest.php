<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Jobs\GenerateAssistantReplyJob;
use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AI\AIPayloadBuilder;
use App\Services\AiArabicWriterService;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class GenerateAssistantReplyCostFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.aiarabic.inject_qdrant_context', false);
    }

    public function test_it_uses_provider_usage_and_cost_and_charges_wallet(): void
    {
        [$conversation, $userMessage, $wallet] = $this->makeConversationContext(true, 1000);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')->once()->andReturn([
            'reply' => 'Provider reply text',
            'usage' => [
                'input_tokens' => 1345,
                'output_tokens' => 340,
                'total_tokens' => 1685,
            ],
            'cost' => [
                'input_cost' => 0.00168125,
                'output_cost' => 0.0034,
                'web_search_cost' => 0.0,
                'total_cost' => 0.00508125,
                'currency' => 'USD',
            ],
            'request_id' => (string) Str::uuid(),
            'model_key' => 'writer_pro',
        ]);

        $payloadBuilder = $this->mockPayloadBuilder();
        $qdrant = $this->mockQdrant($conversation->id, 2);

        $job = new GenerateAssistantReplyJob($userMessage->id);
        $job->handle($writer, $payloadBuilder, app(ConversationMessageCacheService::class), $qdrant);

        $assistant = Message::where('reply_to_message_id', $userMessage->id)->first();
        $this->assertNotNull($assistant);
        $this->assertSame('Provider reply text', $assistant->content);

        $cost = CostLogger::where('conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($cost);
        $this->assertSame(1345, (int) $cost->input_tokens);
        $this->assertSame(340, (int) $cost->output_tokens);
        $this->assertSame(1685, (int) $cost->total_tokens);
        $this->assertEqualsWithDelta(0.00168125, (float) $cost->input_cost, 0.00000001);
        $this->assertEqualsWithDelta(0.0034, (float) $cost->output_cost, 0.00000001);
        $this->assertEqualsWithDelta(0.00508125, (float) $cost->total_cost, 0.00000001);

        $wallet->refresh();
        $this->assertSame(999, (int) $wallet->balance);
    }

    public function test_it_falls_back_to_formula_when_provider_cost_is_missing(): void
    {
        [$conversation, $userMessage, $wallet] = $this->makeConversationContext(true, 1000);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')->once()->andReturn([
            'reply' => 'Usage only reply',
            'usage' => [
                'input_tokens' => 1000,
                'output_tokens' => 340,
                'total_tokens' => 1340,
            ],
        ]);

        $payloadBuilder = $this->mockPayloadBuilder();
        $qdrant = $this->mockQdrant($conversation->id, 2);

        $job = new GenerateAssistantReplyJob($userMessage->id);
        $job->handle($writer, $payloadBuilder, app(ConversationMessageCacheService::class), $qdrant);

        $cost = CostLogger::where('conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(0.00125, (float) $cost->input_cost, 0.00000001);
        $this->assertEqualsWithDelta(0.0034, (float) $cost->output_cost, 0.00000001);
        $this->assertEqualsWithDelta(0.00465, (float) $cost->total_cost, 0.00000001);
        $this->assertEqualsWithDelta(0.0, (float) $cost->web_search_cost, 0.00000001);

        $wallet->refresh();
        $this->assertSame(999, (int) $wallet->balance);
    }

    public function test_it_handles_string_reply_only_and_still_creates_cost_row(): void
    {
        [$conversation, $userMessage] = $this->makeConversationContext(false);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')->once()->andReturn('Plain assistant reply');

        $payloadBuilder = $this->mockPayloadBuilder();
        $qdrant = $this->mockQdrant($conversation->id, 2);

        $job = new GenerateAssistantReplyJob($userMessage->id);
        $job->handle($writer, $payloadBuilder, app(ConversationMessageCacheService::class), $qdrant);

        $assistant = Message::where('reply_to_message_id', $userMessage->id)->first();
        $this->assertNotNull($assistant);
        $this->assertSame('Plain assistant reply', $assistant->content);

        $cost = CostLogger::where('conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($cost);
        $this->assertGreaterThan(0, (int) $cost->total_tokens);
        $this->assertGreaterThan(0, (float) $cost->total_cost);
    }

    public function test_it_defaults_web_search_cost_when_missing_from_provider_cost(): void
    {
        [$conversation, $userMessage, $wallet] = $this->makeConversationContext(true, 500);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')->once()->andReturn([
            'reply' => 'No web search cost in payload',
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 10,
                'total_tokens' => 20,
            ],
            'cost' => [
                'input_cost' => 0.0000125,
                'output_cost' => 0.0001,
                'total_cost' => 0.0001125,
                'currency' => 'USD',
            ],
        ]);

        $payloadBuilder = $this->mockPayloadBuilder();
        $qdrant = $this->mockQdrant($conversation->id, 2);

        $job = new GenerateAssistantReplyJob($userMessage->id);
        $job->handle($writer, $payloadBuilder, app(ConversationMessageCacheService::class), $qdrant);

        $cost = CostLogger::where('conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(0.0, (float) $cost->web_search_cost, 0.00000001);

        $wallet->refresh();
        $this->assertSame(499, (int) $wallet->balance);
    }

    public function test_it_throws_when_provider_fails_and_does_not_store_assistant_or_cost(): void
    {
        [$conversation, $userMessage] = $this->makeConversationContext(false);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')->once()->andThrow(new AiServiceException('provider failed'));

        $payloadBuilder = $this->mockPayloadBuilder();
        $qdrant = $this->mockQdrant($conversation->id, 1);

        $job = new GenerateAssistantReplyJob($userMessage->id);

        $this->expectException(AiServiceException::class);
        $job->handle($writer, $payloadBuilder, app(ConversationMessageCacheService::class), $qdrant);

        $this->assertNull(Message::where('reply_to_message_id', $userMessage->id)->first());
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
    }

    public function test_it_handles_missing_wallet_without_failing(): void
    {
        [$conversation, $userMessage] = $this->makeConversationContext(false);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')->once()->andReturn([
            'reply' => 'Wallet missing scenario',
            'usage' => [
                'input_tokens' => 50,
                'output_tokens' => 10,
                'total_tokens' => 60,
            ],
            'cost' => [
                'input_cost' => 0.0000625,
                'output_cost' => 0.0001,
                'total_cost' => 0.0001625,
                'currency' => 'USD',
            ],
        ]);

        $payloadBuilder = $this->mockPayloadBuilder();
        $qdrant = $this->mockQdrant($conversation->id, 2);

        $job = new GenerateAssistantReplyJob($userMessage->id);
        $job->handle($writer, $payloadBuilder, app(ConversationMessageCacheService::class), $qdrant);

        $cost = CostLogger::where('conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($cost);
        $this->assertNull(Wallet::where('user_id', $conversation->user_id)->first());
    }

    protected function makeConversationContext(bool $withWallet, int $walletBalance = 0): array
    {
        $user = User::factory()->create();

        if ($withWallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'balance' => $walletBalance,
                'is_active' => true,
            ]);
        } else {
            $wallet = null;
        }

        $mainTool = MainTools::create([
            'name' => 'Main Tool ' . Str::random(8),
            'slug' => 'main-' . Str::random(10),
        ]);

        $subTool = SubTools::create([
            'main_tool_id' => $mainTool->id,
            'name' => 'Sub Tool ' . Str::random(8),
            'slug' => 'sub-' . Str::random(10),
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);

        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'content' => 'Hello from user',
            'role' => 'user',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$conversation, $userMessage, $wallet];
    }

    protected function mockPayloadBuilder()
    {
        $payloadBuilder = Mockery::mock(AIPayloadBuilder::class);
        $payloadBuilder->shouldReceive('build')->once()->andReturn(['dummy' => true]);
        $payloadBuilder->shouldReceive('withTaskOptions')->once()->andReturn(['dummy' => true]);

        return $payloadBuilder;
    }

    protected function mockQdrant(int $conversationId, int $insertTimes)
    {
        $qdrant = Mockery::mock(QdrantService::class);
        $qdrant->shouldReceive('collectionName')->andReturn("conversation_{$conversationId}");
        $qdrant->shouldReceive('insertMessage')->times($insertTimes);

        return $qdrant;
    }
}
