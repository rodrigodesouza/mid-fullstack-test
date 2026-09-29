<?php

declare(strict_types=1);

namespace App\Domain\Company\Entity;

use App\Domain\Company\Exceptions\InvalidCompanyAttributeException;

final readonly class Company
{
    public function __construct(
        private int $id,
        private string $name,
    ) {
        $this->validate($name);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    private function validate(string $name): void
    {
        throw_if($this->id <= 0, InvalidCompanyAttributeException::class, 'Company ID must be a positive integer.');

        throw_if(mb_trim($name) === '', InvalidCompanyAttributeException::class, 'Company name cannot be empty.');
    }
}
