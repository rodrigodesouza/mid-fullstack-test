<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Events\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('authorization_id')
                    ->label('Authorization'),

                TextColumn::make('amount_cents')
                    ->label('Valor')
                    ->numeric(),

                TextColumn::make('currency')
                    ->label('Moeda'),

                TextColumn::make('occurred_at')
                    ->label('Ocorrido em')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
