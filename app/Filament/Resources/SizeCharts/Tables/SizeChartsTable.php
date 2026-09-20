<?php

namespace App\Filament\Resources\SizeCharts\Tables;

use App\Models\SizeChart;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SizeChartsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tablo')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('sutun_sayisi')
                    ->label('Sütun')
                    ->getStateUsing(fn (SizeChart $kayit) => count($kayit->columns ?? []))
                    ->alignCenter(),

                TextColumn::make('satir_sayisi')
                    ->label('Beden')
                    ->getStateUsing(fn (SizeChart $kayit) => count($kayit->rows ?? []))
                    ->alignCenter(),

                /*
                 * Tutarsız tablo uyarısı: bir satırın hücre sayısı sütun
                 * başlığı sayısından farklıysa ürün sayfasındaki tablo
                 * kayar. Listede görünsün ki fark edilsin.
                 */
                TextColumn::make('tutarli')
                    ->label('Tutarlılık')
                    ->badge()
                    ->getStateUsing(function (SizeChart $kayit) {
                        $sutun = count($kayit->columns ?? []);

                        foreach ($kayit->rows ?? [] as $satir) {
                            if (count((array) $satir) !== $sutun) {
                                return 'Satır/sütun uyuşmuyor';
                            }
                        }

                        return 'Tutarlı';
                    })
                    ->color(fn (string $state) => $state === 'Tutarlı' ? 'success' : 'danger'),

                TextColumn::make('categories_count')
                    ->label('Kategori')
                    ->counts('categories')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Henüz beden tablosu yok')
            ->emptyStateDescription('Kategori bazında tablo ekleyin; ürün sayfasında açılır pencerede gösterilir.');
    }
}
