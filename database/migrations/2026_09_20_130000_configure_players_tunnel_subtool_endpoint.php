<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sub_tools')
            ->where('id', 30)
            ->update([
                'endpoint' => 'tasks/trends/players-tunnel',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sub_tools')
            ->where('id', 30)
            ->where('endpoint', 'tasks/trends/players-tunnel')
            ->update([
                'endpoint' => null,
                'updated_at' => now(),
            ]);
    }
};
