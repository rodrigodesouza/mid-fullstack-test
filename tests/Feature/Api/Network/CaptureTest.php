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
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
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

    $captureInput = new CaptureTransactionInput(
        externalId: 'cap_capture_001',
        authorizationId: $authorization->externalId(),
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

// // C2 — Regra: uma captura parcial deve registrar o capture e liberar o valor correspondente da reserva.
// it('partially captures an authorization and releases the corresponding reservation', function () {
//     $authorizationInput = new AuthorizeTransactionInput(
//         externalId: 'aut_capture_011',
//         cardToken: 'tok_diego',
//         amount: Money::fromCents(80000),
//         currency: 'BRL',
//         mcc: '5411',
//         merchant: new MerchantInput(
//             name: 'Mercado Teste',
//             city: 'Porto Alegre',
//             country: 'BR',
//         ),
//         occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
//     );

//     $authorizationResult = app(AuthorizeTransaction::class)
//         ->execute($authorizationInput);

//     expect($authorizationResult->decision)
//         ->toBe(AuthorizationDecisionEnum::APPROVED)
//         ->and($authorizationResult->authorization)
//         ->not->toBeNull();

//     $authorization = $authorizationResult->authorization;

//     $capture = app(CaptureTransaction::class);

//     $result = $capture->execute(
//         new CaptureTransactionInput(
//             externalId: 'cap_capture_011_001',
//             authorizationId: $authorization->externalId(),
//             amount: Money::fromCents(30000),
//             currency: 'BRL',
//             occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
//             final: false,
//         ),
//     );

//     expect($result)->toBeTrue();

//     expect(
//         TransactionModel::query()
//             ->where('authorization_id', $authorization->id())
//             ->where('type', TransactionTypeEnum::CAPTURE->value)
//             ->sum('amount_cents')
//     )->toBe('-30000');

//     expect(
//         TransactionModel::query()
//             ->where('authorization_id', $authorization->id())
//             ->where('type', TransactionTypeEnum::RELEASE->value)
//             ->sum('amount_cents')
//     )->toBe('30000');
// });

// C3 — Regra: múltiplos captures parciais devem ser permitidos dentro do limite permitido.
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
            authorizationId: $authorization->externalId(),
            amount: Money::fromCents(30000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: false,
        ),
    );

    $secondCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_002_002',
            authorizationId: $authorization->externalId(),
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

// C9 — Regra: MCC com tolerância de 20% permite captura acima do valor autorizado dentro do limite.
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
            authorizationId: $authorization->externalId(),
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

// C8 — Regra: a captura não pode ultrapassar a autorização acrescida da tolerância permitida.
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
            authorizationId: $authorization->externalId(),
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

// C8 — Regra: a soma das capturas não pode ultrapassar a autorização acrescida da tolerância.
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
            authorizationId: $authorization->externalId(),
            amount: Money::fromCents(90000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: false,
        ),
    );

    $secondCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_005_002',
            authorizationId: $authorization->externalId(),
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

// Regra adicional — após uma captura final, nenhuma nova captura pode ser processada.
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
            authorizationId: $authorization->externalId(),
            amount: Money::fromCents(50000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: true,
        ),
    );

    $secondCapture = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_006_002',
            authorizationId: $authorization->externalId(),
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

// Regra adicional — a captura deve utilizar a mesma moeda da autorização.
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
            authorizationId: $authorization->externalId(),
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

// C7 — Regra: um evento duplicado não pode produzir um segundo efeito financeiro.
it('does not apply a duplicated capture event twice', function () {
    $authorizationResult = app(AuthorizeTransaction::class)->execute(
        new AuthorizeTransactionInput(
            externalId: 'aut_capture_008',
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

    $authorization = $authorizationResult->authorization;

    $capture = app(CaptureTransaction::class);

    $input = new CaptureTransactionInput(
        externalId: 'cap_capture_008_001',
        authorizationId: 'aut_capture_008',
        amount: Money::fromCents(30000),
        currency: 'BRL',
        occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
        final: false,
    );

    $first = $capture->execute($input);
    $second = $capture->execute($input);

    expect($first)->toBeTrue()
        ->and($second)->toBeTrue();

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id())
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->count()
    )->toBe(1);

    expect(
        EventModel::query()
            ->where('external_id', 'cap_capture_008_001')
            ->count()
    )->toBe(1);
});

// C5 — Regra: uma captura recebida antes da autorização deve ficar pendente e não produzir efeito financeiro.
it('stores capture as pending when authorization does not exist yet', function () {
    $capture = app(CaptureTransaction::class);

    $result = $capture->execute(
        new CaptureTransactionInput(
            externalId: 'cap_capture_009_001',
            authorizationId: '00000000-0000-0000-0000-000000000999',
            amount: Money::fromCents(30000),
            currency: 'BRL',
            occurredAt: new DateTimeImmutable('2026-09-17T15:03:22Z'),
            final: false,
        ),
    );

    expect($result)->toBeTrue();

    expect(
        EventModel::query()
            ->where('external_id', 'cap_capture_009_001')
            ->value('status')
    )->toBe('pending');

    expect(
        TransactionModel::query()
            ->where('event_id', function ($query) {
                $query->select('id')
                    ->from('events')
                    ->where('external_id', 'cap_capture_009_001');
            })
            ->count()
    )->toBe(0);
});

// C10 — Regra: MCC sem tolerância não permite captura acima do valor autorizado.
it('does not allow capture above the authorization amount when the mcc has no tolerance', function () {
    $authorizationInput = new AuthorizeTransactionInput(
        externalId: 'aut_capture_010',
        cardToken: 'tok_diego',
        amount: Money::fromCents(80000),
        currency: 'BRL',
        mcc: '5411',
        merchant: new MerchantInput(
            name: 'Mercado Teste',
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
            externalId: 'cap_capture_010_001',
            authorizationId: $authorization->externalId(),
            amount: Money::fromCents(80001),
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
