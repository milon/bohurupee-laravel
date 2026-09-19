<?php

$split = static function (?string $value): array {
    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value))));
};

return [
    'enabled' => (bool) env('BOHURUPEE_ENABLED', false),
    'url' => rtrim((string) env('BOHURUPEE_URL', 'http://127.0.0.1:4190'), '/'),
    'drivers' => $split(env('BOHURUPEE_DRIVERS')),
    'except' => $split(env('BOHURUPEE_EXCEPT')),
];
