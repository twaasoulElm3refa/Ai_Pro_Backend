<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->change();
            $table->unsignedBigInteger('models_cost_logger_id')->nullable()->unique();
            $table->bigInteger('payback_before')->nullable();
            $table->bigInteger('payback_after')->nullable();
        });
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->foreign('models_cost_logger_id')
                    ->references('id')->on('models_cost_loggers')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Usage debits have no payment and cannot exist in the previous schema.
        DB::table('wallet_transactions')->whereNull('payment_id')->delete();

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropForeign(['models_cost_logger_id']);
            });
        }
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('wallet_transactions_models_cost_logger_id_unique');
        });
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('models_cost_logger_id');
            $table->dropColumn(['payback_before', 'payback_after']);
        });
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable(false)->change();
        });
    }
};
