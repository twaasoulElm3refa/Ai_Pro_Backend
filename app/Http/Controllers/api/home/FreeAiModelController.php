<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreeAiModelResource;
use App\Models\MainFreeAiModels;
use App\Models\ModelsConverstaions;
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
        ]);

        $selection = $request->filled('catalog_model_id')
            ? $this->requestedCatalogSelection($model, $request)
            : $this->defaultCatalogSelection($model, $request);

        $conversation = $request->user()->model_conversations()->create([
            'model_id' => $model->id,
            'uuid' => (string) Str::uuid(),
            'is_pinned' => false,
            'is_archived' => false,
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

        $conversations = ModelsConverstaions::query()
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id)
            ->where('is_archived', false)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'uuid',
                'is_pinned',
                'created_at',
                'updated_at',
                'selected_model_source',
                'selected_model_catalog_id',
                'selected_provider_model_id',
                'selected_model_name',
            ])
            ->map(fn (ModelsConverstaions $conversation) => $this->conversationSummary($conversation))
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

        $conversation = ModelsConverstaions::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id)
            ->first();

        if (! $conversation) {
            return $this->notFound('Free AI model conversation not found.');
        }

        return $this->success(
            $this->conversationPayload($request, $conversation, $model),
            'Free AI model conversation fetched successfully.'
        );
    }

    public function updateConversationModel(Request $request, string $slug, string $uuid)
    {
        $model = $this->activeModelsQuery()
            ->where('slug', $slug)
            ->first();

        if (! $model) {
            return $this->notFound('Free AI model not found.');
        }

        $conversation = ModelsConverstaions::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id)
            ->first();

        if (! $conversation) {
            return $this->notFound('Free AI model conversation not found.');
        }

        $request->validate([
            'catalog_model_id' => ['required'],
            'provider_model_id' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation->update($this->requestedCatalogSelection($model, $request));

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

        $conversation = ModelsConverstaions::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('model_id', $model->id)
            ->first();

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

    private function defaultCatalogSelection(MainFreeAiModels $model, Request $request): ?array
    {
        $source = $this->catalogSourceFor($model);

        if (! $source) {
            return null;
        }

        try {
            $items = collect($this->catalogs->getModels($source)['items'] ?? [])
                ->sortBy(fn (array $item) => (int) ($item['sort_order'] ?? PHP_INT_MAX))
                ->values();

            $recentSelection = ModelsConverstaions::query()
                ->where('user_id', $request->user()->id)
                ->where('model_id', $model->id)
                ->whereNotNull('selected_model_source')
                ->whereNotNull('selected_model_name')
                ->latest('updated_at')
                ->first([
                    'selected_model_source',
                    'selected_model_catalog_id',
                    'selected_provider_model_id',
                ]);

            $selected = $recentSelection && $recentSelection->selected_model_source === $source
                ? $items->first(fn (array $item) => $this->catalogBoolean($item['is_available'] ?? true)
                    && (
                        (string) ($item['id'] ?? '') === (string) $recentSelection->selected_model_catalog_id
                        || (
                            $recentSelection->selected_provider_model_id
                            && (string) ($item['provider_model_id'] ?? '') === $recentSelection->selected_provider_model_id
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
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function requestedCatalogSelection(MainFreeAiModels $model, Request $request): array
    {
        $source = $this->catalogSourceFor($model);

        if (! $source) {
            throw ValidationException::withMessages([
                'catalog_model_id' => ['This Free AI tool does not have a configured model catalog.'],
            ]);
        }

        $catalogModelId = (string) $request->input('catalog_model_id');
        $providerModelId = trim((string) $request->input('provider_model_id', ''));
        try {
            $items = $this->catalogs->getModels($source)['items'] ?? [];
        } catch (Throwable $exception) {
            Log::warning('Unable to validate the selected Free AI catalog model.', [
                'source' => $source,
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
            'selected_model_catalog_id' => is_numeric($item['id'] ?? null) ? (int) $item['id'] : null,
            'selected_provider_model_id' => (string) ($item['provider_model_id'] ?? ''),
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
            'id' => $conversation->selected_model_catalog_id,
            'provider_model_id' => $conversation->selected_provider_model_id,
            'name' => $conversation->selected_model_name,
        ];
    }

    private function conversationSummary(ModelsConverstaions $conversation): array
    {
        return [
            'uuid' => $conversation->uuid,
            'title' => null,
            'is_pinned' => (bool) $conversation->is_pinned,
            'created_at' => $conversation->created_at?->toISOString(),
            'updated_at' => $conversation->updated_at?->toISOString(),
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
            'is_pinned' => (bool) $conversation->is_pinned,
            'is_archived' => (bool) $conversation->is_archived,
            'created_at' => $conversation->created_at?->toISOString(),
            'catalog_source' => $this->catalogSourceFor($model),
            'selected_model' => $this->selectedModelPayload($conversation),
            'model' => new FreeAiModelResource($model),
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
        ];
    }
}
