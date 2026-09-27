<?php

declare(strict_types=1);

namespace Tests\Support\Builders;

use App\Domain\Card\Entity\Card;
use App\Domain\Card\Enums\CardStatusEnum;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

final class CardBuilder
{
    private int $id = 1;

    private int $userId = 1;

    private string $cardToken = 'tok_ana';

    private Money $monthlyLimitCents;

    private CardStatusEnum $status = CardStatusEnum::ACTIVE;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        $this->monthlyLimitCents = Money::fromCents(100000);
        $this->createdAt = new DateTimeImmutable('2026-09-01T00:00:00Z');
        $this->updatedAt = new DateTimeImmutable('2026-09-01T00:00:00Z');
    }

    public static function make(): self
    {
        return new self();
    }

    public function withId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function withToken(string $token): self
    {
        $this->cardToken = $token;

        return $this;
    }

    public function withMonthlyLimit(int $cents): self
    {
        $this->monthlyLimitCents = Money::fromCents($cents);

        return $this;
    }

    public function withStatus(CardStatusEnum $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function build(): Card
    {
        return new Card(
            id: $this->id,
            userId: $this->userId,
            cardToken: $this->cardToken,
            monthlyLimitCents: $this->monthlyLimitCents,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }
}
