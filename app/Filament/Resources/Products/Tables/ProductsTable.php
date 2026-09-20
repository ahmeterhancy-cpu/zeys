<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('hero_image')
                    ->label('Görsel')
                    ->square()
                    ->defaultImageUrl(asset('img/zeys-logo-sm.png')),

                TextColumn::make('name')
                    ->label('Ürün')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $kayit) => $kayit->base_sku),

                TextColumn::make('collection.name')
                    ->label('Koleksiyon')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('variants_count')
                    ->label('Varyant')
                    ->counts('variants')
                    ->alignCenter(),

                /*
                 * Fiyat aralığı önbellekten okunur (min_price/max_price).
                 * Varyantlardan canlı hesaplansaydı liste her satırda
                 * ayrı sorgu atardı.
                 */
                TextColumn::make('min_price')
                    ->label('Fiyat')
                    ->getStateUsing(function (Product $kayit) {
                        $min = number_format((float) $kayit->min_price, 2, ',', '.');

                        if (! $kayit->has_price_range) {
                            return $min.' TL';
                        }

                        return $min.' – '.number_format((float) $kayit->max_price, 2, ',', '.').' TL';
                    })
                    ->sortable(),

                TextColumn::make('total_stock')
                    ->label('Satılabilir stok')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state < 1 => 'danger',
                        (int) $state <= (int) config('shop.dusuk_stok_esigi') => 'warning',
                        default => 'success',
                    })
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Satışta')
                    ->boolean(),

                IconColumn::make('is_featured')
                    ->label('Öne çıkan')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Eklendi')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('collection_id')
                    ->label('Koleksiyon')
                    ->relationship('collection', 'name')
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Satış durumu')
                    ->placeholder('Hepsi')
                    ->trueLabel('Satışta')
                    ->falseLabel('Satışta değil'),

                Filter::make('stok_bitti')
                    ->label('Stoğu bitenler')
                    ->query(fn ($query) => $query->where('total_stock', '<', 1)),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Henüz ürün yok')
            ->emptyStateDescription('İlk ürünü ekleyin, sonra Beden × Renk kombinasyonlarını üretin.');
    }
}
