<?php

declare(strict_types=1);

namespace Velm\Web\Api;

use RuntimeException;

final class InvalidDomainException extends RuntimeException
{
    public static function forMessage(string $message): self
    {
        return new self($message);
    }
}
