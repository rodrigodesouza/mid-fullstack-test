<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

enum AuthorizationReasonEnum: string
{
    case MCC_BLOCKED = 'mcc_blocked';
    case PURCHASE_LIMIT_EXCEEDED = 'purchase_limit_exceeded';
    case CARD_BLOCKED = 'card_blocked';
    case CARD_NOT_FOUND = 'card_not_found';
    case COMPANY_BALANCE_EXCEEDED = 'company_balance_exceeded';
}
