<?php

declare(strict_types=1);

namespace App\Domain\Card\Repositories;

use App\Domain\Card\Entity\Card;

interface CardRepository
{
    public function findByToken(string $token): ?Card;
}
