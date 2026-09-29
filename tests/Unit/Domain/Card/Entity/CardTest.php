<?php

declare(strict_types=1);

use App\Domain\Card\Entity\Card;
use App\Domain\Card\Enums\CardStatusEnum;
use App\Domain\Shared\ValueObjects\Money;
use Carbon\CarbonImmutable;

function makeCard(
    int $id = 1,
    int $userId = 1,
    string $cardToken = 'tok_ana',
    int $monthlyLimitCents = 500000,
    CardStatusEnum $status = CardStatusEnum::ACTIVE,
): Card {
    $createdAt = CarbonImmutable::parse('2026-09-26 10:00:00');

    return new Card(
        id: $id,
        userId: $userId,
        cardToken: $cardToken,
        monthlyLimitCents: new Money($monthlyLimitCents),
        status: $status,
        createdAt: $createdAt,
        updatedAt: $createdAt,
    );
}

it('creates an active card', function (): void {
    $card = makeCard();

    expect($card->id())->toBe(1)
        ->and($card->userId())->toBe(1)
        ->and($card->cardToken())->toBe('tok_ana')
        ->and($card->monthlyLimitCents()->toCents())->toBe(500000)
        ->and($card->status())->toBe(CardStatusEnum::ACTIVE)
        ->and($card->isActive())->toBeTrue()
        ->and($card->isBlocked())->toBeFalse();
});

it('creates a blocked card', function (): void {
    $card = makeCard(status: CardStatusEnum::BLOCKED);

    expect($card->status())->toBe(CardStatusEnum::BLOCKED)
        ->and($card->isActive())->toBeFalse()
        ->and($card->isBlocked())->toBeTrue();
});

it('does not allow an invalid card id', function (): void {
    makeCard(id: 0);
})->throws(
    InvalidArgumentException::class,
    'Card id must be greater than zero.'
);

it('does not allow an invalid user id', function (): void {
    makeCard(userId: 0);
})->throws(
    InvalidArgumentException::class,
    'User id must be greater than zero.'
);

it('does not allow an empty card token', function (): void {
    makeCard(cardToken: '');
})->throws(
    InvalidArgumentException::class,
    'Card token cannot be empty.'
);

it('does not allow a zero monthly limit', function (): void {
    makeCard(monthlyLimitCents: 0);
})->throws(
    InvalidArgumentException::class,
    'Monthly limit must be greater than zero.'
);

it('does not allow a negative monthly limit', function (): void {
    makeCard(monthlyLimitCents: -1);
})->throws(
    InvalidArgumentException::class,
    'Monthly limit must be greater than zero.'
);

it('blocks an active card', function (): void {
    $card = makeCard();

    $card->block();

    expect($card->status())->toBe(CardStatusEnum::BLOCKED)
        ->and($card->isBlocked())->toBeTrue()
        ->and($card->isActive())->toBeFalse();
});

it('activates a blocked card', function (): void {
    $card = makeCard(status: CardStatusEnum::BLOCKED);

    $card->activate();

    expect($card->status())->toBe(CardStatusEnum::ACTIVE)
        ->and($card->isActive())->toBeTrue()
        ->and($card->isBlocked())->toBeFalse();
});
