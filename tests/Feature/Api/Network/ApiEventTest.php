<?php

declare(strict_types=1);

use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Models\AuthorizationModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

uses(
    RefreshDatabase::class,
)->beforeEach(function (): void {
    $this->seed();
});

function networkHeaders(array $payload = []): array
{
    $timestamp = (string) Date::now()->getTimestamp();

    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return [
        'X-Network-Timestamp' => $timestamp,
        'X-Network-Signature' => 'sha256='.hash_hmac(
            'sha256',
            $timestamp.'.'.$body,
            (string) config('services.network.secret'),
        ),
    ];
}

function networkPost(string $url, array $payload): TestResponse
{
    return test()
        ->withHeaders(networkHeaders($payload))
        ->postJson($url, $payload);
}

// Validação
// I15 / V1 — Regra: o evento deve exigir os campos básicos do contrato.
it('requires the event base fields', function (): void {
    $payload = [];

    $response = networkPost('/api/network/events', $payload);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'id',
            'type',
            'occurred_at',
            'authorization_id',
        ]);
});

// I16 / V2 — Regra: capture deve exigir amount_cents, currency e final.
it('requires capture specific fields', function (): void {
    $payload = [
        'id' => 'evt_validation_001',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T18:40:00Z',
        'authorization_id' => 'aut_validation_001',
    ];

    $response = networkPost('/api/network/events', $payload);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'amount_cents',
            'currency',
            'final',
        ]);
});

// I17 / V3 — Regra: cancellation não deve exigir campos exclusivos de capture.
it('does not require capture specific fields for cancellation', function (): void {
    $payload = [
        'id' => 'evt_validation_002',
        'type' => 'cancellation',
        'occurred_at' => '2026-09-17T18:40:00Z',
        'authorization_id' => 'aut_validation_002',
    ];

    $response = networkPost('/api/network/events', $payload);

    $response
        ->assertAccepted()
        ->assertJsonMissingPath('errors.amount_cents')
        ->assertJsonMissingPath('errors.currency')
        ->assertJsonMissingPath('errors.final');
});

// I18 / V4 — Regra: capture pode ser recebido sem sequence.
it('accepts a capture without sequence', function (): void {
    $payload = [
        'id' => 'evt_validation_003',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T18:40:00Z',
        'authorization_id' => 'aut_validation_003',
        'amount_cents' => 30000,
        'currency' => 'BRL',
        'final' => true,
    ];

    $response = networkPost('/api/network/events', $payload);

    $response->assertAccepted();
});

// I24 / X1 — Regra: múltiplas capturas recebidas antes da autorização devem ser processadas quando ela chegar.
it('processes multiple pending captures when their authorization arrives', function (): void {
    $capture1Payload = [
        'id' => 'evt_capture_pending_001',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T14:04:00Z',
        'authorization_id' => 'aut_pending_multiple_001',
        'amount_cents' => 5000,
        'currency' => 'BRL',
        'final' => false,
    ];

    $capture1 = networkPost('/api/network/events', $capture1Payload);

    $capture1->assertAccepted();

    $capture2Payload = [
        'id' => 'evt_capture_pending_002',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T14:05:00Z',
        'authorization_id' => 'aut_pending_multiple_001',
        'amount_cents' => 3000,
        'currency' => 'BRL',
        'final' => true,
    ];

    $capture2 = networkPost('/api/network/events', $capture2Payload);

    $capture2->assertAccepted();

    expect(
        EventModel::query()
            ->where('authorization_reference', 'aut_pending_multiple_001')
            ->where('status', 'pending')
            ->count()
    )->toBe(2);

    $authorizationPayload = [
        'id' => 'aut_pending_multiple_001',
        'card_token' => 'tok_ana',
        'amount_cents' => 10000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ];

    $authorization = networkPost(
        '/api/network/authorizations',
        $authorizationPayload,
    );

    $authorization
        ->assertAccepted()
        ->assertJson([
            'decision' => 'approved',
        ]);

    $authorizationModel = AuthorizationModel::query()
        ->where('external_id', 'aut_pending_multiple_001')
        ->firstOrFail();

    expect(
        EventModel::query()
            ->where('authorization_id', $authorizationModel->id)
            ->where('type', 'capture')
            ->where('status', 'processed')
            ->count()
    )->toBe(2);

    expect(
        (int) TransactionModel::query()
            ->where('authorization_id', $authorizationModel->id)
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents')
    )->toBe(-8000);
});

// I25 / X1 — Regra: capturas pendentes devem ser processadas pela ordem crescente de sequence.
it('processes pending captures in sequence order', function (): void {
    $capture1Payload = [
        'id' => 'evt_sequence_002',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T14:05:00Z',
        'authorization_id' => 'aut_sequence_001',
        'amount_cents' => 3000,
        'currency' => 'BRL',
        'sequence' => 2,
        'final' => true,
    ];

    $capture1 = networkPost('/api/network/events', $capture1Payload);

    $capture1->assertAccepted();

    $capture2Payload = [
        'id' => 'evt_sequence_001',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T14:04:00Z',
        'authorization_id' => 'aut_sequence_001',
        'amount_cents' => 5000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => false,
    ];

    $capture2 = networkPost('/api/network/events', $capture2Payload);

    $capture2->assertAccepted();

    $authorizationPayload = [
        'id' => 'aut_sequence_001',
        'card_token' => 'tok_ana',
        'amount_cents' => 8000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ];

    $authorization = networkPost(
        '/api/network/authorizations',
        $authorizationPayload,
    );

    $authorization->assertAccepted();

    $authorizationModel = AuthorizationModel::query()
        ->where('external_id', 'aut_sequence_001')
        ->firstOrFail();

    $events = EventModel::query()
        ->where('authorization_id', $authorizationModel->id)
        ->where('type', 'capture')
        ->orderBy('sequence')
        ->get();

    expect($events)->toHaveCount(2);

    expect($events[0]->sequence)->toBe(1);
    expect($events[1]->sequence)->toBe(2);

    expect(
        (int) TransactionModel::query()
            ->where('authorization_id', $authorizationModel->id)
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents')
    )->toBe(-8000);
});

// I26 / C6 — Regra: nenhuma captura pode ser processada após uma captura final.
it('rejects a capture after a final capture', function (): void {
    $authorizationPayload = [
        'id' => 'aut_final_001',
        'card_token' => 'tok_ana',
        'amount_cents' => 8000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ];

    $authorization = networkPost(
        '/api/network/authorizations',
        $authorizationPayload,
    );

    $authorization->assertAccepted();

    $finalCapturePayload = [
        'id' => 'evt_final_001',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T14:04:00Z',
        'authorization_id' => 'aut_final_001',
        'amount_cents' => 5000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => true,
    ];

    $finalCapture = networkPost(
        '/api/network/events',
        $finalCapturePayload,
    );

    $finalCapture->assertAccepted();

    $laterCapturePayload = [
        'id' => 'evt_final_002',
        'type' => 'capture',
        'occurred_at' => '2026-09-17T14:05:00Z',
        'authorization_id' => 'aut_final_001',
        'amount_cents' => 1000,
        'currency' => 'BRL',
        'sequence' => 2,
        'final' => false,
    ];

    $laterCapture = networkPost(
        '/api/network/events',
        $laterCapturePayload,
    );

    $laterCapture->assertUnprocessable();
});

// I27 / C1 — Regra: uma autorização pode ser capturada em múltiplos eventos até o limite permitido.
it('accepts multiple partial captures within the allowed amount', function (): void {
    $authorizationPayload = [
        'id' => 'aut_partial_001',
        'card_token' => 'tok_ana',
        'amount_cents' => 80000,
        'currency' => 'BRL',
        'mcc' => '7011',
        'merchant' => [
            'name' => 'Hotel Example',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ];

    $authorization = networkPost(
        '/api/network/authorizations',
        $authorizationPayload,
    );

    $authorization->assertAccepted();

    $captures = [
        [
            'id' => 'evt_partial_001',
            'amount_cents' => 30000,
            'occurred_at' => '2026-09-17T14:04:00Z',
            'sequence' => 1,
            'final' => false,
        ],
        [
            'id' => 'evt_partial_002',
            'amount_cents' => 30000,
            'occurred_at' => '2026-09-17T14:05:00Z',
            'sequence' => 2,
            'final' => false,
        ],
        [
            'id' => 'evt_partial_003',
            'amount_cents' => 26000,
            'occurred_at' => '2026-09-17T14:06:00Z',
            'sequence' => 3,
            'final' => true,
        ],
    ];

    foreach ($captures as $capture) {
        $payload = [
            'id' => $capture['id'],
            'type' => 'capture',
            'occurred_at' => $capture['occurred_at'],
            'authorization_id' => 'aut_partial_001',
            'amount_cents' => $capture['amount_cents'],
            'currency' => 'BRL',
            'sequence' => $capture['sequence'],
            'final' => $capture['final'],
        ];

        $response = networkPost('/api/network/events', $payload);

        $response->assertAccepted();
    }

    $authorizationModel = AuthorizationModel::query()
        ->where('external_id', 'aut_partial_001')
        ->firstOrFail();

    expect(
        (int) TransactionModel::query()
            ->where('authorization_id', $authorizationModel->id)
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents')
    )->toBe(-86000);
});
