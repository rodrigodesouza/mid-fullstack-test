<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Pages;

use App\Filament\Admin\Resources\Authorizations\AuthorizationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditAuthorization extends EditRecord
{
    protected static string $resource = AuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
