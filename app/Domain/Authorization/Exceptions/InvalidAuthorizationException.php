<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use InvalidArgumentException;
use Throwable;

final class InvalidAuthorizationException extends InvalidArgumentException
{
    public function __construct(string $message = 'Invalid authorization.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
