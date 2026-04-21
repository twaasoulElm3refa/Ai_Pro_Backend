<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Repository\Register\UserRepository;
use App\Repository\Register\UserRepositoryImpl;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, UserRepositoryImpl::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}
