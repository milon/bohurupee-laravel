<?php

namespace Milon\Bohurupee;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;

class BohurupeeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bohurupee.php', 'bohurupee');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/bohurupee.php' => config_path('bohurupee.php'),
        ], 'bohurupee-config');

        if (! $this->app['config']->get('bohurupee.enabled')) {
            return;
        }

        if ($this->app->environment('production')) {
            throw ProductionForbiddenException::make();
        }

        $this->app->extend(Factory::class, function (Factory $factory, $app) {
            return new BohurupeeFactory($factory, $app);
        });
    }
}
