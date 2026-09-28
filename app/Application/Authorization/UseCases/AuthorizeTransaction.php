<?php

declare(strict_types=1);

namespace App\Application\Authorization\UseCases;

use App\Application\Authorization\DTO\AuthorizationResult;
use App\Application\Authorization\DTO\AuthorizeTransactionInput;
use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Enums\AuthorizationReasonEnum;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Card\Entity\Card;
use App\Domain\Card\Repositories\CardLimitsRepository;
use App\Domain\Card\Repositories\CardRepository;
use App\Domain\Company\Repositories\CompanyBalanceRepository;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;
use App\Domain\User\Entity\User;
use App\Domain\User\Repositories\UserRepository;
use App\Events\AuthorizationApproved;

final class AuthorizeTransaction
{
    public function __construct(
        private readonly AuthorizationRepository $authorizationRepository,
        private readonly CardRepository $cardRepository,
        private readonly CardLimitsRepository $cardLimitsRepository,
        private readonly UserRepository $userRepository,
        private readonly CompanyBalanceRepository $companyBalanceRepository,
        private readonly TransactionRepository $transactionRepository,
    ) {}

    public function execute(
        AuthorizeTransactionInput $input,
    ): AuthorizationResult {
        // A1
        if ($result = $this->findExistingAuthorization($input)) {
            return $result;
        }

        // A2
        $card = $this->findCard($input);

        if ($card === null) {
            return $this->declineCardNotFound();
        }

        // A3
        if ($result = $this->declineIfCardBlocked($card)) {
            return $result;
        }

        // A4
        if ($result = $this->declineIfMccBlocked($input)) {
            return $result;
        }

        // A5
        if ($result = $this->declineIfPurchaseLimitExceeded($input, $card)) {
            return $result;
        }

        // A6
        if ($result = $this->declineIfMonthlyLimitExceeded($input, $card)) {
            return $result;
        }

        $user = $this->userRepository->findById($card->userId());

        // A7
        if ($result = $this->declineIfCompanyBalanceExceeded($input, $user)) {
            return $result;
        }

        // A8
        $authorization = $this->createApprovedAuthorization(
            $input,
            $card,
            $user,
        );

        $reservation = new Transaction(
            id: (string) \Illuminate\Support\Str::uuid(),
            companyId: $user->companyId(),
            cardId: $card->id(),
            authorizationId: $authorization->id(),
            eventId: null,
            type: TransactionTypeEnum::RESERVE,
            amount: $input->amount->negate(),
            occurredAt: $input->occurredAt,
            limitMonth: $input->occurredAt->format('Y-m'),
            reference: $authorization->id(),
        );

        // A9 — Registra uma única reserva no ledger para a autorização aprovada.
        // A10 — Autorização e reserva devem ser confirmadas atomicamente sob concorrência.
        if (! $this->transactionRepository->reserve($authorization, $reservation)) {
            return new AuthorizationResult(
                decision: AuthorizationDecisionEnum::DECLINED,
                reason: AuthorizationReasonEnum::COMPANY_BALANCE_EXCEEDED,
                authorization: null,
            );
        }

        event(new AuthorizationApproved($authorization));

        return new AuthorizationResult(
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            authorization: $authorization,
        );
    }

    /**
     * A1 — Retorna a autorização existente quando o ID externo já foi processado.
     */
    private function findExistingAuthorization(
        AuthorizeTransactionInput $input,
    ): ?AuthorizationResult {
        $authorization = $this->authorizationRepository
            ->findByExternalId($input->externalId);

        if ($authorization === null) {
            return null;
        }

        return new AuthorizationResult(
            decision: $authorization->decision(),
            reason: $authorization->reason(),
            authorization: $authorization,
        );
    }

    /**
     * A2 — Localiza o cartão pelo token recebido pela rede.
     */
    private function findCard(
        AuthorizeTransactionInput $input,
    ): ?Card {
        return $this->cardRepository->findByToken($input->cardToken);
    }

    /**
     * A2 — Retorna a recusa para cartão inexistente.
     */
    private function declineCardNotFound(): AuthorizationResult
    {
        return new AuthorizationResult(
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: AuthorizationReasonEnum::CARD_NOT_FOUND,
            authorization: null,
        );
    }

    /**
     * A3 — Recusa a autorização quando o cartão está bloqueado.
     */
    private function declineIfCardBlocked(
        Card $card,
    ): ?AuthorizationResult {
        if ($card->isActive()) {
            return null;
        }

        return new AuthorizationResult(
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: AuthorizationReasonEnum::CARD_BLOCKED,
            authorization: null,
        );
    }

    /**
     * A4 — Recusa a autorização quando o MCC está bloqueado para o cartão.
     */
    private function declineIfMccBlocked(
        AuthorizeTransactionInput $input,
    ): ?AuthorizationResult {
        if (! $this->isMccBlocked($input->mcc)) {
            return null;
        }

        return new AuthorizationResult(
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: AuthorizationReasonEnum::MCC_BLOCKED,
            authorization: null,
        );
    }

    /**
     * A5 — Recusa a autorização quando o valor excede o limite máximo por compra.
     */
    private function declineIfPurchaseLimitExceeded(
        AuthorizeTransactionInput $input,
        Card $card,
    ): ?AuthorizationResult {
        $purchaseLimit = $this->cardLimitsRepository
            ->purchaseLimitFor($card->id());

        // if (! $input->amount->isGreaterThan($purchaseLimit)) {
        //     return null;
        // }
        if (
            $purchaseLimit === null
            || ! $input->amount->isGreaterThan($purchaseLimit)
        ) {
            return null;
        }

        return new AuthorizationResult(
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED,
            authorization: null,
        );
    }

    /**
     * A6 — Recusa a autorização quando o valor excede o limite mensal restante.
     */
    private function declineIfMonthlyLimitExceeded(
        AuthorizeTransactionInput $input,
        Card $card,
    ): ?AuthorizationResult {
        $remainingLimit = $this->cardLimitsRepository
            ->remainingForMonth(
                $card->id(),
                $input->occurredAt->format('Y-m'),
            );

        if (! $input->amount->isGreaterThan($remainingLimit)) {
            return null;
        }

        return new AuthorizationResult(
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED,
            authorization: null,
        );
    }

    /**
     * A7 — Recusa a autorização quando o valor excede o saldo disponível da empresa.
     */
    private function declineIfCompanyBalanceExceeded(
        AuthorizeTransactionInput $input,
        User $user,
    ): ?AuthorizationResult {
        $availableBalance = $this->companyBalanceRepository
            ->availableBalanceFor($user->companyId());

        if (! $input->amount->isGreaterThan($availableBalance)) {
            return null;
        }

        return new AuthorizationResult(
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: AuthorizationReasonEnum::COMPANY_BALANCE_EXCEEDED,
            authorization: null,
        );
    }

    /**
     * A8 — Cria a autorização aprovada após todas as regras de autorização passarem.
     */
    private function createApprovedAuthorization(
        AuthorizeTransactionInput $input,
        Card $card,
        User $user,
    ): Authorization {
        return new Authorization(
            id: (string) \Illuminate\Support\Str::uuid(),
            externalId: $input->externalId,
            cardId: $card->id(),
            companyId: $user->companyId(),
            amount: $input->amount,
            currency: $input->currency,
            mcc: $input->mcc,
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: $input->merchant->name,
            merchantCity: $input->merchant->city,
            merchantCountry: $input->merchant->country,
            occurredAt: $input->occurredAt,
        );

        // $this->authorizationRepository->save($authorization);

        // return $authorization;
    }

    private function isMccBlocked(string $mcc): bool
    {
        return $mcc === '7995';
    }
}
