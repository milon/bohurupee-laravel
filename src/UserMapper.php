<?php

namespace Milon\Bohurupee;

use Laravel\Socialite\Two\User;

final class UserMapper
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public static function map(array $raw): User
    {
        $user = new User;
        $user->setRaw($raw)->map([
            'id' => self::first($raw, ['id', 'sub']),
            'nickname' => self::first($raw, ['nickname', 'login', 'preferred_username']),
            'name' => self::first($raw, ['name', 'display_name']),
            'email' => self::first($raw, ['email']),
            'avatar' => self::avatar($raw),
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     */
    public static function first(array $raw, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = self::value($raw, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function avatar(array $raw): mixed
    {
        $nested = self::value($raw, 'picture.data.url');
        if (is_string($nested) && $nested !== '') {
            return $nested;
        }

        $picture = $raw['picture'] ?? null;
        if (is_string($picture) && $picture !== '') {
            return $picture;
        }

        return self::first($raw, ['avatar', 'profile_image_url', 'avatar_url']);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function value(array $raw, string $path): mixed
    {
        $current = $raw;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
