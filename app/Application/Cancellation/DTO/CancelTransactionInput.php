<?php

declare(strict_types=1);

namespace App\Application\Cancellation\DTO;

use DateTimeImmutable;

final readonly class CancelTransactionInput
{
    public function __construct(
        public string $externalId,
        public string $authorizationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
