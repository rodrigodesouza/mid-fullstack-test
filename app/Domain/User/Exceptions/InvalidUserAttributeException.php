<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use InvalidArgumentException;

final class InvalidUserAttributeException extends InvalidArgumentException
{
    public function __construct(string $message = 'Invalid user attribute.')
    {
        parent::__construct($message);
    }
}
