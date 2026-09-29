<?php

declare(strict_types=1);

namespace Tests\Support\Builders;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entity\User;
use App\Domain\User\Enums\RoleEnum;

final class UserBuilder
{
    private int $id = 1;

    private int $companyId = 1;

    private string $name = 'Test User';

    private string $email = 'user@example.com';

    private RoleEnum $role = RoleEnum::CARD_HOLDER;

    private ?string $password = 'secret123';

    private function __construct() {}

    public static function make(): self
    {
        return new self();
    }

    public function withId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withCompanyId(int $companyId): self
    {
        $this->companyId = $companyId;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withRole(RoleEnum $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function withPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function build(): User
    {
        return new User(
            id: $this->id,
            companyId: $this->companyId,
            name: $this->name,
            email: new Email($this->email),
            role: $this->role,
            password: $this->password,
        );
    }
}
