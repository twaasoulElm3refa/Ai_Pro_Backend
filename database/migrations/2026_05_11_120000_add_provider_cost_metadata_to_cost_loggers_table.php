<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_loggers', function (Blueprint $table) {
            if (! Schema::hasColumn('cost_loggers', 'web_search_cost')) {
                $table->decimal('web_search_cost', 12, 6)->default(0)->after('output_cost');
            }

            if (! Schema::hasColumn('cost_loggers', 'currency')) {
                $table->string('currency', 10)->default('USD')->after('total_cost');
            }

            if (! Schema::hasColumn('cost_loggers', 'provider_request_id')) {
                $table->string('provider_request_id')->nullable()->after('currency');
            }

            if (! Schema::hasColumn('cost_loggers', 'model_key')) {
                $table->string('model_key')->nullable()->after('provider_request_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cost_loggers', function (Blueprint $table) {
            if (Schema::hasColumn('cost_loggers', 'model_key')) {
                $table->dropColumn('model_key');
            }

            if (Schema::hasColumn('cost_loggers', 'provider_request_id')) {
                $table->dropColumn('provider_request_id');
            }

            if (Schema::hasColumn('cost_loggers', 'currency')) {
                $table->dropColumn('currency');
            }

            if (Schema::hasColumn('cost_loggers', 'web_search_cost')) {
                $table->dropColumn('web_search_cost');
            }
        });
    }
};
