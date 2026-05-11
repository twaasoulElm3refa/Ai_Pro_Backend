<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_conversation_id_idx', ['conversation_id']);
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_user_id_idx', ['user_id']);
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_sub_tool_id_idx', ['sub_tool_id']);
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_created_at_idx', ['created_at']);
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_total_tokens_idx', ['total_tokens']);
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_total_cost_idx', ['total_cost']);

        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_conversation_created_idx', ['conversation_id', 'created_at']);
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_user_created_idx', ['user_id', 'created_at']);
        $this->addIndexIfMissing('cost_loggers', 'cost_loggers_subtool_created_idx', ['sub_tool_id', 'created_at']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_subtool_created_idx');
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_user_created_idx');
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_conversation_created_idx');

        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_total_cost_idx');
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_total_tokens_idx');
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_created_at_idx');
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_sub_tool_id_idx');
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_user_id_idx');
        $this->dropIndexIfExists('cost_loggers', 'cost_loggers_conversation_id_idx');
    }

    private function addIndexIfMissing(string $tableName, string $indexName, array $columns): void
    {
        if ($this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (method_exists(Schema::getFacadeRoot(), 'hasIndex')) {
            return Schema::hasIndex($tableName, $indexName);
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);

            return ! empty($rows);
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$tableName}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
                [$tableName, $indexName]
            );

            return ! empty($rows);
        }

        return false;
    }
};
