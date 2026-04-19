<?php

use App\Models\brands;
use App\Models\Categories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Categories::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(brands::class)->nullable()->constrained()->nullOnDelete();
            $table->string('name')->unique();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('slug')->nullable();
            $table->string('sku')->unique()->nullable();
            $table->decimal('tax', 8, 2)->default(0);
            $table->boolean('is_active')->default(true)->nullable();
            $table->decimal('price', 8, 2);
            $table->boolean('is_featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('return_policy')->default('4 days')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
