<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ProviderCostBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderCostBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_total_cost_to_points_without_float_arithmetic(): void
    {
        [$user, $conversation] = $this->makeContext(21, 10_000);

        $result = $this->billing()->chargeSuccessfulResponse(
            $user->id,
            $conversation->id,
            21,
            ['cost' => ['total_cost' => '0.0006', 'currency' => 'USD']],
            'request-21',
            'image-model'
        );

        $this->assertSame(600, $result['points_to_deduct']);
        $this->assertSame(600, $result['points_deducted']);
        $this->assertSame(9_400, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'sub_tool_id' => 21,
            'total_cost' => 0.0006,
            'currency' => 'USD',
            'provider_request_id' => 'request-21',
            'model_key' => 'image-model',
        ]);
    }

    public function test_zero_total_cost_is_logged_without_deducting_points(): void
    {
        [$user, $conversation] = $this->makeContext(22, 10_000);

        $result = $this->billing()->chargeSuccessfulResponse(
            $user->id,
            $conversation->id,
            22,
            ['cost' => ['total_cost' => 0, 'currency' => 'USD']]
        );

        $this->assertSame('zero_cost', $result['status']);
        $this->assertSame(0, $result['points_deducted']);
        $this->assertSame(10_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'sub_tool_id' => 22,
            'total_cost' => 0,
        ]);
    }

    public function test_missing_cost_or_total_cost_never_deducts_or_creates_a_cost_log(): void
    {
        [$user, $conversation] = $this->makeContext(23, 10_000);

        $missingCost = $this->billing()->chargeSuccessfulResponse(
            $user->id,
            $conversation->id,
            23,
            []
        );
        $missingTotal = $this->billing()->chargeSuccessfulResponse(
            $user->id,
            $conversation->id,
            23,
            ['cost' => ['currency' => 'USD']]
        );

        $this->assertSame('skipped_invalid_cost', $missingCost['status']);
        $this->assertSame('skipped_invalid_cost', $missingTotal['status']);
        $this->assertSame(10_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('cost_loggers', 0);
    }

    public function test_non_usd_or_negative_cost_never_deducts_points(): void
    {
        [$user, $conversation] = $this->makeContext(21, 10_000);

        $eur = $this->billing()->chargeSuccessfulResponse(
            $user->id,
            $conversation->id,
            21,
            ['cost' => ['total_cost' => '0.0006', 'currency' => 'EUR']]
        );
        $negative = $this->billing()->chargeSuccessfulResponse(
            $user->id,
            $conversation->id,
            21,
            ['cost' => ['total_cost' => '-0.0006', 'currency' => 'USD']]
        );

        $this->assertSame(0, $eur['points_deducted']);
        $this->assertSame(0, $negative['points_deducted']);
        $this->assertSame(10_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('cost_loggers', 0);
    }

    public function test_tool_24_and_other_tools_are_outside_this_billing_service(): void
    {
        [$user, $conversation] = $this->makeContext(24, 10_000);

        foreach ([24, 20] as $unsupportedSubToolId) {
            try {
                $this->billing()->chargeSuccessfulResponse(
                    $user->id,
                    $conversation->id,
                    $unsupportedSubToolId,
                    ['cost' => ['total_cost' => '0.0006', 'currency' => 'USD']]
                );
                $this->fail("Tool {$unsupportedSubToolId} must not be handled by provider-cost billing.");
            } catch (InvalidArgumentException) {
                $this->assertSame(10_000, Wallet::where('user_id', $user->id)->value('balance'));
                $this->assertDatabaseCount('cost_loggers', 0);
            }
        }
    }

    private function billing(): ProviderCostBillingService
    {
        return app(ProviderCostBillingService::class);
    }

    private function makeContext(int $subToolId, int $balance): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Billing Test Tool '.Str::random(6),
            'slug' => 'billing-test-tool-'.Str::random(6),
        ]);
        $subTool = SubTools::create([
            'id' => $subToolId,
            'main_tool_id' => $mainTool->id,
            'name' => "Sub Tool {$subToolId}",
            'slug' => "sub-tool-{$subToolId}-".Str::random(6),
        ]);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => $balance,
            'payback_balance' => 0,
            'is_active' => true,
        ]);

        return [$user, $conversation];
    }
}
