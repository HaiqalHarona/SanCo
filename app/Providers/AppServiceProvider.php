<?php

namespace App\Providers;

use App\Services\UserService;
use App\Services\FriendshipService;
use App\Services\ConversationService;
use App\Services\MessageService;
use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Rate limiter for OAuth login endpoints — 5 attempts per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for authenticated API endpoints — 60 requests per minute per user (fallback to IP)
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->_id)
                : Limit::perMinute(60)->by($request->ip());
        });
    }
}
