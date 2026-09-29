<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

enum AuthorizationDecisionEnum: string
{
    case APPROVED = 'approved';
    case DECLINED = 'declined';
}
