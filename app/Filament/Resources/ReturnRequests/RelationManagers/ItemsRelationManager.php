<?php

namespace App\Filament\Resources\ReturnRequests\RelationManagers;

use App\Models\ReturnRequestItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * İade edilen kalemler.
 *
 * SALT OKUNUR: hangi parçanın kaç adet iade edildiği müşterinin talebidir,
 * panelden değiştirilmemeli. Ekleme/silme eylemleri bilerek yok —
 * değiştirilirse onaylandığında stoğa yanlış adet eklenir.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'İade edilen ürünler';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orderItem.name')
                    ->label('Ürün')
                    ->weight('medium'),

                TextColumn::make('orderItem.variant_label')
                    ->label('Kombinasyon')
                    ->placeholder('—'),

                TextColumn::make('orderItem.sku')
                    ->label('SKU')
                    ->color('gray')
                    ->copyable(),

                TextColumn::make('quantity')
                    ->label('Adet')
                    ->alignCenter(),

                TextColumn::make('orderItem.unit_price')
                    ->label('Birim fiyat')
                    ->money('TRY', locale: 'tr'),

                TextColumn::make('iade_tutari')
                    ->label('Satır tutarı')
                    ->getStateUsing(fn (ReturnRequestItem $kayit) => $kayit->refundValue())
                    ->money('TRY', locale: 'tr'),

                TextColumn::make('exchangeVariant.sku')
                    ->label('Değişim SKU')
                    ->placeholder('—')
                    ->description(fn (ReturnRequestItem $kayit) => $kayit->exchangeVariant?->label)
                    ->color('info'),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->headerActions([])
            ->emptyStateHeading('Kalem yok');
    }
}
