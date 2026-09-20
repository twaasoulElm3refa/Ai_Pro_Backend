<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Repository\AiModels\AiModelsInterface;
use App\Repository\AiModels\AiModelsRepository;
use App\Repository\Conversation\ConversationInterface;
use App\Repository\Conversation\ConversationRepository;
use App\Repository\cost\CostInterface;
use App\Repository\cost\CostRepository;
use App\Repository\freeAiModels\MainFreeAiModelInterface;
use App\Repository\freeAiModels\MainFreeAiModelRepository;
use App\Repository\GeneralTool\GenrealToolInterface;
use App\Repository\GeneralTool\GenrealToolRepository;
use App\Repository\Messages\MessageInterface;
use App\Repository\Messages\MessageRepository;
use App\Repository\payment\AdminPaymentInterface;
use App\Repository\payment\AdminPaymentRepository;
use App\Repository\Register\UserRepository;
use App\Repository\Register\UserRepositoryImpl;
use App\Repository\tools\MainToolInterface;
use App\Repository\tools\MainToolRepository;
use App\Repository\tools\SubToolInterface;
use App\Repository\tools\SubToolRepository;
use App\Repository\Trends\TrendInterface;
use App\Repository\Trends\TrendRepository;
use App\Repository\user\AdminUserRepository;
use App\Repository\user\AdminUserRepositoryInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, UserRepositoryImpl::class);
        $this->app->bind(AdminUserRepositoryInterface::class, AdminUserRepository::class);
        $this->app->bind(MainToolInterface::class, MainToolRepository::class);
        $this->app->bind(MainFreeAiModelInterface::class, MainFreeAiModelRepository::class);
        $this->app->bind(SubToolInterface::class, SubToolRepository::class);
        $this->app->bind(AdminPaymentInterface::class, AdminPaymentRepository::class);
        $this->app->bind(ConversationInterface::class, ConversationRepository::class);
        $this->app->bind(MessageInterface::class, MessageRepository::class);
        $this->app->bind(CostInterface::class, CostRepository::class);
        $this->app->bind(GenrealToolInterface::class, GenrealToolRepository::class);
        $this->app->bind(AiModelsInterface::class, AiModelsRepository::class);
        $this->app->bind(TrendInterface::class, TrendRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        RateLimiter::for('free-ai-chat-send', fn (Request $request) => Limit::perMinute(
            max(1, (int) config('free_ai_chat.send_rate_per_minute'))
        )->by((string) $request->user()->getAuthIdentifier()));

        RateLimiter::for('free-ai-model-send', function (Request $request): Limit {
            $userId = (string) $request->user()->getAuthIdentifier();
            $slug = (string) $request->route('slug');
            $isMedia = config("model_catalogs.free_ai_tools.{$slug}") === 'general_media';

            return Limit::perMinute(max(1, (int) config(
                $isMedia ? 'free_ai_chat.media_send_rate_per_minute' : 'free_ai_chat.send_rate_per_minute'
            )))->by(($isMedia ? 'free-ai-media:' : 'free-ai-chat:').$userId);
        });

        RateLimiter::for('free-ai-conversation-create', fn (Request $request) => Limit::perMinute(
            max(1, (int) config('free_ai_chat.conversation_create_rate_per_minute'))
        )->by('free-ai-conversation:'.(string) $request->user()->getAuthIdentifier()));

        RateLimiter::for('trend-image-generation', function (Request $request): array {
            $subToolId = match ($request->route()?->getName()) {
                'trends.cup-lift', 'trends.cup-lifting-moment' => 28,
                'trends.locker-room' => 29,
                'trends.players-tunnel' => 30,
                'trends.paparazzi' => 31,
                default => null,
            };
            $userId = $request->user()?->getAuthIdentifier();

            if ($subToolId === null || $userId === null) {
                return [Limit::perMinute(max(
                    1,
                    (int) config('trends.rate_limits.fallback_requests_per_minute', 10)
                ))->by('trend-image:fallback:'.$request->ip())];
            }

            $key = 'trend-image:user:'.$userId;

            return [
                Limit::perMinute(max(
                    1,
                    (int) config('trends.rate_limits.requests_per_minute', 30)
                ))->by($key.':minute'),
                Limit::perSecond(max(
                    1,
                    (int) config('trends.rate_limits.burst_per_second', 5)
                ))->by($key.':burst'),
            ];
        });
    }
}
