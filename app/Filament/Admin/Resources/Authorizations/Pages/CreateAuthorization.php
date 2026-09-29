<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Pages;

use App\Filament\Admin\Resources\Authorizations\AuthorizationResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAuthorization extends CreateRecord
{
    protected static string $resource = AuthorizationResource::class;
}
