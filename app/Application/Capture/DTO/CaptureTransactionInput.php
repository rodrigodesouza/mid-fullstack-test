<?php

declare(strict_types=1);

namespace App\Application\Capture\DTO;

use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

final readonly class CaptureTransactionInput
{
    public function __construct(
        public string $externalId,
        public string $authorizationId,
        public Money $amount,
        public string $currency,
        public DateTimeImmutable $occurredAt,
        public bool $final,
    ) {}
}
