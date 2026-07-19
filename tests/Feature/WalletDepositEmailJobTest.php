<?php

namespace Tests\Feature;

use App\Jobs\SendDepositSuccessMailJob;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WalletDepositEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_sent_is_set_only_after_the_mailer_succeeds(): void
    {
        $payment = $this->completedPayment();
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->once()->andReturnNull();

        (new SendDepositSuccessMailJob($payment->id))->handle($mailer);

        $this->assertTrue($payment->fresh()->mail_sent);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->wallet_credited);
    }

    public function test_mail_failure_never_reverts_a_completed_payment_or_credit(): void
    {
        $payment = $this->completedPayment();
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        try {
            (new SendDepositSuccessMailJob($payment->id))->handle($mailer);
            $this->fail('The mail failure was not propagated for a queue retry.');
        } catch (RuntimeException $e) {
            $this->assertSame('SMTP unavailable', $e->getMessage());
            $payment->refresh();
            $this->assertFalse($payment->mail_sent);
            $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);
            $this->assertTrue($payment->wallet_credited);
        }
    }

    private function completedPayment(): Payment
    {
        return Payment::create([
            'user_id' => User::factory()->create()->id,
            'payment_method' => 'paypal',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_COMPLETED,
            'currency' => 'USD',
            'amount' => '8.50',
            'paypal_order_id' => 'PAYPAL-'.Str::random(10),
            'transaction_id' => 'CAPTURE-'.Str::random(10),
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'mail_sent' => false,
            'wallet_credited' => true,
            'paid_at' => now(),
        ]);
    }
}
