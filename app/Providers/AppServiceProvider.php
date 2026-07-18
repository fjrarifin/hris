<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('career-read', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('career-apply-ip', fn (Request $request) => [
            Limit::perMinute(5)->by('career-ip-minute:'.$request->ip()),
            Limit::perDay(30)->by('career-ip-day:'.$request->ip()),
        ]);
        RateLimiter::for('career-apply-identity', function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email')));
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone')) ?: '';
            if (str_starts_with($phone, '0')) {
                $phone = '62'.substr($phone, 1);
            }
            $slug = (string) $request->route('slug');

            return [
                Limit::perDay(3)->by('career-email:'.$slug.':'.hash('sha256', $email)),
                Limit::perDay(3)->by('career-phone:'.$slug.':'.hash('sha256', $phone)),
            ];
        });

        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $idleMinutes = $token->name === 'hris-mobile'
                ? (int) config('sanctum.mobile_idle_expiration', 3 * 24 * 60)
                : (int) config('sanctum.idle_expiration', 30);
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
