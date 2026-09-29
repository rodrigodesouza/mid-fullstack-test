<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Cancellation\DTO\CancelTransactionInput;
use App\Application\Cancellation\UseCases\CancelTransaction;
use App\Application\Capture\DTO\CaptureTransactionInput;
use App\Application\Capture\UseCases\CaptureTransaction;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EventController
{
    public function __construct(
        private CaptureTransaction $captureTransaction,
        private CancelTransaction $cancelTransaction,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'type' => ['required', 'string', 'in:capture,cancellation'],
            'occurred_at' => ['required', 'date'],
            'authorization_id' => ['required', 'string'],

            'amount_cents' => ['required_if:type,capture', 'integer', 'min:1'],
            'currency' => ['required_if:type,capture', 'string', 'size:3'],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'final' => ['required_if:type,capture', 'boolean'],
        ]);

        $occurredAt = new DateTimeImmutable($data['occurred_at']);

        if ($data['type'] === 'capture') {
            $processed = $this->captureTransaction->execute(
                new CaptureTransactionInput(
                    externalId: $data['id'],
                    authorizationId: $data['authorization_id'],
                    amount: Money::fromCents((int) $data['amount_cents']),
                    currency: $data['currency'],
                    occurredAt: $occurredAt,
                    final: (bool) $data['final'],
                    sequence: isset($data['sequence'])
                        ? (int) $data['sequence']
                        : null,
                ),
            );
        } else {
            $processed = $this->cancelTransaction->execute(
                new CancelTransactionInput(
                    externalId: $data['id'],
                    authorizationId: $data['authorization_id'],
                    occurredAt: $occurredAt,
                ),
            );
        }

        return response()->json(
            ['processed' => $processed],
            $processed
                ? Response::HTTP_ACCEPTED
                : Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
