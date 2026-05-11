<?php

namespace App\Repository\cost;

use App\Models\CostLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CostRepository implements CostInterface
{
    private const ALLOWED_SORT_BY = [
        'id',
        'created_at',
        'total_tokens',
        'input_tokens',
        'output_tokens',
        'total_cost',
        'input_cost',
        'output_cost',
        'conversation_total_tokens',
    ];

    public function paginate(array $filters): array
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $filters);

        $summary = $this->buildSummary(clone $query);

        $this->applyListSelect($query);
        $this->applySorting($query, $filters);

        $paginator = $query
            ->paginate($this->normalizePerPage($filters))
            ->appends($filters)
            ->through(fn (CostLogger $log) => $this->mapItem($log));

        return [
            'data' => $paginator,
            'summary' => $summary,
        ];
    }

    public function today(array $filters): array
    {
        $todayFilters = $filters;
        $todayFilters['today'] = true;

        return $this->paginate($todayFilters);
    }

    public function find(int $id): mixed
    {
        $cost = CostLogger::query()
            ->with([
                'user:id,name,email',
                'conversation:id,uuid,user_id,sub_tool_id,created_at',
                'conversation.user:id,name,email',
                'conversation.subTool:id,name,slug',
                'subTool:id,name,slug',
            ])
            ->find($id);

        if (! $cost) {
            return null;
        }

        $conversationTotalTokens = 0;
        if ($cost->conversation_id) {
            $conversationTotalTokens = (int) CostLogger::query()
                ->where('conversation_id', $cost->conversation_id)
                ->sum('total_tokens');
        }

        $cost->setAttribute('conversation_total_tokens', $conversationTotalTokens);
        $cost->setAttribute('is_limited', $conversationTotalTokens >= $this->conversationTokenLimit());

        $conversationSummary = $this->conversationSummary($cost->conversation_id);
        $cost->setAttribute('conversation_cost_summary', $conversationSummary);

        return $cost;
    }

    public function destroy(int $id): bool
    {
        $cost = CostLogger::query()->find($id);

        if (! $cost) {
            return false;
        }

        return (bool) $cost->delete();
    }

    private function baseQuery(): Builder
    {
        $conversationTotals = CostLogger::query()
            ->select('conversation_id')
            ->selectRaw('SUM(total_tokens) as conversation_total_tokens')
            ->whereNotNull('conversation_id')
            ->groupBy('conversation_id');

        return CostLogger::query()
            ->leftJoinSub($conversationTotals, 'conversation_totals', function ($join) {
                $join->on('conversation_totals.conversation_id', '=', 'cost_loggers.conversation_id');
            })
            ->with($this->relations());
    }

    private function applyListSelect(Builder $query): void
    {
        $limit = $this->conversationTokenLimit();

        $query->select('cost_loggers.*')
            ->selectRaw('COALESCE(conversation_totals.conversation_total_tokens, 0) as conversation_total_tokens')
            ->selectRaw(
                'CASE WHEN COALESCE(conversation_totals.conversation_total_tokens, 0) >= ? THEN 1 ELSE 0 END as is_limited',
                [$limit]
            );
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                if (is_numeric($search)) {
                    $nested->orWhere('cost_loggers.id', (int) $search);
                }

                $nested->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });

                $nested->orWhereHas('conversation', function (Builder $conversationQuery) use ($search): void {
                    $conversationQuery->where('uuid', 'like', "%{$search}%");
                });
            });
        }

        if (! empty($filters['user_id'])) {
            $query->where('cost_loggers.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['conversation_id'])) {
            $query->where('cost_loggers.conversation_id', (int) $filters['conversation_id']);
        }

        if (! empty($filters['conversation_uuid'])) {
            $uuid = trim((string) $filters['conversation_uuid']);
            $query->whereHas('conversation', function (Builder $conversationQuery) use ($uuid): void {
                $conversationQuery->where('uuid', 'like', "%{$uuid}%");
            });
        }

        if (! empty($filters['sub_tool_id'])) {
            $query->where('cost_loggers.sub_tool_id', (int) $filters['sub_tool_id']);
        }

        $today = $this->toNullableBoolean($filters['today'] ?? null);
        if ($today === true) {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
            $query->whereBetween('cost_loggers.created_at', [$start, $end]);
        } else {
            $dateFrom = $filters['date_from'] ?? null;
            $dateTo = $filters['date_to'] ?? null;

            if (! empty($dateFrom) && ! empty($dateTo)) {
                $start = Carbon::parse((string) $dateFrom)->startOfDay();
                $end = Carbon::parse((string) $dateTo)->endOfDay();
                $query->whereBetween('cost_loggers.created_at', [$start, $end]);
            } elseif (! empty($dateFrom)) {
                $start = Carbon::parse((string) $dateFrom)->startOfDay();
                $query->where('cost_loggers.created_at', '>=', $start);
            } elseif (! empty($dateTo)) {
                $end = Carbon::parse((string) $dateTo)->endOfDay();
                $query->where('cost_loggers.created_at', '<=', $end);
            }
        }

        if (array_key_exists('min_total_tokens', $filters) && $filters['min_total_tokens'] !== null) {
            $query->where('cost_loggers.total_tokens', '>=', (int) $filters['min_total_tokens']);
        }

        if (array_key_exists('max_total_tokens', $filters) && $filters['max_total_tokens'] !== null) {
            $query->where('cost_loggers.total_tokens', '<=', (int) $filters['max_total_tokens']);
        }

        if (array_key_exists('min_total_cost', $filters) && $filters['min_total_cost'] !== null) {
            $query->where('cost_loggers.total_cost', '>=', (float) $filters['min_total_cost']);
        }

        if (array_key_exists('max_total_cost', $filters) && $filters['max_total_cost'] !== null) {
            $query->where('cost_loggers.total_cost', '<=', (float) $filters['max_total_cost']);
        }

        $limited = $this->toNullableBoolean($filters['limited'] ?? null);
        $tokenLimit = $this->conversationTokenLimit();

        if ($limited === true) {
            $query->whereRaw('COALESCE(conversation_totals.conversation_total_tokens, 0) >= ?', [$tokenLimit]);
        } elseif ($limited === false) {
            $query->whereRaw('COALESCE(conversation_totals.conversation_total_tokens, 0) < ?', [$tokenLimit]);
        }
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');

        if (! in_array($sortBy, self::ALLOWED_SORT_BY, true)) {
            $sortBy = 'created_at';
        }

        $direction = strtolower((string) ($filters['sort_direction'] ?? 'desc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        if ($sortBy === 'conversation_total_tokens') {
            $query->orderByRaw("COALESCE(conversation_totals.conversation_total_tokens, 0) {$direction}");

            return;
        }

        $query->orderBy("cost_loggers.{$sortBy}", $direction);
    }

    private function buildSummary(Builder $query): array
    {
        $limit = $this->conversationTokenLimit();

        $query->setEagerLoads([]);

        $row = $query
            ->reorder()
            ->selectRaw('COUNT(cost_loggers.id) as logs_count')
            ->selectRaw('COALESCE(SUM(cost_loggers.total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(cost_loggers.input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(cost_loggers.output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(cost_loggers.total_cost), 0) as total_cost')
            ->selectRaw('COALESCE(SUM(cost_loggers.input_cost), 0) as input_cost')
            ->selectRaw('COALESCE(SUM(cost_loggers.output_cost), 0) as output_cost')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN COALESCE(conversation_totals.conversation_total_tokens, 0) >= ? THEN cost_loggers.conversation_id END) as limited_conversations_count',
                [$limit]
            )
            ->first();

        return [
            'logs_count' => (int) ($row->logs_count ?? 0),
            'total_tokens' => (int) ($row->total_tokens ?? 0),
            'input_tokens' => (int) ($row->input_tokens ?? 0),
            'output_tokens' => (int) ($row->output_tokens ?? 0),
            'total_cost' => (float) ($row->total_cost ?? 0),
            'input_cost' => (float) ($row->input_cost ?? 0),
            'output_cost' => (float) ($row->output_cost ?? 0),
            'limited_conversations_count' => (int) ($row->limited_conversations_count ?? 0),
        ];
    }

    private function conversationSummary(?int $conversationId): array
    {
        if (! $conversationId) {
            return [
                'total_tokens' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_cost' => 0,
                'input_cost' => 0,
                'output_cost' => 0,
                'logs_count' => 0,
            ];
        }

        $summary = CostLogger::query()
            ->where('conversation_id', $conversationId)
            ->selectRaw('COUNT(id) as logs_count')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost')
            ->selectRaw('COALESCE(SUM(input_cost), 0) as input_cost')
            ->selectRaw('COALESCE(SUM(output_cost), 0) as output_cost')
            ->first();

        return [
            'total_tokens' => (int) ($summary->total_tokens ?? 0),
            'input_tokens' => (int) ($summary->input_tokens ?? 0),
            'output_tokens' => (int) ($summary->output_tokens ?? 0),
            'total_cost' => (float) ($summary->total_cost ?? 0),
            'input_cost' => (float) ($summary->input_cost ?? 0),
            'output_cost' => (float) ($summary->output_cost ?? 0),
            'logs_count' => (int) ($summary->logs_count ?? 0),
        ];
    }

    private function normalizePerPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 10);

        if ($perPage < 1) {
            $perPage = 10;
        }

        return min($perPage, 100);
    }

    private function mapItem(CostLogger $log): CostLogger
    {
        $conversationTotalTokens = (int) ($log->conversation_total_tokens ?? 0);

        $log->setAttribute('conversation_total_tokens', $conversationTotalTokens);
        $log->setAttribute('is_limited', $conversationTotalTokens >= $this->conversationTokenLimit());

        return $log;
    }

    private function relations(): array
    {
        return [
            'user:id,name,email',
            'conversation:id,uuid,user_id,sub_tool_id,created_at',
            'subTool:id,name,slug',
        ];
    }

    private function toNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized;
    }

    private function conversationTokenLimit(): int
    {
        return (int) config('services.aiarabic.conversation_token_limit', 7000);
    }
}
