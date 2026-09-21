<?php

namespace App\Filament\Resources\StockInquiries;

use App\Filament\Resources\StockInquiries\Pages\ListStockInquiries;
use App\Models\StockInquiry;
use App\Services\StockAlerts;
use App\Support\Yetki;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Stokta yok — haber ver" kayıtları.
 *
 * SALT OKUNUR liste: kayıtları müşteri açar, panelden oluşturulmaz.
 * Mağaza için asıl değer, "hangi beden kaç kişi tarafından bekleniyor"
 * bilgisi — üretim/sipariş kararına girdi olur.
 */
class StockInquiryResource extends Resource
{
    protected static ?string $model = StockInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Stok Talepleri';

    protected static ?string $modelLabel = 'stok talebi';

    protected static ?string $pluralModelLabel = 'stok talepleri';

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant.product', 'variant.optionValues.option']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('variant.product.name')
                    ->label('Ürün')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (StockInquiry $kayit) => $kayit->variant?->label),

                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->color('gray')
                    ->copyable(),

                /*
                 * Aynı varyantı bekleyen kişi sayısı. Karar verdiren sütun:
                 * "Siyah M'yi 14 kişi bekliyor" bir üretim siparişi demek.
                 */
                TextColumn::make('bekleyen')
                    ->label('Bekleyen')
                    ->getStateUsing(fn (StockInquiry $kayit) => StockInquiry::where('product_variant_id', $kayit->product_variant_id)
                        ->whereNull('notified_at')
                        ->count())
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                TextColumn::make('variant.stock')
                    ->label('Stok')
                    ->alignCenter(),

                TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('created_at')
                    ->label('Kayıt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('notified_at')
                    ->label('Bildirildi')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Bekliyor')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('bildirildi')
                    ->label('Durum')
                    ->placeholder('Hepsi')
                    ->trueLabel('Bildirilenler')
                    ->falseLabel('Bekleyenler')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('notified_at'),
                        false: fn (Builder $query) => $query->whereNull('notified_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                /*
                 * Stok panelden girilince gözlemci zaten bildirim gönderiyor.
                 * Bu düğme, e-posta o an gönderilemediyse (SMTP hatası) elle
                 * yeniden denemek için.
                 */
                Action::make('simdiBildir')
                    ->label('Şimdi bildir')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (StockInquiry $kayit) => $kayit->is_pending
                        && ($kayit->variant?->available_stock ?? 0) > 0)
                    ->action(function (StockInquiry $record) {
                        $gonderilen = app(StockAlerts::class)->notifyIfBack($record->variant);

                        $bildirim = Notification::make();

                        $gonderilen > 0
                            ? $bildirim->title($gonderilen.' kişiye bildirim gönderildi')->success()
                            : $bildirim->title('Gönderilemedi — günlüğe bakın')->danger();

                        $bildirim->send();
                    }),

                DeleteAction::make()->authorize(fn () => Yetki::yonetici())->label('Sil'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize(fn () => Yetki::yonetici())->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Bekleyen stok talebi yok')
            ->emptyStateDescription('Müşteriler tükenen bir beden için "haber ver" dediğinde burada görünür.');
    }

    public static function getNavigationBadge(): ?string
    {
        $bekleyen = StockInquiry::whereNull('notified_at')->count();

        return $bekleyen > 0 ? (string) $bekleyen : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockInquiries::route('/'),
        ];
    }
}
