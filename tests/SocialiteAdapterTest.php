<?php

namespace Milon\Bohurupee\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use Laravel\Socialite\Two\GithubProvider;
use Milon\Bohurupee\BohurupeeFactory;
use Milon\Bohurupee\BohurupeeProvider;
use Milon\Bohurupee\BohurupeeServiceProvider;
use Milon\Bohurupee\OAuthErrorException;
use Milon\Bohurupee\ProductionForbiddenException;
use Milon\Bohurupee\UserMapper;
use Orchestra\Testbench\TestCase;

class SocialiteAdapterTest extends TestCase
{
    protected bool $enableBohurupee = true;

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteServiceProvider::class,
            BohurupeeServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.env', 'testing');
        $app['config']->set('bohurupee.enabled', $this->enableBohurupee);
        $app['config']->set('bohurupee.url', 'http://127.0.0.1:4190');
        $app['config']->set('bohurupee.public_url', 'http://127.0.0.1:4190');
        $app['config']->set('bohurupee.drivers', []);
        $app['config']->set('bohurupee.except', []);
        $app['config']->set('services.google', [
            'client_id' => 'bohurupee-google',
            'client_secret' => 'bohurupee',
            'redirect' => 'http://localhost/auth/google/callback',
        ]);
        $app['config']->set('services.github', [
            'client_id' => 'bohurupee-github',
            'client_secret' => 'bohurupee',
            'redirect' => 'http://localhost/auth/github/callback',
        ]);
    }

    public function test_factory_is_decorated_when_enabled(): void
    {
        $this->assertInstanceOf(BohurupeeFactory::class, $this->app->make(Factory::class));
        $this->assertInstanceOf(BohurupeeProvider::class, Socialite::driver('google'));
        $this->assertInstanceOf(BohurupeeProvider::class, Socialite::driver('github'));
        $this->assertInstanceOf(BohurupeeProvider::class, Socialite::driver('acme'));
    }

    public function test_redirect_targets_local_bohurupee(): void
    {
        $target = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        $this->assertStringStartsWith('http://127.0.0.1:4190/google/authorize?', $target);
        $this->assertStringNotContainsString('accounts.google.com', $target);
        $this->assertStringContainsString('client_id=bohurupee-google', $target);
        $this->assertStringContainsString('scope=openid+profile+email', $target);
    }

    public function test_public_url_used_for_authorize_only(): void
    {
        $this->app['config']->set('bohurupee.url', 'http://bohurupee:4190');
        $this->app['config']->set('bohurupee.public_url', 'http://127.0.0.1:14190');
        $this->app->forgetInstance(Factory::class);
        Socialite::clearResolvedInstances();

        $provider = Socialite::driver('google');
        $this->assertInstanceOf(BohurupeeProvider::class, $provider);
        $this->assertSame('http://bohurupee:4190', $provider->getBaseUrl());
        $this->assertSame('http://127.0.0.1:14190', $provider->getPublicUrl());

        $target = $provider->stateless()->redirect()->getTargetUrl();
        $this->assertStringStartsWith('http://127.0.0.1:14190/google/authorize?', $target);
    }

    public function test_relative_redirect_follows_the_request_host(): void
    {
        $this->app['config']->set('services.google.redirect', '/auth/google/callback');

        foreach (['http://localhost:8000', 'http://127.0.0.1:8000'] as $root) {
            $this->app['url']->forceRootUrl($root);
            $this->app->forgetInstance(Factory::class);
            Socialite::clearResolvedInstances();

            $target = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

            $this->assertStringContainsString(
                'redirect_uri='.urlencode($root.'/auth/google/callback'),
                $target
            );
        }
    }

    public function test_user_mapping_and_set_raw_from_userinfo(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'tok_test',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'alice',
                'sub' => 'alice',
                'email' => 'alice@example.test',
                'email_verified' => true,
                'name' => 'Alice',
                'nickname' => 'alice',
                'avatar' => 'https://example.test/alice.png',
                'login' => 'alice',
            ])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $this->app['request']->merge(['code' => 'auth-code']);

        $provider = Socialite::driver('github')->stateless();
        $provider->setHttpClient(new Client(['handler' => $stack]));
        $user = $provider->user();

        $this->assertSame('alice', $user->getId());
        $this->assertSame('alice@example.test', $user->getEmail());
        $this->assertSame('Alice', $user->getName());
        $this->assertSame('alice', $user->getNickname());
        $this->assertSame('https://example.test/alice.png', $user->getAvatar());
        $this->assertSame('alice', $user->getRaw()['login']);
        $this->assertTrue($user->getRaw()['email_verified']);

        $this->assertCount(2, $history);
        $tokenUrl = (string) $history[0]['request']->getUri();
        $userinfoUrl = (string) $history[1]['request']->getUri();
        $this->assertSame('http://127.0.0.1:4190/github/token', $tokenUrl);
        $this->assertSame('http://127.0.0.1:4190/github/userinfo', $userinfoUrl);
        $this->assertStringNotContainsString('github.com', $tokenUrl.$userinfoUrl);
        $this->assertStringNotContainsString('google.com', $tokenUrl.$userinfoUrl);
    }

    public function test_allowlist_wraps_only_listed_drivers(): void
    {
        $this->app['config']->set('bohurupee.drivers', ['google']);
        $factory = $this->app->make(Factory::class);

        $this->assertTrue($factory->shouldWrap('google'));
        $this->assertFalse($factory->shouldWrap('github'));
        $this->assertInstanceOf(BohurupeeProvider::class, $factory->driver('google'));
        $this->assertInstanceOf(GithubProvider::class, $factory->driver('github'));
    }

    public function test_except_leaves_listed_drivers_native(): void
    {
        $this->app['config']->set('bohurupee.except', ['github']);
        $factory = $this->app->make(Factory::class);

        $this->assertTrue($factory->shouldWrap('google'));
        $this->assertFalse($factory->shouldWrap('github'));
        $this->assertInstanceOf(GithubProvider::class, $factory->driver('github'));
    }

    public function test_facebook_picture_and_github_avatar_url(): void
    {
        $facebook = UserMapper::map([
            'id' => 'fb-1',
            'email' => 'carol@example.test',
            'name' => 'Carol',
            'picture' => ['data' => ['url' => 'https://graph.facebook.test/carol.jpg']],
        ]);
        $this->assertSame('https://graph.facebook.test/carol.jpg', $facebook->getAvatar());

        $github = UserMapper::map([
            'id' => '42',
            'login' => 'octocat',
            'name' => 'The Octocat',
            'email' => 'octocat@github.test',
            'avatar_url' => 'https://avatars.github.test/u/1',
        ]);
        $this->assertSame('octocat', $github->getNickname());
        $this->assertSame('https://avatars.github.test/u/1', $github->getAvatar());
        $this->assertSame('42', $github->getId());
    }

    public function test_deny_throws_oauth_error_exception(): void
    {
        $this->app['request']->merge([
            'error' => 'access_denied',
            'error_description' => 'the user denied the request',
            'state' => 'abc',
        ]);

        try {
            Socialite::driver('google')->stateless()->user();
            $this->fail('expected OAuthErrorException');
        } catch (OAuthErrorException $e) {
            $this->assertSame('access_denied', $e->error);
            $this->assertSame('the user denied the request', $e->errorDescription);
            $this->assertSame('abc', $e->state);
            $this->assertSame('google', $e->provider);
            $this->assertSame([
                'provider' => 'google',
                'error' => 'access_denied',
                'error_description' => 'the user denied the request',
                'state' => 'abc',
            ], $e->toArray());
        }
    }

    public function test_deny_renders_json_when_requested(): void
    {
        $exception = new OAuthErrorException('access_denied', 'the user denied the request', 'st', 'jumpcloud');
        $request = Request::create('/oauth/callback/jumpcloud', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $exception->render($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'provider' => 'jumpcloud',
            'error' => 'access_denied',
            'error_description' => 'the user denied the request',
            'state' => 'st',
        ], $response->getData(true));
    }

    public function test_deny_renders_redirect_for_html(): void
    {
        $this->app['config']->set('bohurupee.error_redirect', '/login');
        $exception = new OAuthErrorException('access_denied', 'the user denied the request');
        $request = Request::create('/oauth/callback/jumpcloud', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $request->setLaravelSession($this->app['session']->driver());

        $response = $exception->render($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame(url('/login'), $response->getTargetUrl());
        $this->assertSame('access_denied', $request->session()->get('bohurupee_oauth_error'));
        $this->assertSame('the user denied the request', $request->session()->get('filament-socialite-login-error'));
    }

    public function test_production_refuses_to_boot(): void
    {
        $this->expectException(ProductionForbiddenException::class);

        $this->app['env'] = 'production';
        $this->app['config']->set('bohurupee.enabled', true);
        (new BohurupeeServiceProvider($this->app))->boot();
    }

    public function test_disabled_leaves_native_socialite(): void
    {
        $this->enableBohurupee = false;
        $this->refreshApplication();

        $this->assertNotInstanceOf(BohurupeeFactory::class, $this->app->make(Factory::class));
        $this->assertInstanceOf(GithubProvider::class, Socialite::driver('github'));

        $this->enableBohurupee = true;
    }
}
