<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('wallet_credited')->default(false)->after('mail_sent');
        });

        DB::table('payments')
            ->where('type', 'wallet_deposit')
            ->where('status', 'completed')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('wallet_transactions')
                    ->whereColumn('wallet_transactions.payment_id', 'payments.id');
            })
            ->update(['wallet_credited' => true]);

        Schema::table('wallets', function (Blueprint $table) {
            $table->unsignedBigInteger('balance')->default(0)->change();
            $table->unsignedBigInteger('payback_balance')->default(0)->change();
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('points')->default(0)->change();
            $table->bigInteger('balance_before')->default(0)->change();
            $table->bigInteger('balance_after')->default(0)->change();
            $table->unique('payment_id', 'wallet_transactions_payment_id_unique');
            $table->unique('slug', 'wallet_transactions_slug_unique');
        });

        Schema::create('paypal_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->string('paypal_order_id')->nullable()->index();
            $table->string('capture_id')->nullable()->index();
            $table->json('payload');
            $table->string('status')->default('received')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_webhook_events');

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('wallet_transactions_payment_id_unique');
            $table->dropUnique('wallet_transactions_slug_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('wallet_credited');
        });
    }
};
