<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTO;

use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

final class AuthorizeTransactionInput
{
    public function __construct(
        public string $externalId,
        public string $cardToken,
        public Money $amount,
        public string $currency,
        public string $mcc,
        public MerchantInput $merchant,
        public DateTimeImmutable $occurredAt,
    ) {}

}
