<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Authorization\DTO\AuthorizeTransactionInput;
use App\Application\Authorization\DTO\MerchantInput;
use App\Application\Authorization\UseCases\AuthorizeTransaction;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthorizationController
{
    public function __construct(
        private AuthorizeTransaction $authorizeTransaction,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'card_token' => ['required', 'string'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'mcc' => ['required', 'string'],
            'merchant' => ['required', 'array'],
            'merchant.name' => ['required', 'string'],
            'merchant.city' => ['required', 'string'],
            'merchant.country' => ['required', 'string', 'size:2'],
            'occurred_at' => ['required', 'date'],
        ]);

        $result = $this->authorizeTransaction->execute(
            new AuthorizeTransactionInput(
                externalId: $data['id'],
                cardToken: $data['card_token'],
                amount: Money::fromCents((int) $data['amount_cents']),
                currency: $data['currency'],
                mcc: $data['mcc'],
                merchant: new MerchantInput(
                    name: $data['merchant']['name'],
                    city: $data['merchant']['city'],
                    country: $data['merchant']['country'],
                ),
                occurredAt: new DateTimeImmutable($data['occurred_at']),
            ),
        );

        return response()->json(
            array_filter([
                'decision' => $result->decision->value,
                'reason' => $result->reason?->value,
            ], fn (?string $value) => $value !== null),
            Response::HTTP_ACCEPTED,
        );
    }
}
