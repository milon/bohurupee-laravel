<?php

namespace Milon\Bohurupee;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class BohurupeeProvider extends AbstractProvider
{
    /**
     * @var list<string>
     */
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    public function __construct(
        Request $request,
        $clientId,
        $clientSecret,
        $redirectUrl,
        protected string $slug,
        protected string $baseUrl,
        protected string $publicUrl,
        $guzzle = [],
    ) {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->publicEndpoint('authorize'), $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->endpoint('token');
    }

    /**
     * {@inheritdoc}
     *
     * Bohurupee (and any OIDC IdP) may redirect back with error=access_denied
     * when the user clicks Deny. Surface that as OAuthErrorException instead of
     * letting Socialite fail on a missing code.
     */
    public function user()
    {
        if ($this->request->filled('error')) {
            throw OAuthErrorException::fromRequest($this->request, $this->slug);
        }

        return parent::user();
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->endpoint('userinfo'), [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true) ?: [];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return UserMapper::map($user);
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl.'/'.$this->slug.'/'.$path;
    }

    private function publicEndpoint(string $path): string
    {
        return $this->publicUrl.'/'.$this->slug.'/'.$path;
    }
}
