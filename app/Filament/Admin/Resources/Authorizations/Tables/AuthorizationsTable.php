<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AuthorizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_id')
                    ->label('ID externo')
                    ->searchable(),

                TextColumn::make('decision')
                    ->label('Decisão')
                    ->badge(),

                TextColumn::make('amount_cents')
                    ->label('Valor')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            $state / 100,
                            2,
                            ',',
                            '.',
                        ),
                    ),

                TextColumn::make('currency')
                    ->label('Moeda'),

                TextColumn::make('mcc')
                    ->label('MCC'),

                TextColumn::make('card.card_token')
                    ->label('Cartão')
                    ->placeholder('-'),

                TextColumn::make('occurred_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s'),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
