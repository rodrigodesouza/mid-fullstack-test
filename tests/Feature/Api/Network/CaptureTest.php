<?php

declare(strict_types=1);

use App\Application\Authorization\DTO\AuthorizeTransactionInput;
use App\Application\Authorization\DTO\MerchantInput;
use App\Application\Authorization\UseCases\AuthorizeTransaction;
use App\Application\Capture\DTO\CaptureTransactionInput;
use App\Application\Capture\UseCases\CaptureTransaction;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(
    RefreshDatabase::class,
)->beforeEach(function () {
    $this->seed();
});

// C1 — Regra: uma captura válida deve criar uma nova transação de captura sem alterar a reserva original.
it('captures an approved authorization and preserves the original reservation', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_001',
        cardToken: 'tok_ana',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorize = app(AuthorizeTransaction::class);

    $authorizationResult = $authorize->execute($authorizationInput);

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $reservation = TransactionModel::query()
        ->where('authorization_id', $authorization->id())
        ->where('type', TransactionTypeEnum::RESERVE->value)
        ->first();

    expect($reservation)
        ->not->toBeNull()
        ->and($reservation->amount_cents)
        ->toBe(-80000);

    // C1
    $captureInput = new CaptureTransactionInput(
        externalId: 'cap_capture_001',
        authorizationId: $authorization->id(),
        amount: Money::fromCents(30000),
        currency: 'BRL',
        occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
        final: false,
    );

    $capture = app(CaptureTransaction::class);

    $result = $capture->execute($captureInput);

    expect($result)->toBeTrue();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(1);

    expect(
        TransactionModel::query()
            ->whereKey($reservation->id)
            ->value('amount_cents')
    )->toBe(-80000);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->value('amount_cents')
    )->toBe(-30000);
});

// C2 — Regra: uma autorização pode receber múltiplas capturas parciais, cada uma registrada separadamente no ledger.
it('records multiple partial captures for the same authorization', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_002',
        cardToken: 'tok_ana',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorize = app(AuthorizeTransaction::class);

    $authorizationResult = $authorize->execute($authorizationInput);

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $capture = app(CaptureTransaction::class);

    $firstCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_002_001',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(30000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: false,
        ),
    );

    $secondCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_002_002',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(30000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T16:03:22Z'),
            final: false,
        ),
    );

    expect($firstCapture)->toBeTrue()
        ->and($secondCapture)->toBeTrue();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(2);

    expect(
        (int) TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents')
    )->toBe(-60000);
});

// C3 / C2 — Regra: a captura pode exceder o valor autorizado em até 20% para MCCs com tolerância.
it('allows capture above authorization amount within mcc tolerance', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_003',
        cardToken: 'tok_diego',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '7011',
        merchant: new MerchantInput(
            name: 'Hotel Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorize = app(AuthorizeTransaction::class);

    $authorizationResult = $authorize->execute($authorizationInput);

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $capture = app(CaptureTransaction::class);

    $result = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_003_001',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(90000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: true,
        ),
    );

    expect($result)->toBeTrue();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents')
    )->toBe('-90000');
});

// C4 / C3 — Regra: a captura que ultrapassa a autorização mais a tolerância permitida deve ser rejeitada.
it('rejects capture above mcc tolerance', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_004',
        cardToken: 'tok_diego',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '7011',
        merchant: new MerchantInput(
            name: 'Hotel Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorize = app(AuthorizeTransaction::class);

    $authorizationResult = $authorize->execute($authorizationInput);

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $capture = app(CaptureTransaction::class);

    $result = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_004_001',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(96100),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: true,
        ),
    );

    expect($result)->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(0);
});

// C5 / C4 — Regra: a soma das capturas não pode ultrapassar a autorização acrescida da tolerância.
it('rejects cumulative captures above mcc tolerance', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_005',
        cardToken: 'tok_diego',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '7011',
        merchant: new MerchantInput(
            name: 'Hotel Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorize = app(AuthorizeTransaction::class);

    $authorizationResult = $authorize->execute($authorizationInput);

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $capture = app(CaptureTransaction::class);

    $firstCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_005_001',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(90000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: false,
        ),
    );

    $secondCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_005_002',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(10000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T16:03:22Z'),
            final: true,
        ),
    );

    expect($firstCapture)->toBeTrue()
        ->and($secondCapture)->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(1);

    expect(
        (int) TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents')
    )->toBe(-90000);
});

// C6 / C5 — Regra: após uma captura final, nenhuma nova captura pode ser processada.
it('rejects captures after a final capture', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_006',
        cardToken: 'tok_diego',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorizationResult = app(AuthorizeTransaction::class)
        ->execute($authorizationInput);

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $capture = app(CaptureTransaction::class);

    $firstCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_006_001',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(50000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: true,
        ),
    );

    $secondCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_006_002',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(10000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T16:03:22Z'),
            final: false,
        ),
    );

    expect($firstCapture)->toBeTrue()
        ->and($secondCapture)->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(1);
});

// C7 / C6 — Regra: a captura deve utilizar a mesma moeda da autorização.
it('rejects capture with different currency from authorization', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_007',
        cardToken: 'tok_diego',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorizationResult = app(AuthorizeTransaction::class)
        ->execute($authorizationInput);

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $capture = app(CaptureTransaction::class);

    $result = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_007_001',
            authorizationId: $authorization->id(),
            amount: Money::fromCents(50000),
            currency: 'USD',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: false,
        ),
    );

    expect($result)->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(0);
});
