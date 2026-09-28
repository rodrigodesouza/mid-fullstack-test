<?php

declare(strict_types=1);

use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Models\AuthorizationModel;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(
    RefreshDatabase::class,
)->beforeEach(function () {
    $this->seed();
});

// I19 / A-HTTP1 — Regra: uma autorização válida recebida pela API deve retornar 202.
it('accepts a valid authorization request', function () {
    $response = $this->postJson('/api/network/authorizations', [
        'id' => 'aut_http_001',
        'card_token' => 'tok_ana',
        'amount_cents' => 12990,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ]);

    $response
        ->assertAccepted()
        ->assertJson([
            'decision' => 'approved',
        ])
        ->assertJsonMissingPath('reason');
});

// I20 / A-HTTP2 — Regra: a autorização deve exigir todos os campos obrigatórios do contrato.
it('requires authorization fields', function () {
    $response = $this->postJson('/api/network/authorizations', []);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'id',
            'card_token',
            'amount_cents',
            'currency',
            'mcc',
            'merchant',
            'occurred_at',
        ]);
});

// I21 / A-HTTP3 — Regra: merchant deve conter nome, cidade e país.
it('requires merchant fields', function () {
    $response = $this->postJson('/api/network/authorizations', [
        'id' => 'aut_http_002',
        'card_token' => 'tok_ana',
        'amount_cents' => 12990,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'merchant.name',
            'merchant.city',
            'merchant.country',
        ]);
});

// I22 / A-HTTP4 — Regra: os campos numéricos e formatos definidos pelo contrato devem ser válidos.
it('rejects invalid authorization field formats', function () {
    $response = $this->postJson('/api/network/authorizations', [
        'id' => 'aut_http_003',
        'card_token' => 'tok_ana',
        'amount_cents' => 0,
        'currency' => 'BR',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BRA',
        ],
        'occurred_at' => 'invalid-date',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'amount_cents',
            'currency',
            'merchant.country',
            'occurred_at',
        ]);
});

// I23 / A1 — Regra: uma autorização duplicada deve retornar a mesma decisão sem criar nova reserva.
it('returns the same decision for a duplicated authorization', function () {
    $payload = [
        'id' => 'aut_http_duplicate_001',
        'card_token' => 'tok_ana',
        'amount_cents' => 12990,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ];

    $firstResponse = $this->postJson(
        '/api/network/authorizations',
        $payload
    );

    $firstResponse
        ->assertAccepted()
        ->assertJson([
            'decision' => 'approved',
        ]);

    $authorization = AuthorizationModel::query()
        ->where('external_id', $payload['id'])
        ->firstOrFail();

    $reserveCount = TransactionModel::query()
        ->where('authorization_id', $authorization->id)
        ->where('type', TransactionTypeEnum::RESERVE->value)
        ->count();

    expect($reserveCount)->toBe(1);

    $secondResponse = $this->postJson(
        '/api/network/authorizations',
        $payload
    );

    $secondResponse
        ->assertAccepted()
        ->assertJson([
            'decision' => 'approved',
        ]);

    expect(
        TransactionModel::query()
            ->where('authorization_id', $authorization->id)
            ->where('type', TransactionTypeEnum::RESERVE->value)
            ->count()
    )->toBe(1);
});
