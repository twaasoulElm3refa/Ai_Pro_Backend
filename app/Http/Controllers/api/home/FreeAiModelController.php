<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreeAiModelResource;
use App\Models\MainFreeAiModels;
use App\Models\ModelsConverstaions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FreeAiModelController extends Controller
{
    use ApiResponse;

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

        $conversation = $request->user()->model_conversations()->create([
            'model_id' => $model->id,
            'uuid' => (string) Str::uuid(),
            'is_pinned' => false,
            'is_archived' => false,
        ]);

        return $this->success(
            $this->conversationPayload($request, $conversation, $model),
            'Free AI model conversation created successfully.'
        );
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

    private function activeModelsQuery(): Builder
    {
        $locales = array_values(array_unique([app()->getLocale(), 'en']));

        return MainFreeAiModels::query()
            ->where('is_active', true)
            ->with([
                'translations' => fn ($query) => $query->whereIn('locale', $locales),
            ]);
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
            'model' => new FreeAiModelResource($model),
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
        ];
    }
}
