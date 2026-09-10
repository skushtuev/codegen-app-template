<?php

declare(strict_types=1);

namespace Common\Infra\Kratos\Exception;

use RuntimeException;
use Throwable;

/** Any failure while talking to Ory Kratos. */
final class KratosException extends RuntimeException
{
    public static function from(Throwable $previous): self
    {
        return new self('Kratos request failed: ' . $previous->getMessage(), (int) $previous->getCode(), $previous);
    }
}
