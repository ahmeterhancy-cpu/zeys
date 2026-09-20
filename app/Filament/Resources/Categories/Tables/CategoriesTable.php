<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('image')
                    ->label('Görsel')
                    ->square()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Kategori')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Category $kayit) => $kayit->parent?->name
                        ? 'Üst: '.$kayit->parent->name
                        : null),

                TextColumn::make('products_count')
                    ->label('Ürün')
                    ->counts('products')
                    ->alignCenter(),

                /*
                 * Beden tablosu miras alınabiliyor (ürün > kategori > üst
                 * kategori). Listede hangisinin geçerli olduğunu gösteriyoruz
                 * ki "neden bu kategoride tablo çıkmıyor" sorusu doğmasın.
                 */
                TextColumn::make('beden_tablosu')
                    ->label('Beden tablosu')
                    ->getStateUsing(function (Category $kayit) {
                        if ($kayit->size_chart_id) {
                            return $kayit->sizeChart?->name ?? '—';
                        }

                        $mirasli = $kayit->resolvedSizeChart();

                        return $mirasli ? $mirasli->name.' (üstten)' : 'Yok';
                    })
                    ->color(fn (string $state) => $state === 'Yok' ? 'danger' : 'gray'),

                TextColumn::make('position')
                    ->label('Sıra')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Vitrinde')
                    ->boolean(),

                IconColumn::make('is_featured')
                    ->label('Öne çıkan')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Vitrin durumu')
                    ->placeholder('Hepsi')
                    ->trueLabel('Gösteriliyor')
                    ->falseLabel('Gizli'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Henüz kategori yok');
    }
}
