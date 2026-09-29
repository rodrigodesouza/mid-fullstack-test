<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge(),

                TextEntry::make('amount_cents')
                    ->label('Valor')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            abs($state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    ),

                TextEntry::make('reference')
                    ->label('Referência'),

                TextEntry::make('card.card_token')
                    ->label('Cartão')
                    ->placeholder('-'),

                TextEntry::make('authorization_id')
                    ->label('Authorization')
                    ->placeholder('-'),

                TextEntry::make('event_id')
                    ->label('Evento')
                    ->placeholder('-'),

                TextEntry::make('limit_month')
                    ->label('Mês do limite')
                    ->placeholder('-'),

                TextEntry::make('occurred_at')
                    ->label('Ocorrido em')
                    ->dateTime('d/m/Y H:i:s'),
            ]);
    }
}
