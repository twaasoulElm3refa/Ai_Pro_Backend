<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
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
        $this->app->bind(SubToolInterface::class, SubToolRepository::class);
        $this->app->bind(AdminPaymentInterface::class, AdminPaymentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}
