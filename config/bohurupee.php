<?php

$split = static function (?string $value): array {
    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value))));
};

return [
    'enabled' => (bool) env('BOHURUPEE_ENABLED', false),
    // Server-side token + userinfo (Compose DNS, e.g. http://bohurupee:4190).
    'url' => rtrim((string) env('BOHURUPEE_URL', 'http://127.0.0.1:4190'), '/'),
    // Browser authorize redirect. Defaults to url. Set when the app runs in
    // Docker so the host browser can reach a published port
    // (e.g. http://127.0.0.1:14190) while url stays on the internal network.
    'public_url' => rtrim((string) env(
        'BOHURUPEE_PUBLIC_URL',
        (string) env('BOHURUPEE_URL', 'http://127.0.0.1:4190'),
    ), '/'),
    'drivers' => $split(env('BOHURUPEE_DRIVERS')),
    'except' => $split(env('BOHURUPEE_EXCEPT')),
    // Where to send the browser after Deny / OAuth error (HTML requests).
    // Empty: filament.admin.auth.login, then login, then /login.
    'error_redirect' => env('BOHURUPEE_ERROR_REDIRECT'),
];
