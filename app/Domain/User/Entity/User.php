<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Enums\RoleEnum;
use App\Domain\User\Exceptions\InvalidUserAttributeException;

final readonly class User
{
    public function __construct(
        private int $id,
        private int $companyId,
        private string $name,
        private Email $email,
        private RoleEnum $role,
        ?string $password,
    ) {
        $this->validate($companyId, $name, $password);
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

    private function validate(int $companyId, string $name, ?string $password): void
    {
        throw_if($this->id <= 0, InvalidUserAttributeException::class, 'User ID must be a positive integer.');

        throw_if(mb_trim($name) === '', InvalidUserAttributeException::class, 'User name cannot be empty.');

        throw_if($companyId <= 0, InvalidUserAttributeException::class, 'User company ID must be a positive integer.');

        throw_if($password === null || ($password !== '' && mb_strlen($password) < 6), InvalidUserAttributeException::class, 'User password must be at least 6 characters long.');

    }
}
