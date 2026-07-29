<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
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
