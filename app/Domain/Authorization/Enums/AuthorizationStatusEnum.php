<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

enum AuthorizationStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DECLINED = 'declined';
    case CAPTURED = 'captured';
    case PARTIALLY_CAPTURED = 'partially_captured';
    case CANCELLED = 'cancelled';
}
