<?php

declare(strict_types=1);

namespace Velm\Web\Api;

final class CannotQuickCreateException extends \InvalidArgumentException
{
    /**
     * @param  list<string>  $fields
     */
    public static function forModel(string $model, array $fields): self
    {
        sort($fields);

        return new self(
            sprintf(
                "Cannot quick-create %s: requires %s. Use Create and edit instead.",
                $model,
                implode(', ', $fields),
            ),
        );
    }
}
