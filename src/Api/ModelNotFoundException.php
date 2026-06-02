<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use RuntimeException;

final class ModelNotFoundException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("Unknown model {$model}.");
    }
}
