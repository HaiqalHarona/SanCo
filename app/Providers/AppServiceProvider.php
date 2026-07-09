<?php

namespace App\Providers;

use App\Services\UserService;
use App\Services\FriendshipService;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UserService::class);
        $this->app->singleton(FriendshipService::class);
        $this->app->singleton(ConversationService::class);
        $this->app->singleton(MessageService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
