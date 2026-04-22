<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Repository\Register\UserRepository;
use App\Repository\Register\UserRepositoryImpl;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}
