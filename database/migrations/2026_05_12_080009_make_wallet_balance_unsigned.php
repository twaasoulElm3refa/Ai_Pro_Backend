<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('wallets')
            ->where('balance', '<', 0)
            ->update(['balance' => 0]);

        Schema::table('wallets', function (Blueprint $table) {
            $table->unsignedInteger('balance')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->integer('balance')->default(0)->change();
        });
    }
};
