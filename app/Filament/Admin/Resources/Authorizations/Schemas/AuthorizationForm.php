<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Schemas;

use Filament\Schemas\Schema;

final class AuthorizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
