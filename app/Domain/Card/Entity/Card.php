<?php

declare(strict_types=1);

namespace App\Domain\Card\Entity;

use App\Domain\Card\Enums\CardStatusEnum;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final class Card
{
    /**
     * @property-read Money $monthlyLimitCents
     * monthlyLimitCents: limite mensal nominal do cartão, armazenado em centavos.
     * É obrigatório e deve ser maior que zero. O limite restante (limit_remaining) não fica armazenado no cartão;
     * ele é derivado das transações do período.
     * @property-read string $cardToken
     * Identificador/token usado pela rede para identificar o cartão sem expor seus dados reais.
     * @property-read CardStatusEnum $status
     * Estado do cartão. Os valores usados pelo domínio são`active`e`blocked`. Um cartão`blocked`não pode gerar uma authorization aprovada.
     */
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly string $cardToken,
        private readonly Money $monthlyLimitCents,
        private CardStatusEnum $status,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?Money $purchaseLimitCents = null,
    ) {
        if ($this->id <= 0) {
            throw new InvalidArgumentException('Card id must be greater than zero.');
        }

        if ($this->userId <= 0) {
            throw new InvalidArgumentException('User id must be greater than zero.');
        }

        if ($this->cardToken === '') {
            throw new InvalidArgumentException('Card token cannot be empty.');
        }

        if ($this->monthlyLimitCents->toCents() <= 0) {
            throw new InvalidArgumentException(
                'Monthly limit must be greater than zero.'
            );
        }
        if ($this->purchaseLimitCents !== null) {
            if ($this->purchaseLimitCents->toCents() <= 0) {
                throw new InvalidArgumentException(
                    'Purchase limit must be greater than zero.'
                );
            }
        }

    }

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * Identificador/token usado pela rede para identificar o cartão sem expor seus dados reais.
     */
    public function cardToken(): string
    {
        return $this->cardToken;
    }

    /**
     * monthlyLimitCents: limite mensal nominal do cartão, armazenado em centavos.
     * É obrigatório e deve ser maior que zero. O limite restante (limit_remaining) não fica armazenado no cartão;
     * ele é derivado das transações do período.
     */
    public function monthlyLimitCents(): Money
    {
        return $this->monthlyLimitCents;
    }

    /**
     * Limite máximo permitido para uma única autorização de compra, em centavos. Diferente do limite mensal.
     */
    public function purchaseLimitCents(): ?Money
    {
        return $this->purchaseLimitCents;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->status === CardStatusEnum::ACTIVE;
    }

    public function isBlocked(): bool
    {
        return $this->status === CardStatusEnum::BLOCKED;
    }

    public function block(): void
    {
        $this->status = CardStatusEnum::BLOCKED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->status = CardStatusEnum::ACTIVE;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Check if card is active or blocked
     */
    public function status(): CardStatusEnum
    {
        return $this->status;
    }
}
