<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Services\Faturalar;
use App\Services\OrderShipping;
use App\Services\OrderStock;
use App\Services\PaymentRefunds;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Sipariş')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Müşteri')
                    ->searchable()
                    ->description(fn (Order $kayit) => $kayit->customer_phone),

                TextColumn::make('grand_total')
                    ->label('Tutar')
                    ->money('TRY', locale: 'tr')
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Ödeme')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Bekliyor',
                        'paid' => 'Ödendi',
                        'failed' => 'Başarısız',
                        'refunded' => 'İade edildi',
                        'partially_refunded' => 'Kısmi iade',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded', 'partially_refunded' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Bekliyor',
                        'paid' => 'Ödendi',
                        'preparing' => 'Hazırlanıyor',
                        'shipped' => 'Kargoda',
                        'delivered' => 'Teslim edildi',
                        'cancelled' => 'İptal',
                        'refunded' => 'İade',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'shipped' => 'info',
                        'delivered' => 'success',
                        'cancelled', 'refunded' => 'danger',
                        default => 'gray',
                    }),

                /*
                 * Stok durumu listede görünür: "Rezerve"de takılı kalmış eski
                 * bir sipariş hayalet stok tutuyor demektir. Bu sütun olmasa
                 * o durum ancak müşteri "stokta yok" deyince fark edilirdi.
                 */
                TextColumn::make('stock_state')
                    ->label('Stok')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'none' => 'Yok',
                        'reserved' => 'Rezerve',
                        'committed' => 'Düşüldü',
                        default => $state,
                    })
                    ->color(fn (string $state) => $state === 'reserved' ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('tracking_number')
                    ->label('Takip no')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('invoice_number')
                    ->label('Fatura')
                    ->getStateUsing(fn (Order $kayit) => $kayit->invoice_number
                        ?: ($kayit->payment_status === 'paid' ? 'Kesilmedi' : null))
                    ->badge()
                    ->color(fn (Order $kayit) => $kayit->invoice_number ? 'success' : 'warning')
                    ->description(fn (Order $kayit) => $kayit->invoice_sent_at ? 'Gönderildi' : null)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Ödeme durumu')
                    ->options([
                        'pending' => 'Bekliyor',
                        'paid' => 'Ödendi',
                        'failed' => 'Başarısız',
                        'refunded' => 'İade edildi',
                        'partially_refunded' => 'Kısmi iade',
                    ]),

                SelectFilter::make('status')
                    ->label('Sipariş durumu')
                    ->options([
                        'pending' => 'Bekliyor',
                        'paid' => 'Ödendi',
                        'shipped' => 'Kargoda',
                        'delivered' => 'Teslim edildi',
                        'cancelled' => 'İptal',
                    ]),

                Filter::make('faturasiz')
                    ->label('Faturası kesilmemiş ödenmiş siparişler')
                    ->query(fn ($query) => $query->where('payment_status', 'paid')
                        ->where('status', '!=', 'cancelled')
                        ->whereNull('invoice_number')),

                Filter::make('takili_rezerv')
                    ->label('Takılı rezervler (2 saatten eski)')
                    ->query(fn ($query) => $query->where('stock_state', 'reserved')
                        ->where('created_at', '<', now()->subHours(2))),
            ])
            ->recordActions([
                /*
                 * Fatura muhasebe programında kesilir; burada numarası, tarihi
                 * ve PDF'i siparişe bağlanır. PDF gizli diske (local) yazılır:
                 * kişisel veri içerir, herkese açık klasöre konmaz.
                 */
                Action::make('fatura')
                    ->label(fn (Order $kayit) => $kayit->invoice_number ? 'Fatura' : 'Fatura gir')
                    ->icon('heroicon-o-document-text')
                    ->color(fn (Order $kayit) => $kayit->invoice_number ? 'gray' : 'warning')
                    ->visible(fn (Order $kayit) => $kayit->payment_status === 'paid')
                    ->modalHeading(fn (Order $kayit) => 'Fatura — '.$kayit->number)
                    ->fillForm(fn (Order $kayit) => [
                        'numara' => $kayit->invoice_number,
                        'tarih' => $kayit->invoice_date?->toDateString() ?? now()->toDateString(),
                        'gonder' => ! $kayit->invoice_sent_at,
                    ])
                    ->schema(fn (Order $kayit) => [
                        TextInput::make('numara')
                            ->label('Fatura numarası')
                            ->required()
                            ->maxLength(40)
                            ->unique('orders', 'invoice_number', ignorable: $kayit),
                        DatePicker::make('tarih')
                            ->label('Fatura tarihi')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                        FileUpload::make('pdf')
                            ->label($kayit->invoice_pdf ? 'Yeni PDF (mevcut dosyanın yerine geçer)' : 'Fatura PDF')
                            ->disk('local')
                            ->directory('faturalar')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(5120)
                            ->required(fn () => ! $kayit->invoice_pdf),
                        Toggle::make('gonder')
                            ->label('Müşteriye e-postayla gönder (PDF ekli)')
                            ->helperText($kayit->invoice_sent_at
                                ? 'Daha önce '.$kayit->invoice_sent_at->format('d.m.Y H:i').' tarihinde gönderildi.'
                                : null),
                    ])
                    ->action(function (Order $record, array $data) {
                        $gitti = app(Faturalar::class)->kaydet($record, $data['numara'], $data['tarih'], $data['pdf'] ?? null, (bool) $data['gonder']);

                        Notification::make()
                            ->title('Fatura kaydedildi')
                            ->body($data['gonder'] ? ($gitti ? 'Müşteriye e-postayla gönderildi.' : 'E-posta gönderilemedi — günlüğe bakın.') : null)
                            ->color($data['gonder'] && ! $gitti ? 'warning' : 'success')
                            ->send();
                    }),

                Action::make('faturaIndir')
                    ->label('Fatura PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Order $kayit) => (bool) $kayit->invoice_pdf)
                    ->action(fn (Order $record) => Storage::disk('local')->download(
                        $record->invoice_pdf,
                        'Fatura-'.$record->invoice_number.'.pdf',
                    )),

                Action::make('kargola')
                    ->label('Kargoya ver')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Order $kayit) => $kayit->payment_status === 'paid'
                        && in_array($kayit->status, ['paid', 'preparing'], true))
                    ->schema([
                        TextInput::make('kargo_firmasi')
                            ->label('Kargo firması')
                            ->default(fn () => config('shop.kargo.firma'))
                            ->required(),
                        TextInput::make('takip_no')
                            ->label('Takip numarası')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        try {
                            app(OrderShipping::class)->markShipped(
                                $record,
                                $data['kargo_firmasi'],
                                $data['takip_no'],
                            );
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Sipariş kargoya verildi')->success()->send();
                    }),

                Action::make('teslimEdildi')
                    ->label('Teslim edildi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(
                        'Cayma hakkı süresi BU tarihten başlar ('
                        .config('shop.cayma_hakki_gun').' gün), '
                        .'bu yüzden gerçekten teslim edildiğinde işaretleyin.'
                    )
                    ->visible(fn (Order $kayit) => $kayit->status === 'shipped')
                    ->action(function (Order $record) {
                        try {
                            app(OrderShipping::class)->markDelivered($record);
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Teslim edildi olarak işaretlendi')->success()->send();
                    }),

                Action::make('iptal')->authorize(fn () => Yetki::yonetici())
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Order $kayit) => $kayit->payment_status === 'paid'
                        ? 'Rezerve ya da düşülmüş stok geri verilir. ÖDEME ALINMIŞ: parayı iade etmeyi unutmayın.'
                        : 'Rezerve ya da düşülmüş stok varsa geri verilir.')
                    /*
                     * Ödenmiş siparişi iptal etmek önceden parayı geri VERMİYORDU;
                     * stok dönüyor, para mağazada kalıyordu. Artık iptal ekranında
                     * PayTR iadesi seçilebiliyor (varsayılan açık).
                     */
                    ->schema(fn (Order $kayit) => $kayit->payment_status === 'paid' && app(PaymentRefunds::class)->kullanilabilir()
                        ? [
                            Toggle::make('para_iade')
                                ->label(number_format((float) $kayit->grand_total, 2, ',', '.').' TL PayTR ile karta iade edilsin')
                                ->default(true),
                        ]
                        : [])
                    ->visible(fn (Order $kayit) => ! in_array($kayit->status, ['cancelled', 'delivered'], true))
                    ->action(function (Order $record, array $data) {
                        /*
                         * Önce para: iade başarısız olursa iptal DE yapılmaz —
                         * "iptal edildi ama para mağazada" yarım durumu olmasın.
                         */
                        if (! empty($data['para_iade'])) {
                            try {
                                app(PaymentRefunds::class)->refundOrder($record);
                            } catch (RuntimeException $e) {
                                Notification::make()
                                    ->title('İptal yapılmadı')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }
                        }

                        $stock = app(OrderStock::class);

                        /*
                         * Sipariş hangi aşamadaysa o geçiş çalışır; ötekiler
                         * durum uyuşmadığı için kendiliğinden atlanır.
                         */
                        $stock->restore($record->fresh('items'));
                        $stock->release($record->fresh('items'));

                        $record->update(['status' => 'cancelled']);

                        Notification::make()
                            ->title('Sipariş iptal edildi')
                            ->body(! empty($data['para_iade']) ? 'Tutar PayTR ile iade edildi.' : null)
                            ->success()
                            ->send();
                    }),

                EditAction::make()->label('Detay'),
            ])
            ->emptyStateHeading('Henüz sipariş yok');
    }
}
