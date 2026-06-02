<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use RuntimeException;

final class RecordNotFoundException extends RuntimeException
{
    public static function forRecord(string $model, int $id): self
    {
        return new self("{$model}({$id}) not found.");
    }
}
