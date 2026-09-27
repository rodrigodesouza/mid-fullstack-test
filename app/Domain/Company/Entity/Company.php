<?php

declare(strict_types=1);

namespace App\Domain\Company\Entity;

use App\Domain\Company\Exceptions\InvalidCompanyAttributeException;

final class Company
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
    ) {
        $this->validate($id, $name);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    private function validate(string $id, string $name): void
    {
        if (trim($id) === '') {
            throw new InvalidCompanyAttributeException('Company ID cannot be empty.');
        }

        if (trim($name) === '') {
            throw new InvalidCompanyAttributeException('Company name cannot be empty.');
        }
    }
}
