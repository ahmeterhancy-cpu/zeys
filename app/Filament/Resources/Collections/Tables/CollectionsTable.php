<?php

namespace App\Filament\Resources\Collections\Tables;

use App\Models\Collection;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('image')
                    ->disk('public')
                    ->label('Görsel')
                    ->square()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Koleksiyon')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Collection $kayit) => $kayit->description),

                TextColumn::make('products_count')
                    ->label('Ürün')
                    ->counts('products')
                    ->alignCenter(),

                /*
                 * Vitrinde görünen ürün sayısı toplamdan farklı olabilir
                 * (pasif ürünler sayılmaz). Koleksiyon "boş görünüyor"
                 * şikâyetinin cevabı genelde burada.
                 */
                TextColumn::make('aktif_urun')
                    ->label('Vitrinde')
                    ->getStateUsing(fn (Collection $kayit) => $kayit->products()->where('is_active', true)->count())
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'success' : 'warning')
                    ->alignCenter(),

                TextColumn::make('position')
                    ->label('Sıra')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Etkin')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Vitrin durumu')
                    ->placeholder('Hepsi')
                    ->trueLabel('Gösteriliyor')
                    ->falseLabel('Gizli'),
            ])
            ->recordActions([
                Action::make('vitrindeGor')
                    ->label('Vitrinde gör')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (Collection $kayit) => (bool) $kayit->is_active)
                    ->url(fn (Collection $kayit) => url('/koleksiyon/'.$kayit->slug))
                    ->openUrlInNewTab(),

                EditAction::make()->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Henüz koleksiyon yok');
    }
}
