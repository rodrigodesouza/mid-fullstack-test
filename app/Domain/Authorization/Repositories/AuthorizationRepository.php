<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repositories;

use App\Domain\Authorization\Entity\Authorization;

interface AuthorizationRepository
{
    public function findByExternalId(string $externalId): ?Authorization;

    public function save(Authorization $authorization): void;

    public function findById(string $id): ?Authorization;
}
