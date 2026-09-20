<?php

namespace App\Filament\Resources\ReturnRequests\Schemas;

use App\Models\ReturnRequest;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * İade / değişim talebi detayı.
 *
 * DİKKAT: `status` ve `type` BİLEREK salt okunur.
 *
 * Üretecin bıraktığı hâlde ikisi de serbest metin kutusuydu; yönetici
 * oraya "approved" yazınca sadece bir dize değişiyor, Returns servisinin
 * durum makinesi atlanıyordu — stok geri gelmiyor, iade tutarı siparişe
 * işlenmiyor, değişim varyantı rezerve edilmiyordu. Yani ekran
 * çalışıyormuş gibi görünüp yanlış iş yapıyordu.
 *
 * Geçişler yalnızca listedeki eylemlerle yapılır; onlar servisi çağırır.
 */
class ReturnRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Talep')
                ->columns(4)
                ->schema([
                    TextInput::make('number')->label('Talep no')->disabled(),

                    TextInput::make('type')
                        ->label('Tür')
                        ->disabled()
                        ->formatStateUsing(fn (?string $state) => $state === 'exchange' ? 'Değişim' : 'İade'),

                    TextInput::make('status')
                        ->label('Durum')
                        ->disabled()
                        ->helperText('Durum yalnızca listedeki eylemlerle değişir.')
                        ->formatStateUsing(fn ($state, ?ReturnRequest $record) => $record?->status_label ?? $state),

                    TextInput::make('reason')
                        ->label('Gerekçe')
                        ->disabled()
                        ->formatStateUsing(fn (?string $state) => match ($state) {
                            'beden' => 'Beden tutmadı',
                            'kusurlu' => 'Kusurlu ürün',
                            'yanlis-urun' => 'Yanlış ürün geldi',
                            'vazgectim' => 'Vazgeçtim',
                            default => $state ?: '—',
                        }),
                ]),

            Section::make('Sipariş')
                ->columns(4)
                ->schema([
                    TextInput::make('order.number')->label('Sipariş no')->disabled(),
                    TextInput::make('order.customer_name')->label('Müşteri')->disabled(),
                    TextInput::make('order.customer_phone')->label('Telefon')->disabled(),

                    TextInput::make('order.delivered_at')
                        ->label('Teslim tarihi')
                        ->disabled()
                        ->helperText('Cayma hakkı bu tarihten başlar.')
                        ->formatStateUsing(fn ($state) => $state ? $state->format('d.m.Y') : '—'),
                ]),

            Section::make('Geri gönderim')
                ->description('Müşterinin ürünü bize gönderdiği kargo.')
                ->columns(2)
                ->schema([
                    TextInput::make('return_carrier')->label('Kargo firması'),
                    TextInput::make('return_tracking_number')->label('Takip numarası'),

                    TextInput::make('shipped_back_at')
                        ->label('Gönderim')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state ? $state->format('d.m.Y H:i') : '—'),

                    TextInput::make('received_at')
                        ->label('Bize ulaştı')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state ? $state->format('d.m.Y H:i') : '—'),
                ]),

            Section::make('Değişim gönderimi')
                ->description('Değişimde müşteriye gönderilen yeni ürünün kargosu.')
                ->columns(2)
                ->visible(fn (?ReturnRequest $record) => (bool) $record?->is_exchange)
                ->schema([
                    TextInput::make('exchange_carrier')->label('Kargo firması'),
                    TextInput::make('exchange_tracking_number')->label('Takip numarası'),
                ]),

            Section::make('Sonuç')
                ->columns(2)
                ->schema([
                    TextInput::make('refund_amount')
                        ->label('İade edilen tutar')
                        ->disabled()
                        ->prefix('₺')
                        ->helperText('Onaylandığında servis hesaplar; elle girilmez.'),

                    TextInput::make('resolved_at')
                        ->label('Sonuçlandı')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state ? $state->format('d.m.Y H:i') : '—'),
                ]),

            Section::make('Notlar')
                ->schema([
                    Textarea::make('customer_note')
                        ->label('Müşteri notu')
                        ->disabled()
                        ->rows(2),

                    Textarea::make('admin_note')
                        ->label('Yönetici notu')
                        ->rows(3)
                        ->helperText('Yalnızca panelde görünür.'),
                ]),
        ]);
    }
}
