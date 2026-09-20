<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sub_tools')
            ->where('id', 28)
            ->update([
                'endpoint' => 'tasks/trends/cup-lift',
                'updated_at' => now(),
            ]);

        DB::table('sub_tools')
            ->where('id', 29)
            ->update([
                'endpoint' => 'tasks/trends/locker-room',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sub_tools')
            ->where('id', 28)
            ->where('endpoint', 'tasks/trends/cup-lift')
            ->update(['endpoint' => null, 'updated_at' => now()]);

        DB::table('sub_tools')
            ->where('id', 29)
            ->where('endpoint', 'tasks/trends/locker-room')
            ->update(['endpoint' => null, 'updated_at' => now()]);
    }
};
