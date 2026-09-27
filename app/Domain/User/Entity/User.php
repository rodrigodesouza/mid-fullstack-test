<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Enums\RoleEnum;
use App\Domain\User\Exceptions\InvalidUserAttributeException;

final class User
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly string $name,
        private readonly Email $email,
        private readonly RoleEnum $role,
        private readonly ?string $password,
    ) {
        $this->validate($id, $companyId, $name, $password);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function role(): RoleEnum
    {
        return $this->role;
    }

    public function email(): string
    {
        return (string) $this->email;
    }

    private function validate(int $id, int $companyId, string $name, ?string $password): void
    {
        if ($this->id <= 0) {
            throw new InvalidUserAttributeException(
                'User ID must be a positive integer.'
            );
        }

        if (trim($name) === '') {
            throw new InvalidUserAttributeException('User name cannot be empty.');
        }

        if ($companyId <= 0) {
            throw new InvalidUserAttributeException('User company ID must be a positive integer.');
        }

        if ($password === null || ($password !== null && mb_strlen($password) < 6)) {
            throw new InvalidUserAttributeException('User password must be at least 6 characters long.');
        }

    }
}
