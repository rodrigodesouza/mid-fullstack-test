<?php

declare(strict_types=1);

namespace App\Domain\Card\Entity;

use App\Domain\Card\Enums\CardMccRuleEnum;
use App\Domain\Card\Enums\CardStatusEnum;
use App\Domain\Shared\ValueObjects\Money;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;

final class Card
{
    /**
     * Limite mensal nominal do cartão, armazenado em centavos.
     * É obrigatório e deve ser maior que zero. O limite restante
     * é derivado das transações do período.
     *
     * @property-read Money $monthlyLimitCents
     * @property-read string $cardToken
     * @property-read CardStatusEnum $status
     *
     * @param  array<int, array{mcc?: string, rule?: string}>  $mccRules
     */
    public function __construct(private readonly int $id, private readonly int $userId, private readonly string $cardToken, private readonly Money $monthlyLimitCents, private CardStatusEnum $status, private readonly DateTimeImmutable $createdAt, private DateTimeImmutable $updatedAt, private readonly ?Money $purchaseLimitCents = null, private readonly array $mccRules = [])
    {
        throw_if($this->id <= 0, InvalidArgumentException::class, 'Card id must be greater than zero.');
        throw_if($this->userId <= 0, InvalidArgumentException::class, 'User id must be greater than zero.');
        throw_if($this->cardToken === '', InvalidArgumentException::class, 'Card token cannot be empty.');
        throw_if($this->monthlyLimitCents->toCents() <= 0, InvalidArgumentException::class, 'Monthly limit must be greater than zero.');

        if ($this->purchaseLimitCents instanceof Money) {
            throw_if($this->purchaseLimitCents->toCents() <= 0, InvalidArgumentException::class, 'Purchase limit must be greater than zero.');
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

    public function isMccBlocked(string $mcc): bool
    {
        return array_any(
            $this->mccRules,
            fn (array $rule): bool => ($rule['mcc'] ?? null) === $mcc
                && ($rule['rule'] ?? null) === CardMccRuleEnum::BLOCKED->value
        );
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
        $this->updatedAt = CarbonImmutable::now();
    }

    public function activate(): void
    {
        $this->status = CardStatusEnum::ACTIVE;
        $this->updatedAt = CarbonImmutable::now();
    }

    /**
     * Check if card is active or blocked
     */
    public function status(): CardStatusEnum
    {
        return $this->status;
    }
}
