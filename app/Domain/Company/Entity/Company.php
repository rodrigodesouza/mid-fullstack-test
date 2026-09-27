<?php

declare(strict_types=1);

namespace App\Domain\Company\Entity;

use App\Domain\Company\Exceptions\InvalidCompanyAttributeException;

final class Company
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
    ) {
        $this->validate($id, $name);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    private function validate(int $id, string $name): void
    {
        if ($this->id <= 0) {
            throw new InvalidCompanyAttributeException(
                'Company ID must be a positive integer.'
            );
        }

        if (trim($name) === '') {
            throw new InvalidCompanyAttributeException('Company name cannot be empty.');
        }
    }
}
