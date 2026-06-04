<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $idleMinutes = (int) config('sanctum.idle_expiration', 7 * 24 * 60);
            $lastActivity = $token->last_used_at ?? $token->created_at;

            return $idleMinutes <= 0 || $lastActivity?->gt(now()->subMinutes($idleMinutes));
        });

        Gate::define('viewLogViewer', function (?User $user) {
            return $user && in_array((int) $user->level, [0, 1, 2], true);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
