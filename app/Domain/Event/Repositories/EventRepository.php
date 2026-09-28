<?php

declare(strict_types=1);

namespace App\Domain\Event\Repositories;

use App\Domain\Event\Entity\Event;

interface EventRepository
{
    public function findByExternalId(string $externalId): ?Event;

    public function save(Event $event): void;

    public function hasFinalCapture(string $authorizationId): bool;

    public function findPendingByAuthorizationReference(
        string $authorizationReference
    ): ?Event;

    public function update(Event $event): void;
}
