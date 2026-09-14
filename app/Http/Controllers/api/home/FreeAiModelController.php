<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreeAiModelResource;
use App\Models\MainFreeAiModels;
use App\Models\ModelsConverstaions;
use App\Services\FreeAiModels\FreeAiChatException;
use App\Services\FreeAiModels\FreeAiChatService;
use App\Services\ModelCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FreeAiModelController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ModelCatalogService $catalogs) {}

    public function index()
    {
        $models = $this->activeModelsQuery()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->success(
            FreeAiModelResource::collection($models),
            'Free AI models fetched successfully.'
        );
    }

    public function show(string $slug)
    {
        $model = $this->activeModelsQuery()
            ->where('slug', $slug)
            ->first();

        if (! $model) {
            return $this->notFound('Free AI model not found.');
        }

        return $this->success(
            new FreeAiModelResource($model),
            'Free AI model fetched successfully.'
        );
    }

    public function storeConversation(Request $request, string $slug)
    {
        $model = $this->activeModelsQuery()
            ->where('slug', $slug)
            ->first();

        if (! $model) {
            return $this->notFound('Free AI model not found.');
        }

        $request->validate([
            'catalog_model_id' => ['nullable'],
            'provider_model_id' => ['nullable', 'string', 'max:255'],
            'catalog_operation' => ['nullable', 'string', 'max:64'],
        ]);

        $operation = $this->catalogOperationForRequest($model, $request);

        $selection = $request->filled('catalog_model_id')
            ? $this->requestedCatalogSelection($model, $request, $operation)
            : $this->defaultCatalogSelection($model, $request, $operation);

        $conversation = $request->user()->model_conversations()->create([
            'model_id' => $model->id,
            'uuid' => (string) Str::uuid(),
            'is_pinned' => false,
            'is_archived' => false,
            'catalog_operation' => $operation,
            ...($selection ?? []),
        ]);

        return $this->success(
            $this->conversationPayload($request, $conversation, $model),
            'Free AI model conversation created successfully.'
        );
    }

    public function conversations(Request $request, string $slug)
    {
        $model = $this->activeModelsQuery()
            ->where('slug', $slug)
            ->first();

        if (! $model) {
            return $this->notFound('Free AI model not found.');
        }

        $request->validate([
            'catalog_operation' => ['nullable', 'string', 'max:64'],
        ]);
        $operation = $this->catalogOperationForRequest($model, $request);

        $conversationsQuery = ModelsConverstaions::query()
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id)
            ->where('is_archived', false);

        $this->scopeConversationOperation($conversationsQuery, $model, $operation);

        $conversations = $conversationsQuery
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'uuid',
                'is_pinned',
                'created_at',
                'updated_at',
                'selected_model_source',
                'catalog_operation',
                'selected_model_id',
                'provider_model_id',
                'selected_model_name',
                'title',
            ])
            ->map(fn (ModelsConverstaions $conversation) => $this->conversationSummary($conversation, $model))
            ->values();

        return $this->success($conversations, 'Free AI model conversations fetched successfully.');
    }

    public function showConversation(Request $request, string $slug, string $uuid)
    {
        $model = $this->activeModelsQuery()
            ->where('slug', $slug)
            ->first();

        if (! $model) {
            return $this->notFound('Free AI model not found.');
        }

        $request->validate([
            'catalog_operation' => ['nullable', 'string', 'max:64'],
        ]);
        $operation = $this->catalogOperationForRequest($model, $request);

        $conversationQuery = ModelsConverstaions::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id);

        $this->scopeConversationOperation($conversationQuery, $model, $operation);
        $conversation = $conversationQuery->first();

        if (! $conversation) {
            return $this->notFound('Free AI model conversation not found.');
        }

        return $this->success(
            $this->conversationPayload($request, $conversation, $model),
            'Free AI model conversation fetched successfully.'
        );
    }

    public function messages(Request $request, string $slug, string $uuid)
    {
        $conversation = $this->ownedConversation($request, $slug, $uuid);
        if (! $conversation) {
            return $this->notFound('Free AI model conversation not found.');
        }

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->cursorPaginate(30, ['id', 'role', 'content', 'request_id', 'created_at']);

        return $this->success([
            'items' => collect($messages->items())->reverse()->values(),
            'next_cursor' => $messages->nextCursor()?->encode(),
        ]);
    }

    public function sendMessage(Request $request, string $slug, string $uuid, FreeAiChatService $chat)
    {
        $validated = $request->validate([
            'user_message' => ['required', 'string', 'max:5000'],
            'request_id' => ['required', 'uuid'],
        ]);
        $message = trim($validated['user_message']);
        if ($message === '') {
            throw ValidationException::withMessages(['user_message' => ['A message is required.']]);
        }

        $conversation = $this->ownedConversation($request, $slug, $uuid);
        if (! $conversation) {
            return $this->notFound('Free AI model conversation not found.');
        }

        $programmingLanguage = null;
        $translationOptions = null;
        if ($this->catalogSourceFor($conversation->model) === 'general_code') {
            $codeInput = $request->validate([
                'programming_language' => ['required', 'string', 'max:120'],
            ]);
            $programmingLanguage = trim($codeInput['programming_language']);
            if ($programmingLanguage === '') {
                throw ValidationException::withMessages([
                    'programming_language' => ['Select a programming language or framework.'],
                ]);
            }
        }

        if ($this->catalogSourceFor($conversation->model) === 'general_translation') {
            $languages = FreeAiChatService::TRANSLATION_LANGUAGES;
            $translationOptions = $request->validate([
                'source_language' => ['required', 'string', \Illuminate\Validation\Rule::in($languages)],
                'target_language' => ['required', 'string', \Illuminate\Validation\Rule::in($languages)],
            ]);
        }

        try {
            return $this->success($chat->send($conversation, $message, $validated['request_id'], $programmingLanguage, $translationOptions));
        } catch (FreeAiChatException $exception) {
            $response = $this->error($exception->getMessage(), $exception->statusCode);
            if ($exception->statusCode === 429 && $exception->retryAfter !== null) {
                $response->headers->set('Retry-After', $exception->retryAfter);
            }

            return $response;
        }
    }

    private function ownedConversation(Request $request, string $slug, string $uuid): ?ModelsConverstaions
    {
        $model = $this->activeModelsQuery()->where('slug', $slug)->first();
        if (! $model) return null;

        $operation = $this->catalogOperationForRequest($model, $request);
        $query = ModelsConverstaions::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id)
            ->where('is_archived', false);
        $this->scopeConversationOperation($query, $model, $operation);

        return $query->first();
    }

    public function updateConversationModel(Request $request, string $slug, string $uuid)
    {
        $model = $this->activeModelsQuery()
            ->where('slug', $slug)
            ->first();

        if (! $model) {
            return $this->notFound('Free AI model not found.');
        }

        $request->validate([
            'catalog_model_id' => ['required'],
            'provider_model_id' => ['nullable', 'string', 'max:255'],
            'catalog_operation' => ['nullable', 'string', 'max:64'],
        ]);
        $operation = $this->catalogOperationForRequest($model, $request);

        $conversationQuery = ModelsConverstaions::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id);

        $this->scopeConversationOperation($conversationQuery, $model, $operation);
        $conversation = $conversationQuery->first();

        if (! $conversation) {
            return $this->notFound('Free AI model conversation not found.');
        }

        $conversation->update($this->requestedCatalogSelection($model, $request, $operation));

        return $this->success(
            $this->conversationPayload($request, $conversation->fresh(), $model),
            'Conversation model updated successfully.'
        );
    }

    public function destroyConversation(Request $request, string $slug, string $uuid)
    {
        $model = $this->activeModelsQuery()
            ->where('slug', $slug)
            ->first();

        if (! $model) {
            return $this->notFound('Free AI model not found.');
        }

        $request->validate([
            'catalog_operation' => ['nullable', 'string', 'max:64'],
        ]);
        $operation = $this->catalogOperationForRequest($model, $request);

        $conversationQuery = ModelsConverstaions::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id);

        $this->scopeConversationOperation($conversationQuery, $model, $operation);
        $conversation = $conversationQuery->first();

        if (! $conversation) {
            return $this->notFound('Free AI model conversation not found.');
        }

        $conversation->delete();

        return $this->success(['uuid' => $uuid], 'Conversation deleted successfully.');
    }

    private function activeModelsQuery(): Builder
    {
        $locales = array_values(array_unique([app()->getLocale(), 'en']));

        return MainFreeAiModels::query()
            ->where('is_active', true)
            ->with([
                'translations' => fn ($query) => $query->whereIn('locale', $locales),
            ]);
    }

    private function catalogSourceFor(MainFreeAiModels $model): ?string
    {
        $source = config("model_catalogs.free_ai_tools.{$model->slug}");

        return is_string($source) && $source !== '' ? $source : null;
    }

    private function catalogOperationForRequest(MainFreeAiModels $model, Request $request): ?string
    {
        $source = $this->catalogSourceFor($model);

        if (! $source) {
            return null;
        }

        $operation = $request->input('catalog_operation', $request->query('catalog_operation'));

        try {
            return $this->catalogs->resolveOperation(
                $source,
                is_string($operation) ? $operation : null
            );
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'catalog_operation' => ['The selected catalog operation is not supported.'],
            ]);
        }
    }

    private function operationForConversation(
        ModelsConverstaions $conversation,
        MainFreeAiModels $model
    ): ?string {
        if (is_string($conversation->catalog_operation) && $conversation->catalog_operation !== '') {
            return $conversation->catalog_operation;
        }

        $source = $this->catalogSourceFor($model);

        return $source ? $this->catalogs->resolveOperation($source) : null;
    }

    private function scopeConversationOperation(
        Builder $query,
        MainFreeAiModels $model,
        ?string $operation
    ): void {
        $source = $this->catalogSourceFor($model);
        $defaultOperation = $source ? $this->catalogs->resolveOperation($source) : null;

        if ($operation === null) {
            $query->whereNull('catalog_operation');

            return;
        }

        if ($operation === $defaultOperation) {
            $query->where(function (Builder $query) use ($operation): void {
                $query->where('catalog_operation', $operation)
                    ->orWhereNull('catalog_operation');
            });

            return;
        }

        $query->where('catalog_operation', $operation);
    }

    private function defaultCatalogSelection(
        MainFreeAiModels $model,
        Request $request,
        ?string $operation
    ): ?array {
        $source = $this->catalogSourceFor($model);

        if (! $source) {
            return null;
        }

        try {
            $items = collect($this->catalogs->getModels($source, $operation)['items'] ?? [])
                ->sortBy(fn (array $item) => (int) ($item['sort_order'] ?? PHP_INT_MAX))
                ->values();

            $recentSelectionQuery = ModelsConverstaions::query()
                ->where('user_id', $request->user()->id)
                ->where('model_id', $model->id)
                ->whereNotNull('selected_model_source')
                ->whereNotNull('selected_model_name')
                ->latest('updated_at');

            $this->scopeConversationOperation($recentSelectionQuery, $model, $operation);

            $recentSelection = $recentSelectionQuery
                ->first([
                    'selected_model_source',
                    'catalog_operation',
                    'selected_model_id',
                    'provider_model_id',
                ]);

            $selected = $recentSelection && $recentSelection->selected_model_source === $source
                ? $items->first(fn (array $item) => $this->catalogBoolean($item['is_available'] ?? true)
                    && (
                        (string) ($item['id'] ?? '') === (string) $recentSelection->selected_model_id
                        || (
                            $recentSelection->provider_model_id
                            && (string) ($item['provider_model_id'] ?? '') === $recentSelection->provider_model_id
                        )
                    )
                )
                : null;

            $selected ??= $items->first(fn (array $item) => $this->catalogBoolean($item['is_available'] ?? true)
                && $this->catalogBoolean($item['is_recommended'] ?? false)
            ) ?? $items->first(fn (array $item) => $this->catalogBoolean($item['is_available'] ?? true));

            return is_array($selected) ? $this->catalogSelectionAttributes($source, $selected) : null;
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve the default Free AI catalog model.', [
                'free_ai_model_id' => $model->id,
                'source' => $source,
                'operation' => $operation,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function requestedCatalogSelection(
        MainFreeAiModels $model,
        Request $request,
        ?string $operation
    ): array {
        $source = $this->catalogSourceFor($model);

        if (! $source) {
            throw ValidationException::withMessages([
                'catalog_model_id' => ['This Free AI tool does not have a configured model catalog.'],
            ]);
        }

        $catalogModelId = (string) $request->input('catalog_model_id');
        $providerModelId = trim((string) $request->input('provider_model_id', ''));
        try {
            $items = $this->catalogs->getModels($source, $operation)['items'] ?? [];
        } catch (Throwable $exception) {
            Log::warning('Unable to validate the selected Free AI catalog model.', [
                'source' => $source,
                'operation' => $operation,
                'exception' => $exception::class,
            ]);
            throw new HttpResponseException($this->error('Model catalog is currently unavailable.', 502));
        }

        $selected = collect($items)->first(function (array $item) use ($catalogModelId, $providerModelId): bool {
            if ((string) ($item['id'] ?? '') !== $catalogModelId) {
                return false;
            }

            return $providerModelId === '' || (string) ($item['provider_model_id'] ?? '') === $providerModelId;
        });

        if (! is_array($selected) || ! $this->catalogBoolean($selected['is_available'] ?? true)) {
            throw ValidationException::withMessages([
                'catalog_model_id' => ['The selected catalog model is unavailable.'],
            ]);
        }

        return $this->catalogSelectionAttributes($source, $selected);
    }

    private function catalogSelectionAttributes(string $source, array $item): array
    {
        return [
            'selected_model_source' => $source,
            'selected_model_id' => is_numeric($item['id'] ?? null) ? (int) $item['id'] : null,
            'provider_model_id' => (string) ($item['provider_model_id'] ?? ''),
            'provider' => (string) ($item['provider'] ?? ''),
            'tool_key' => (string) ($item['tool_key'] ?? ''),
            'selected_model_name' => (string) ($item['name'] ?? 'AI Model'),
        ];
    }

    private function catalogBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function selectedModelPayload(ModelsConverstaions $conversation): ?array
    {
        if (! $conversation->selected_model_source && ! $conversation->selected_model_name) {
            return null;
        }

        return [
            'source' => $conversation->selected_model_source,
            'id' => $conversation->selected_model_id,
            'provider_model_id' => $conversation->provider_model_id,
            'name' => $conversation->selected_model_name,
        ];
    }

    private function conversationSummary(
        ModelsConverstaions $conversation,
        MainFreeAiModels $model
    ): array {
        return [
            'uuid' => $conversation->uuid,
            'title' => $conversation->title,
            'is_pinned' => (bool) $conversation->is_pinned,
            'created_at' => $conversation->created_at?->toISOString(),
            'updated_at' => $conversation->updated_at?->toISOString(),
            'catalog_operation' => $this->operationForConversation($conversation, $model),
            'selected_model' => $this->selectedModelPayload($conversation),
        ];
    }

    private function conversationPayload(
        Request $request,
        ModelsConverstaions $conversation,
        MainFreeAiModels $model
    ): array {
        return [
            'uuid' => $conversation->uuid,
            'title' => $conversation->title,
            'is_pinned' => (bool) $conversation->is_pinned,
            'is_archived' => (bool) $conversation->is_archived,
            'created_at' => $conversation->created_at?->toISOString(),
            'catalog_source' => $this->catalogSourceFor($model),
            'catalog_operation' => $this->operationForConversation($conversation, $model),
            'selected_model' => $this->selectedModelPayload($conversation),
            'model' => new FreeAiModelResource($model),
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
        ];
    }
}
