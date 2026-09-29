<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards;

use App\Filament\Admin\Resources\Cards\Pages\ListCards;
use App\Filament\Admin\Resources\Cards\Pages\ViewCard;
use App\Filament\Admin\Resources\Cards\Schemas\CardInfolist;
use App\Filament\Admin\Resources\Cards\Tables\CardsTable;
use App\Infrastructure\Persistence\Eloquent\Models\CardModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class CardResource extends Resource
{
    protected static ?string $model = CardModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'card_token';

    public static function infolist(Schema $schema): Schema
    {
        return CardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCards::route('/'),
            'view' => ViewCard::route('/{record}'),
        ];
    }
}
