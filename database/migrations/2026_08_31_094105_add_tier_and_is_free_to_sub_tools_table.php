<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            $table->string('tier')->nullable()->after('deleted_at');
            $table->boolean('is_free')->default(false)->after('tier');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sub_tools', 'is_free')) {
            Schema::table('sub_tools', function (Blueprint $table) {
                $table->dropColumn('is_free');
            });
        }

        if (Schema::hasColumn('sub_tools', 'tier')) {
            Schema::table('sub_tools', function (Blueprint $table) {
                $table->dropColumn('tier');
            });
        }
    }
};
