<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Pages;

use App\Filament\Admin\Resources\Authorizations\AuthorizationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAuthorization extends ViewRecord
{
    protected static string $resource = AuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
