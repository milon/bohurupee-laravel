# milon/bohurupee-laravel

Optional [Laravel Socialite](https://laravel.com/docs/socialite) adapter for
[Bohurupee](https://github.com/milon/bohurupee). When enabled, `Socialite::driver()`
talks to a local Bohurupee process instead of Google, GitHub, or any other
remote IdP.

This package is **dev-only**. It refuses to boot when `APP_ENV=production`.
It supports Laravel 11, 12, and 13.
It supports Laravel 11, 12, and 13.
It supports Laravel 11, 12, and 13.

## Install

```bash
composer require milon/bohurupee-laravel --dev
```

From a local checkout (before Packagist):

```json
{
  "require-dev": {
    "milon/bohurupee-laravel": "@dev"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../bohurupee-laravel"
    }
  ]
}
```

The service provider is auto-discovered.

## Configure

```env
BOHURUPEE_ENABLED=true
BOHURUPEE_URL=http://127.0.0.1:4190
# Optional: wrap only these Socialite names (empty = all drivers, including custom slugs)
# BOHURUPEE_DRIVERS=google,github
# Optional: leave these drivers on the real provider
# BOHURUPEE_EXCEPT=apple
```

Keep your usual `config/services.php` client IDs and redirect URIs. Bohurupee
accepts any local secret.

Usage and mapping notes live in the Bohurupee repo at
`docs/socialite.md` ([GitHub](https://github.com/milon/bohurupee/blob/master/docs/socialite.md)).

## Tests

```bash
composer install
vendor/bin/phpunit
```
