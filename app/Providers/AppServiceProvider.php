<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Repository\Conversation\ConversationInterface;
use App\Repository\Conversation\ConversationRepository;
use App\Repository\cost\CostInterface;
use App\Repository\cost\CostRepository;
use App\Repository\freeAiModels\MainFreeAiModelInterface;
use App\Repository\freeAiModels\MainFreeAiModelRepository;
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
use App\Repository\user\AdminUserRepository;
use App\Repository\user\AdminUserRepositoryInterface;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}
