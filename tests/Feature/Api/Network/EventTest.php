<?php

declare(strict_types=1);

use App\Application\Authorization\DTO\AuthorizeTransactionInput;
use App\Application\Authorization\DTO\MerchantInput;
use App\Application\Authorization\UseCases\AuthorizeTransaction;
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

// I11 / X1 — Regra: cancelamento sem capture deve liberar toda a reserva.
it('releases the full reservation when an authorization is cancelled', function () {
    $authorizationResult = app(AuthorizeTransaction::class)->execute(
        new AuthorizeTransactionInput(
            externalId: 'aut_cancellation_001',
            cardToken: 'tok_diego',
            amount: Money::fromCents(10000),
            currency: 'BRL',
            mcc: '5812',
            merchant: new MerchantInput(
                name: 'Restaurante Teste',
                city: 'Porto Alegre',
                country: 'BR',
            ),
            occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
        ),
    );

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RESERVE->value)
            ->count()
    )->toBe(1);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RESERVE->value)
            ->value('amount_cents')
    )->toBe(-10000);

    // X1
    $response = $this->postJson('/api/network/events', [
        'id' => 'evt_cancellation_001',
        'type' => 'cancellation',
        'occurred_at' => '2026-09-17T15:03:22Z',
        'authorization_id' => $authorization->externalId(),
    ]);

    $response
        ->assertAccepted()
        ->assertJson([
            'processed' => true,
        ]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->count()
    )->toBe(1);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->value('amount_cents')
    )->toBe(10000);
});

// I12 / X2 — Regra: cancelamento após capture parcial deve liberar somente o restante da reserva.
it('releases only the remaining reservation after a partial capture', function () {
    $authorizationResult = app(AuthorizeTransaction::class)->execute(
        new AuthorizeTransactionInput(
            externalId: 'aut_cancellation_002',
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
        ),
    );

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $captureResponse = $this->postJson('/api/network/events', [
        'id' => 'evt_capture_cancellation_002',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T15:03:22Z',
        'authorization_id' => $authorization->externalId(),
        'amount_cents' => 30000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => false,
    ]);

    $captureResponse
        ->assertAccepted()
        ->assertJson([
            'processed' => true,
        ]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->value('amount_cents')
    )->toBe(-30000);

    $response = $this->postJson('/api/network/events', [
        'id' => 'evt_cancellation_002',
        'type' => 'cancellation',
        'occurred_at' => '2026-09-17T16:03:22Z',
        'authorization_id' => $authorization->externalId(),
    ]);

    $response
        ->assertAccepted()
        ->assertJson([
            'processed' => true,
        ]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->count()
    )->toBe(1);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->value('amount_cents')
    )->toBe(50000);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RESERVE->value)
            ->value('amount_cents')
    )->toBe(-80000);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->value('amount_cents')
    )->toBe(-30000);
});

// I13 / X3 — Regra: cancelamento duplicado não deve produzir nenhum efeito financeiro adicional.
it('does not apply the same cancellation twice', function () {
    $authorizationResult = app(AuthorizeTransaction::class)->execute(
        new AuthorizeTransactionInput(
            externalId: 'aut_cancellation_003',
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
        ),
    );

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $event = [
        'id' => 'evt_cancellation_003',
        'type' => 'cancellation',
        'occurred_at' => '2026-09-17T15:03:22Z',
        'authorization_id' => $authorization->externalId(),
    ];

    $firstResponse = $this->postJson('/api/network/events', $event);

    $firstResponse
        ->assertAccepted()
        ->assertJson([
            'processed' => true,
        ]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->count()
    )->toBe(1);

    $secondResponse = $this->postJson('/api/network/events', $event);

    $secondResponse
        ->assertAccepted()
        ->assertJson([
            'processed' => true,
        ]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->count()
    )->toBe(1);

    expect(
        (int) TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->sum('amount_cents')
    )->toBe(80000);
});

// I14 / X4 — Regra: cancelamento após captura total não deve liberar valor adicional.
it('does not release funds after a fully captured authorization is cancelled', function () {
    $authorizationResult = app(AuthorizeTransaction::class)->execute(
        new AuthorizeTransactionInput(
            externalId: 'aut_cancellation_004',
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
        ),
    );

    expect($authorizationResult->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($authorizationResult->authorization)
        ->not->toBeNull();

    $authorization = $authorizationResult->authorization;

    $captureResponse = $this->postJson('/api/network/events', [
        'id' => 'evt_capture_cancellation_004',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T15:03:22Z',
        'authorization_id' => $authorization->externalId(),
        'amount_cents' => 80000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => true,
    ]);

    $captureResponse
        ->assertAccepted()
        ->assertJson(['processed' => true]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(1);

    // X4
    $response = $this->postJson('/api/network/events', [
        'id' => 'evt_cancellation_004',
        'type' => 'cancellation',
        'occurred_at' => '2026-09-17T16:03:22Z',
        'authorization_id' => $authorization->externalId(),
    ]);

    $response
        ->assertAccepted()
        ->assertJson(['processed' => true]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::RELEASE->value)
            ->count()
    )->toBe(0);

    expect(
        (int) TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents')
    )->toBe(-80000);
});
