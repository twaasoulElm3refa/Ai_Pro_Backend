<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('moyasar_invoice_id', 64)
                ->nullable()
                ->unique()
                ->after('paypal_order_id');
            $table->string('moyasar_payment_id', 64)
                ->nullable()
                ->unique()
                ->after('moyasar_invoice_id');
        });

        Schema::create('moyasar_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->string('event_type', 64)->nullable()->index();
            $table->string('moyasar_payment_id', 64)->nullable()->index();
            $table->foreignId('local_payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();
            $table->json('payload');
            $table->string('status', 32)->default('received')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moyasar_webhook_events');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_moyasar_invoice_id_unique');
            $table->dropUnique('payments_moyasar_payment_id_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['moyasar_invoice_id', 'moyasar_payment_id']);
        });
    }
};
