<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('free_credit_claims', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(User::class, 'user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('email')->nullable()->index();
            $table->ipAddress('ip_address')->nullable()->index();
            $table->string('device_fingerprint')->nullable()->index();
            $table->text('user_agent')->nullable();

            $table->integer('amount')->default(10);
            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('free_credit_claims');
    }
};
