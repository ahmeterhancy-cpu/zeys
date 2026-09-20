<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Kod')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('value')
                    ->label('İndirim')
                    ->getStateUsing(fn (Coupon $kayit) => $kayit->type === 'percent'
                        ? rtrim(rtrim(number_format((float) $kayit->value, 2, ',', '.'), '0'), ',').'%'
                        : number_format((float) $kayit->value, 2, ',', '.').' TL')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('min_total')
                    ->label('Alt sepet')
                    ->money('TRY', locale: 'tr')
                    ->placeholder('Sınır yok'),

                TextColumn::make('used_count')
                    ->label('Kullanım')
                    ->getStateUsing(fn (Coupon $kayit) => $kayit->usage_limit
                        ? $kayit->used_count.' / '.$kayit->usage_limit
                        : (string) $kayit->used_count)
                    ->alignCenter(),

                TextColumn::make('ends_at')
                    ->label('Bitiş')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Süresiz')
                    ->sortable(),

                /*
                 * "Etkin" bayrağı tek başına yanıltıcı: kupon etkin olup
                 * süresi dolmuş ya da kotası dolmuş olabilir. Gerçekten
                 * kullanılabilir mi, onu gösteriyoruz.
                 */
                TextColumn::make('kullanilabilir')
                    ->label('Durum')
                    ->badge()
                    ->getStateUsing(function (Coupon $kayit) {
                        if (! $kayit->is_active) {
                            return 'Kapalı';
                        }

                        if ($kayit->starts_at && $kayit->starts_at->isFuture()) {
                            return 'Henüz başlamadı';
                        }

                        if ($kayit->ends_at && $kayit->ends_at->isPast()) {
                            return 'Süresi doldu';
                        }

                        if ($kayit->usage_limit !== null && $kayit->used_count >= $kayit->usage_limit) {
                            return 'Kotası doldu';
                        }

                        return 'Kullanılabilir';
                    })
                    ->color(fn (string $state) => $state === 'Kullanılabilir' ? 'success' : 'danger'),

                IconColumn::make('is_active')
                    ->label('Etkin')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Etkinlik')
                    ->placeholder('Hepsi')
                    ->trueLabel('Etkin')
                    ->falseLabel('Kapalı'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Henüz kupon yok');
    }
}
