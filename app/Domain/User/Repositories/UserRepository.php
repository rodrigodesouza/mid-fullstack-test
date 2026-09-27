<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\Entity\User;

interface UserRepository
{
    public function findById(int $id): ?User;
}
