<?php

namespace Milon\Bohurupee;

use RuntimeException;

class ProductionForbiddenException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Bohurupee must not run in production. Set BOHURUPEE_ENABLED=false or use a non-production APP_ENV.'
        );
    }
}
