<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class AuthorizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('external_id')
                    ->label('ID externo'),

                TextEntry::make('merchant_name')
                    ->label('Estabelecimento'),

                TextEntry::make('amount_cents')
                    ->label('Valor')
                    ->money('BRL'),

                TextEntry::make('currency')
                    ->label('Moeda'),

                TextEntry::make('decision')
                    ->label('Decisão')
                    ->badge(),

                TextEntry::make('reason')
                    ->label('Motivo')
                    ->placeholder('-'),

                TextEntry::make('occurred_at')
                    ->label('Ocorrido em')
                    ->dateTime('d/m/Y H:i:s'),
            ]);
    }
}
