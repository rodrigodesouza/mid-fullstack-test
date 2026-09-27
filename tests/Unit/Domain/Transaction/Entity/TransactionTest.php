<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;

function makeTransaction(
    string $id = 'txn_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
    int $companyId = 1,
    ?int $cardId = 1,
    ?string $authorizationId = 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
    ?string $eventId = 'evt_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
    TransactionTypeEnum $type = TransactionTypeEnum::RESERVE,
    ?Money $amount = null,
    ?string $limitMonth = '2026-09',
    string $reference = 'auth_001',
): Transaction {
    $amount ??= Money::fromCents(-50000);

    return new Transaction(
        id: $id,
        companyId: $companyId,
        cardId: $cardId,
        authorizationId: $authorizationId,
        eventId: $eventId,
        type: $type,
        amount: $amount,
        occurredAt: new DateTimeImmutable('2026-09-26 10:00:00'),
        limitMonth: $limitMonth,
        reference: $reference,
    );
}
// cria uma transação
it('creates a transaction', function () {
    $transaction = makeTransaction();

    expect($transaction->id())->toBe('txn_01J8KQ7Z3N9M2P4R6T8V0W1X2Y')
        ->and($transaction->companyId())->toBe(1)
        ->and($transaction->cardId())->toBe(1)
        ->and($transaction->authorizationId())->toBe('aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y')
        ->and($transaction->eventId())->toBe('evt_01J8KQ7Z3N9M2P4R6T8V0W1X2Y')
        ->and($transaction->type())->toBe(TransactionTypeEnum::RESERVE)
        ->and($transaction->amount()->toCents())->toBe(-50000)
        ->and($transaction->limitMonth())->toBe('2026-09')
        ->and($transaction->reference())->toBe('auth_001');
});

it('does not allow an invalid transaction id', function () {
    makeTransaction(id: '');
})->throws(
    InvalidArgumentException::class,
    'Transaction id cannot be empty.'
);

it('does not allow an invalid company id', function () {
    makeTransaction(companyId: 0);
})->throws(
    InvalidArgumentException::class,
    'Transaction company id must be greater than zero.'
);

it('does not allow an invalid card id', function () {
    makeTransaction(cardId: 0);
})->throws(
    InvalidArgumentException::class,
    'Transaction card id must be greater than zero.'
);

it('does not allow an invalid authorization id', function () {
    makeTransaction(authorizationId: '');
})->throws(
    InvalidArgumentException::class,
    'Transaction authorization id cannot be empty.'
);

it('does not allow an invalid event id', function () {
    makeTransaction(eventId: '');
})->throws(
    InvalidArgumentException::class,
    'Transaction event id cannot be empty.'
);

// não permite um valor zero
it('does not allow a zero amount', function () {
    makeTransaction(amount: Money::fromCents(0));
})->throws(
    InvalidArgumentException::class,
    'Transaction amount must not be zero.'
);

// não permite um limite com mês inválido
it('does not allow an invalid limit month', function () {
    makeTransaction(limitMonth: '2026-13');
})->throws(
    InvalidArgumentException::class,
    'Transaction limit month must be in YYYY-MM format.'
);

it('does not allow an empty reference', function () {
    makeTransaction(reference: '   ');
})->throws(
    InvalidArgumentException::class,
    'Transaction reference cannot be empty.'
);
