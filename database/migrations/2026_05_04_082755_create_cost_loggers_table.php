<?php

use App\Models\SubTools;
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
        Schema::create('cost_loggers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class,'user_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(SubTools::class,'sub_tool_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_cost', 8, 2)->default(0);
            $table->integer('total_tokens')->default(0);
            $table->integer('input_tokens')->default(0);
            $table->decimal('input_cost', 8, 2)->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('output_cost', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_loggers');
    }
};
