<?php

declare(strict_types=1);

namespace Tests\Support\Builders;

use App\Domain\Card\Entity\Card;
use App\Domain\Card\Enums\CardMccRuleEnum;
use App\Domain\Card\Enums\CardStatusEnum;
use App\Domain\Shared\ValueObjects\Money;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

final class CardBuilder
{
    private int $id = 1;

    private int $userId = 1;

    private string $cardToken = 'tok_ana';

    private array $mccRules = [];

    private Money $monthlyLimitCents;

    private ?Money $purchaseLimitCents = null;

    private CardStatusEnum $status = CardStatusEnum::ACTIVE;

    private readonly DateTimeImmutable $createdAt;

    private readonly DateTimeImmutable $updatedAt;

    private function __construct()
    {
        $this->monthlyLimitCents = Money::fromCents(100000);
        $this->createdAt = CarbonImmutable::parse('2026-09-01T00:00:00Z');
        $this->updatedAt = CarbonImmutable::parse('2026-09-01T00:00:00Z');
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

    public function withPurchaseLimit(int $cents): self
    {
        $this->purchaseLimitCents = Money::fromCents($cents);

        return $this;
    }

    public function withStatus(CardStatusEnum $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function withBlockedMcc(string $mcc): self
    {
        $this->mccRules[] = [
            'mcc' => $mcc,
            'rule' => CardMccRuleEnum::BLOCKED->value,
            'tolerance_percent' => 0,
        ];

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
            purchaseLimitCents: $this->purchaseLimitCents,
            mccRules: $this->mccRules,
        );
    }
}
