<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Throwable;

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
        if ($this->app->environment('mock-defense')) {
            Vite::useHotFile(storage_path('vite.hot'));
        }

        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $models) use ($event): void {
                $model = $models[0] ?? null;
                if ($model && ! $model instanceof AuditLog) AuditLogger::record($model, $event);
            });
        }

        RateLimiter::for('officer-location', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $valid): bool => $valid
                && ($token->name !== User::MOBILE_TOKEN_NAME
                    || $token->created_at->gt(now()->subDays((int) app(Settings::class)->get('security.api_token_days'))))
        );

        try {
            if (Schema::hasTable('settings')) {
                $runtime = Setting::whereIn('key', ['general.organization_name', 'general.timezone', 'security.session_timeout'])->pluck('value', 'key');
                config([
                    'app.name' => $runtime['general.organization_name'] ?? config('app.name'),
                    'app.timezone' => $runtime['general.timezone'] ?? config('app.timezone'),
                    'session.lifetime' => (int) ($runtime['security.session_timeout'] ?? config('session.lifetime')),
                ]);
                date_default_timezone_set(config('app.timezone'));
            }
        } catch (Throwable) {
            // The database may be unavailable while installing or migrating.
        }
    }
}
