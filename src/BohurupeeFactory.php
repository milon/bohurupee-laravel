<?php

namespace Milon\Bohurupee;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Contracts\Provider;

class BohurupeeFactory implements Factory
{
    /**
     * @var array<string, Provider>
     */
    private array $drivers = [];

    public function __construct(
        private readonly Factory $inner,
        private readonly Application $app,
    ) {}

    public function driver($driver = null)
    {
        $name = $driver ?: $this->defaultDriver();
        if (! $this->shouldWrap($name)) {
            return $this->inner->driver($name);
        }

        return $this->drivers[$name] ??= $this->create($name);
    }

    public function shouldWrap(string $driver): bool
    {
        $except = $this->app['config']->get('bohurupee.except', []);
        if (in_array($driver, $except, true)) {
            return false;
        }

        $only = $this->app['config']->get('bohurupee.drivers', []);
        if ($only === []) {
            return true;
        }

        return in_array($driver, $only, true);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->inner->{$method}(...$parameters);
    }

    private function defaultDriver(): string
    {
        if (method_exists($this->inner, 'getDefaultDriver')) {
            return $this->inner->getDefaultDriver();
        }

        return 'github';
    }

    private function create(string $driver): BohurupeeProvider
    {
        $config = $this->app['config']->get('services.'.$driver, []);
        $request = $this->app['request'];

        return new BohurupeeProvider(
            $request,
            $config['client_id'] ?? 'bohurupee-'.$driver,
            $config['client_secret'] ?? 'bohurupee',
            $this->formatRedirectUrl($config['redirect'] ?? null, $driver),
            $driver,
            rtrim((string) $this->app['config']->get('bohurupee.url', 'http://127.0.0.1:4190'), '/'),
        );
    }

    /**
     * Resolve a relative callback against the current request, as Socialite does,
     * so the session cookie set at redirect time is still sent on the callback.
     */
    private function formatRedirectUrl(mixed $redirect, string $driver): string
    {
        $redirect = value($redirect) ?: '/auth/'.$driver.'/callback';

        return str_starts_with($redirect, '/')
            ? $this->app['url']->to($redirect)
            : $redirect;
    }
}
