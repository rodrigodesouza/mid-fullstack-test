<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Enums;

enum TransactionTypeEnum: string
{
    case DEPOSIT = 'deposit';
    case RESERVE = 'reserve';
    case RELEASE = 'release';
    case CAPTURE = 'capture';
    case CANCELLATION = 'cancellation';
}
