<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sub_tools')
            ->where('id', 31)
            ->update([
                'endpoint' => 'tasks/trends/paparazzi',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sub_tools')
            ->where('id', 31)
            ->where('endpoint', 'tasks/trends/paparazzi')
            ->update([
                'endpoint' => null,
                'updated_at' => now(),
            ]);
    }
};
