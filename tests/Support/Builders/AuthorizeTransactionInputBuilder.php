<?php

declare(strict_types=1);

namespace Tests\Support\Builders;

use App\Application\Authorization\DTO\AuthorizeTransactionInput;
use App\Application\Authorization\DTO\MerchantInput;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

final class AuthorizeTransactionInputBuilder
{
    private string $externalId = 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y';

    private string $cardToken = 'tok_ana';

    private Money $amount;

    private string $currency = 'BRL';

    private string $mcc = '5812';

    private MerchantInput $merchant;

    private DateTimeImmutable $occurredAt;

    private function __construct()
    {
        $this->amount = Money::fromCents(12990);

        $this->merchant = new MerchantInput(
            name: 'Restaurante Bom Prato',
            city: 'Porto Alegre',
            country: 'BR',
        );

        $this->occurredAt = new DateTimeImmutable(
            '2026-09-17T14:03:22Z'
        );
    }

    public static function make(): self
    {
        return new self();
    }

    public function withExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function withCardToken(string $cardToken): self
    {
        $this->cardToken = $cardToken;

        return $this;
    }

    public function withAmount(int $cents): self
    {
        $this->amount = Money::fromCents($cents);

        return $this;
    }

    public function withCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function withMcc(string $mcc): self
    {
        $this->mcc = $mcc;

        return $this;
    }

    public function withMerchant(
        string $name,
        string $city,
        string $country,
    ): self {
        $this->merchant = new MerchantInput(
            name: $name,
            city: $city,
            country: $country,
        );

        return $this;
    }

    public function withOccurredAt(DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function build(): AuthorizeTransactionInput
    {
        return new AuthorizeTransactionInput(
            externalId: $this->externalId,
            cardToken: $this->cardToken,
            amount: $this->amount,
            currency: $this->currency,
            mcc: $this->mcc,
            merchant: $this->merchant,
            occurredAt: $this->occurredAt,
        );
    }
}
