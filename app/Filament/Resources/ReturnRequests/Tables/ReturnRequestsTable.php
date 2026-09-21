<?php

namespace App\Filament\Resources\ReturnRequests\Tables;

use App\Models\ReturnRequest;
use App\Services\PaymentRefunds;
use App\Services\Returns;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

/**
 * İade kuyruğu.
 *
 * Bütün durum geçişleri App\Services\Returns üzerinden gider. Doğrudan
 * `status` yazan hiçbir eylem YOK — servis hem sırayı denetliyor
 * (teslim alınmadan onaylanamaz) hem de stok/iade tutarı gibi yan
 * etkileri yürütüyor.
 */
class ReturnRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Talep')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('created_at')
                    ->label('Açılış')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('order.number')
                    ->label('Sipariş')
                    ->searchable(),

                TextColumn::make('order.customer_name')
                    ->label('Müşteri')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'exchange' ? 'Değişim' : 'İade')
                    ->color(fn (string $state) => $state === 'exchange' ? 'info' : 'gray'),

                TextColumn::make('reason')
                    ->label('Gerekçe')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'beden' => 'Beden tutmadı',
                        'kusurlu' => 'Kusurlu',
                        'yanlis-urun' => 'Yanlış ürün',
                        'vazgectim' => 'Vazgeçti',
                        default => $state ?: '—',
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state, ReturnRequest $kayit) => $kayit->status_label)
                    ->color(fn (string $state) => match ($state) {
                        'opened' => 'warning',
                        'awaiting_shipment' => 'warning',
                        'received' => 'info',
                        'approved' => 'success',
                        'completed' => 'success',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('refund_amount')
                    ->label('İade tutarı')
                    ->money('TRY', locale: 'tr')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'opened' => 'Talep alındı',
                        'awaiting_shipment' => 'Kargo bekleniyor',
                        'received' => 'Teslim alındı',
                        'approved' => 'Onaylandı',
                        'rejected' => 'Reddedildi',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal edildi',
                    ]),

                SelectFilter::make('type')
                    ->label('Tür')
                    ->options(['return' => 'İade', 'exchange' => 'Değişim']),

                Filter::make('acik')
                    ->label('Yalnızca açık talepler')
                    ->default()
                    ->query(fn ($query) => $query->whereNotIn('status', ['completed', 'rejected', 'cancelled'])),
            ])
            ->recordActions([

                Action::make('kargoBekleniyor')
                    ->label('Kargo bilgisi verildi')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->visible(fn (ReturnRequest $kayit) => $kayit->status === 'opened')
                    ->action(fn (ReturnRequest $record) => static::yurut(
                        fn () => app(Returns::class)->awaitShipment($record),
                        'Müşteriye kargo bilgisi verildi olarak işaretlendi'
                    )),

                Action::make('geriGonderildi')
                    ->label('Geri gönderildi')
                    ->icon('heroicon-o-truck')
                    ->color('gray')
                    ->visible(fn (ReturnRequest $kayit) => in_array($kayit->status, ['opened', 'awaiting_shipment'], true))
                    ->schema([
                        TextInput::make('kargo')->label('Kargo firması'),
                        TextInput::make('takip')->label('Takip numarası'),
                    ])
                    ->action(fn (ReturnRequest $record, array $data) => static::yurut(
                        fn () => app(Returns::class)->markShippedBack($record, $data['kargo'] ?? null, $data['takip'] ?? null),
                        'Geri gönderim bilgisi kaydedildi'
                    )),

                Action::make('teslimAlindi')
                    ->label('Teslim alındı')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Ürün depoya ulaştı ve incelenmeye hazır.')
                    ->visible(fn (ReturnRequest $kayit) => in_array($kayit->status, ['opened', 'awaiting_shipment'], true))
                    ->action(fn (ReturnRequest $record) => static::yurut(
                        fn () => app(Returns::class)->markReceived($record),
                        'Teslim alındı olarak işaretlendi'
                    )),

                Action::make('onayla')->authorize(fn () => Yetki::yonetici())
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Talebi onayla')
                    ->modalDescription(fn (ReturnRequest $kayit) => $kayit->is_exchange
                        ? 'Geri gelen parçalar stoğa eklenir ve değişim varyantı rezerve edilir. Para iadesi YAPILMAZ.'
                        : 'Geri gelen parçalar stoğa eklenir ve iade tutarı siparişe işlenir.')
                    ->schema([
                        Textarea::make('not')->label('Yönetici notu')->rows(2),
                    ])
                    ->visible(fn (ReturnRequest $kayit) => $kayit->status === 'received')
                    ->action(fn (ReturnRequest $record, array $data) => static::yurut(
                        fn () => app(Returns::class)->approve($record, $data['not'] ?? null),
                        'Talep onaylandı'
                    )),

                Action::make('reddet')->authorize(fn () => Yetki::yonetici())
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Talebi reddet')
                    ->modalDescription('Gerekçe müşteriye iletilebilir olmalı. Reddedilen talep müşterinin iade hakkını TÜKETMEZ.')
                    ->schema([
                        Textarea::make('not')
                            ->label('Ret gerekçesi')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn (ReturnRequest $kayit) => in_array($kayit->status, ['opened', 'awaiting_shipment', 'received'], true))
                    ->action(fn (ReturnRequest $record, array $data) => static::yurut(
                        fn () => app(Returns::class)->reject($record, $data['not']),
                        'Talep reddedildi'
                    )),

                Action::make('paytrIade')->authorize(fn () => Yetki::yonetici())
                    ->label('Parayı PayTR ile iade et')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Parayı müşterinin kartına iade et')
                    ->modalDescription(fn (ReturnRequest $kayit) => number_format((float) $kayit->refund_amount, 2, ',', '.')
                        .' TL, PayTR üzerinden müşterinin ödeme yaptığı karta iade edilecek. '
                        .'Bu işlem geri alınamaz; talep "tamamlandı" olur ve müşteriye e-posta gider.')
                    ->modalSubmitActionLabel('İade et')
                    ->visible(fn (ReturnRequest $kayit) => $kayit->status === 'approved'
                        && ! $kayit->is_exchange
                        && ! $kayit->payment_refunded_at
                        && app(PaymentRefunds::class)->kullanilabilir())
                    ->action(fn (ReturnRequest $record) => static::yurut(
                        fn () => app(PaymentRefunds::class)->refundReturn($record),
                        'Para iade edildi, talep tamamlandı'
                    )),

                Action::make('tamamla')
                    // Değişimi personel kapatabilir; "elle iade ettim" para hareketidir, yalnız yönetici
                    ->authorize(fn (ReturnRequest $kayit) => $kayit->is_exchange || Yetki::yonetici())
                    ->label(fn (ReturnRequest $kayit) => $kayit->is_exchange ? 'Tamamla' : 'Elle iade ettim')
                    ->icon('heroicon-o-flag')
                    ->color(fn (ReturnRequest $kayit) => $kayit->is_exchange ? 'success' : 'gray')
                    ->modalHeading(fn (ReturnRequest $kayit) => $kayit->is_exchange
                        ? 'Değişim ürünü kargolandı'
                        : 'İade tutarı gönderildi')
                    ->modalDescription(fn (ReturnRequest $kayit) => $kayit->is_exchange
                        ? null
                        : 'Parayı PayTR panelinden ya da havaleyle ELLE gönderdiyseniz işaretleyin. '
                            .'Sistem para göndermez; yalnızca talebi kapatır ve müşteriye bildirir.')
                    ->schema(fn (ReturnRequest $kayit) => $kayit->is_exchange ? [
                        TextInput::make('kargo')->label('Kargo firması')->required(),
                        TextInput::make('takip')->label('Takip numarası')->required(),
                    ] : [])
                    ->visible(fn (ReturnRequest $kayit) => $kayit->status === 'approved')
                    ->action(fn (ReturnRequest $record, array $data) => static::yurut(
                        fn () => app(Returns::class)->complete($record, $data['kargo'] ?? null, $data['takip'] ?? null),
                        'Talep tamamlandı'
                    )),

                Action::make('iptal')
                    ->label('İptal')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ReturnRequest $kayit) => $kayit->is_cancellable)
                    ->action(fn (ReturnRequest $record) => static::yurut(
                        fn () => app(Returns::class)->cancel($record),
                        'Talep iptal edildi'
                    )),

                EditAction::make()->label('Detay'),
            ])
            ->emptyStateHeading('Açık iade talebi yok')
            ->emptyStateDescription('Müşteriler sipariş sayfalarından talep açtığında burada görünür.');
    }

    /**
     * Servis çağrısını sarar.
     *
     * Returns servisi sıra dışı bir geçişte RuntimeException fırlatıyor
     * ("teslim alınmadan onaylanamaz" gibi). Yakalanmazsa yöneticiye
     * çirkin bir hata sayfası çıkardı; burada bildirime çevriliyor.
     */
    private static function yurut(callable $islem, string $basari): void
    {
        try {
            $islem();
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($basari)->success()->send();
    }
}
