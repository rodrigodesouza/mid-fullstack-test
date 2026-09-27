<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTO;

final readonly class MerchantInput
{
    public function __construct(
        public string $name,
        public string $city,
        public string $country,
    ) {}
}
