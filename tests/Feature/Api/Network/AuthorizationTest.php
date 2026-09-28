<?php

declare(strict_types=1);

use App\Application\Authorization\DTO\AuthorizeTransactionInput;
use App\Application\Authorization\DTO\MerchantInput;
use App\Application\Authorization\UseCases\AuthorizeTransaction;
use App\Application\Capture\DTO\CaptureTransactionInput;
use App\Application\Capture\UseCases\CaptureTransaction;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Enums\AuthorizationReasonEnum;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Models\AuthorizationModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(
    RefreshDatabase::class,
)->beforeEach(function () {
    $this->seed();
});

// I1 — Regra: uma autorização válida deve ser aprovada e registrar a reserva financeira no ledger.
it('approves a valid authorization and records the reservation', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_001',
        cardToken: 'tok_ana',
        amount: Money::fromCents(12990),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Bom Prato',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($result->reason)
        ->toBeNull()
        ->and($result->authorization)
        ->not->toBeNull();

    $authorization = AuthorizationModel::query()
        ->where('external_id', 'aut_integration_001')
        ->first();

    expect($authorization)
        ->not->toBeNull()
        ->and($authorization->amount_cents)
        ->toBe(12990)
        ->and($authorization->card_id)
        ->toBe(1)
        ->and($authorization->company_id)
        ->toBe(1);

    $reservation = TransactionModel::query()
        ->where('reference', $authorization->id)
        ->where('type', TransactionTypeEnum::RESERVE->value)
        ->first();

    expect($reservation)
        ->not->toBeNull()
        ->and($reservation->amount_cents)
        ->toBe(-12990)
        ->and($reservation->card_id)
        ->toBe(1)
        ->and($reservation->company_id)
        ->toBe(1);
});

// X1 / I10 — Regra: uma captura recebida antes da autorização deve ser processada quando a autorização chegar.
it('processes a pending capture when its authorization arrives', function () {
    $capture = app(CaptureTransaction::class);

    // Primeiro: chega uma captura para uma autorização que ainda não existe.
    $captureResult = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'evt_pending_010_001',
            authorizationId: 'aut_pending_010_001',
            amount: Money::fromCents(30000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T18:40:00Z'),
            final: false,
        ),
    );

    expect($captureResult)->toBeTrue();

    $pendingEvent = EventModel::query()
        ->where('external_id', 'evt_pending_010_001')
        ->first();

    expect($pendingEvent)
        ->not->toBeNull()
        ->and($pendingEvent->authorization_reference)
        ->toBe('aut_pending_010_001')
        ->and($pendingEvent->authorization_id)
        ->toBeNull()
        ->and($pendingEvent->status)
        ->toBe('pending');

    // Depois: chega a autorização correspondente.
    $authorization = app(AuthorizeTransaction::class);

    $result = $authorization->execute(
        new AuthorizeTransactionInput(
            externalId: 'aut_pending_010_001',
            cardToken: 'tok_ana',
            amount: Money::fromCents(80000),
            currency: 'BRL',
            mcc: '5812',
            merchant: new MerchantInput(
                name: 'Restaurante Bom Prato',
                city: 'Porto Alegre',
                country: 'BR',
            ),
            occurredAt: new DateTimeImmutable('2026-09-17T18:30:00Z'),
        ),
    );

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($result->authorization)
        ->not->toBeNull();

    // A captura pendente deve ter sido processada.
    $pendingEvent->refresh();

    expect($pendingEvent->status)
        ->toBe('processed')
        ->and($pendingEvent->authorization_id)
        ->toBe($result->authorization->id());

    // E deve existir exatamente uma transação de capture.
    expect(
        TransactionModel::query()
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->where('reference', 'evt_pending_010_001')
            ->count()
    )->toBe(1);

});

// I2 / A2 — Regra: uma autorização com MCC bloqueado deve ser recusada sem gerar efeito financeiro.
it('declines an authorization with a blocked MCC without financial effect', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_002',
        cardToken: 'tok_ana',
        amount: Money::fromCents(10000),
        currency: 'BRL',
        mcc: '7995',
        merchant: new MerchantInput(
            name: 'Casino Test',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::MCC_BLOCKED)
        ->and($result->authorization)
        ->toBeNull();

    expect(
        AuthorizationModel::query()
            ->where('external_id', 'aut_integration_002')
            ->exists()
    )->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('reference', 'aut_integration_002')
            ->exists()
    )->toBeFalse();
});

// I3 / A3 — Regra: uma autorização acima do limite de compra do cartão deve ser recusada sem efeito financeiro.
it('declines an authorization above the purchase limit without financial effect', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_003',
        cardToken: 'tok_ana',
        amount: Money::fromCents(85000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED)
        ->and($result->authorization)
        ->toBeNull();

    expect(
        AuthorizationModel::query()
            ->where('external_id', 'aut_integration_003')
            ->exists()
    )->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('reference', 'aut_integration_003')
            ->exists()
    )->toBeFalse();
});

// I4 / A4 — Regra: uma autorização de cartão bloqueado deve ser recusada sem efeito financeiro.
it('declines an authorization for a blocked card without financial effect', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_004',
        cardToken: 'tok_carla',
        amount: Money::fromCents(10000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::CARD_BLOCKED)
        ->and($result->authorization)
        ->toBeNull();

    expect(
        AuthorizationModel::query()
            ->where('external_id', 'aut_integration_004')
            ->exists()
    )->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('reference', 'aut_integration_004')
            ->exists()
    )->toBeFalse();
});

// I5 / A5 — Regra: uma autorização para cartão inexistente deve ser recusada sem efeito financeiro.
it('declines an authorization for a nonexistent card without financial effect', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_005',
        cardToken: 'tok_nonexistent',
        amount: Money::fromCents(10000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::CARD_NOT_FOUND)
        ->and($result->authorization)
        ->toBeNull();

    expect(
        AuthorizationModel::query()
            ->where('external_id', 'aut_integration_005')
            ->exists()
    )->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('reference', 'aut_integration_005')
            ->exists()
    )->toBeFalse();
});

// I6 / A6 — Regra: a autorização deve ser recusada quando o valor exceder o saldo disponível da empresa.
it('declines an authorization above the available company balance without financial effect', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_006',
        cardToken: 'tok_diego',
        amount: Money::fromCents(1000100),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::COMPANY_BALANCE_EXCEEDED)
        ->and($result->authorization)
        ->toBeNull();

    expect(
        AuthorizationModel::query()
            ->where('external_id', 'aut_integration_006')
            ->exists()
    )->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('reference', 'aut_integration_006')
            ->exists()
    )->toBeFalse();
});

// I7 / A7 — Regra: uma autorização acima do limite mensal restante do cartão deve ser recusada sem efeito financeiro.
it('declines an authorization above the remaining monthly card limit without financial effect', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_007',
        cardToken: 'tok_bruno',
        amount: Money::fromCents(50100),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED)
        ->and($result->authorization)
        ->toBeNull();

    expect(
        AuthorizationModel::query()
            ->where('external_id', 'aut_integration_007')
            ->exists()
    )->toBeFalse();

    expect(
        TransactionModel::query()
            ->where('reference', 'aut_integration_007')
            ->exists()
    )->toBeFalse();
});

// I8 / A7 — Regra: uma autorização exatamente igual ao saldo disponível da empresa deve ser aprovada.
it('approves an authorization exactly equal to the available company balance', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_008',
        cardToken: 'tok_diego',
        amount: Money::fromCents(1_000_000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($result->reason)
        ->toBeNull()
        ->and($result->authorization)
        ->not->toBeNull();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $result->authorization->id())
            ->where('type', TransactionTypeEnum::RESERVE->value)
            ->exists()
    )->toBeTrue();
});

// I9 / A9 — Regra: uma autorização aprovada deve registrar exatamente uma reserva financeira no ledger.
it('records exactly one reservation for an approved authorization', function () {
    $input = new AuthorizeTransactionInput(
        externalId: 'aut_integration_009',
        cardToken: 'tok_ana',
        amount: Money::fromCents(10000),
        currency: 'BRL',
        mcc: '5812',
        merchant: new MerchantInput(
            name: 'Restaurante Teste',
            city: 'Porto Alegre',
            country: 'BR',
        ),
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $useCase = app(AuthorizeTransaction::class);

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($result->authorization)
        ->not->toBeNull();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $result->authorization->id())
            ->where('type', TransactionTypeEnum::RESERVE->value)
            ->count()
    )->toBe(1);
});
