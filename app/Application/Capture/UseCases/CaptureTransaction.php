<?php

declare(strict_types=1);

namespace App\Application\Capture\UseCases;

use App\Application\Capture\DTO\CaptureTransactionInput;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Authorization\Services\CaptureTolerance;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;

final class CaptureTransaction
{
    public function __construct(
        private readonly AuthorizationRepository $authorizationRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly CaptureTolerance $captureTolerance,
    ) {}

    public function execute(CaptureTransactionInput $input): bool
    {
        $authorization = $this->authorizationRepository
            ->findById($input->authorizationId);

        if ($authorization === null) {
            return false;
        }

        if ($authorization->decision() !== AuthorizationDecisionEnum::APPROVED) {
            return false;
        }

        if ($input->currency !== $authorization->currency()) {
            return false;
        }

        $tolerancePercentage = $this->captureTolerance->percentageFor(
            $authorization->mcc()
        );

        $maximumCaptureAmount = $authorization->amount()->add(
            Money::fromCents(
                intdiv(
                    $authorization->amount()->toCents() * $tolerancePercentage,
                    100
                )
            )
        );

        $capturedAmount = $this->transactionRepository
            ->capturedAmountForAuthorization($authorization->id());

        $totalCapturedAmount = $capturedAmount->add($input->amount);

        if ($totalCapturedAmount->isGreaterThan($maximumCaptureAmount)) {
            return false;
        }

        if ($input->amount->isGreaterThan($maximumCaptureAmount)) {
            return false;
        }

        $capture = new Transaction(
            id: (string) \Illuminate\Support\Str::uuid(),
            companyId: $authorization->companyId(),
            cardId: $authorization->cardId(),
            authorizationId: $authorization->id(),
            eventId: null,
            type: TransactionTypeEnum::CAPTURE,
            amount: $input->amount->negate(),
            occurredAt: $input->occurredAt,
            limitMonth: $input->occurredAt->format('Y-m'),
            reference: $input->externalId,
        );

        $this->transactionRepository->save($capture);

        return true;
    }
}
