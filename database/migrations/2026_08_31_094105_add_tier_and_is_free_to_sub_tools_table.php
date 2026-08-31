<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            $table->string('tier')->nullable();
            $table->boolean('is_free')->default(false)->after('tier');
        });
    }

    public function down(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            $table->dropColumn([
                'tier',
                'is_free',
            ]);
        });
    }
};
