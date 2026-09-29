<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class Email implements Stringable
{
    private string $email;

    public function __construct(string $email)
    {
        throw_unless(filter_var($email, FILTER_VALIDATE_EMAIL), InvalidArgumentException::class, 'Invalid email address.');

        $this->email = $email;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
