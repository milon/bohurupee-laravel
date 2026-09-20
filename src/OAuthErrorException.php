<?php

namespace Milon\Bohurupee;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class OAuthErrorException extends Exception
{
    public function __construct(
        public readonly string $error,
        public readonly string $errorDescription = '',
        public readonly ?string $state = null,
        public readonly ?string $provider = null,
    ) {
        $message = $errorDescription !== '' ? $errorDescription : $error;
        parent::__construct($message);
    }

    public static function fromRequest(Request $request, ?string $provider = null): self
    {
        return new self(
            error: (string) $request->query('error', 'access_denied'),
            errorDescription: (string) $request->query('error_description', ''),
            state: $request->query('state'),
            provider: $provider,
        );
    }

    /**
     * @return array{provider: ?string, error: string, error_description: string, state: ?string}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'error' => $this->error,
            'error_description' => $this->errorDescription !== '' ? $this->errorDescription : $this->error,
            'state' => $this->state,
        ];
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->wantsJson($request)) {
            return response()->json($this->toArray(), 400);
        }

        $message = $this->errorDescription !== '' ? $this->errorDescription : $this->error;
        session()->flash('bohurupee_oauth_error', $this->error);
        session()->flash('bohurupee_oauth_error_description', $message);
        // Filament Socialite login form reads this flash key.
        session()->flash('filament-socialite-login-error', $message);
        session()->flash('error', $message);

        return redirect()->to($this->redirectUrl());
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        return str_contains($accept, 'application/json') && ! str_contains($accept, 'text/html');
    }

    private function redirectUrl(): string
    {
        $configured = config('bohurupee.error_redirect');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach ([
            'filament.admin.auth.login',
            'login',
        ] as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }

        return url('/login');
    }
}
