<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Sipariş detayı.
 *
 * Tutarlar, kalemler ve ödeme bilgisi SALT OKUNUR. Sipariş geçmişi
 * muhasebe ve yasal kayıttır; panelden elle değiştirilmemeli. Durum
 * geçişleri listedeki eylemlerle (kargola / teslim / iptal) yapılır,
 * çünkü onlar stok hareketini de yürütüyor.
 *
 * Elle düzenlenebilen tek yer yönetici notu ve kargo takip bilgisi.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Sipariş')
                ->columns(3)
                ->schema([
                    TextInput::make('number')->label('Sipariş no')->disabled(),
                    TextInput::make('created_at')->label('Tarih')->disabled()
                        ->formatStateUsing(fn ($state) => $state?->format('d.m.Y H:i')),
                    TextInput::make('status')->label('Durum')->disabled(),
                ]),

            Section::make('Müşteri')
                ->columns(3)
                ->schema([
                    TextInput::make('customer_name')->label('Ad Soyad')->disabled(),
                    TextInput::make('customer_email')->label('E-posta')->disabled(),
                    TextInput::make('customer_phone')->label('Telefon')->disabled(),

                    Textarea::make('teslimat')
                        ->label('Teslimat adresi')
                        ->disabled()
                        ->rows(3)
                        ->columnSpanFull()
                        ->formatStateUsing(function (?Order $record) {
                            $a = $record?->shipping_address ?? [];

                            return trim(implode("\n", array_filter([
                                $a['name'] ?? null,
                                $a['phone'] ?? null,
                                trim(($a['line1'] ?? '').' '.($a['line2'] ?? '')),
                                trim(($a['district'] ?? '').' / '.($a['city'] ?? '')),
                                $a['postal_code'] ?? null,
                            ])));
                        }),
                ]),

            Section::make('Tutarlar')
                ->columns(4)
                ->schema([
                    TextInput::make('subtotal')->label('Ara toplam')->disabled()->prefix('₺'),
                    TextInput::make('discount_total')->label('İndirim')->disabled()->prefix('₺'),
                    TextInput::make('shipping_total')->label('Kargo')->disabled()->prefix('₺'),
                    TextInput::make('grand_total')->label('Toplam')->disabled()->prefix('₺'),
                    TextInput::make('refunded_total')->label('İade edilen')->disabled()->prefix('₺'),
                    TextInput::make('coupon_code')->label('Kupon')->disabled()->placeholder('—'),
                ]),

            Section::make('Ödeme')
                ->columns(3)
                ->schema([
                    TextInput::make('payment_status')->label('Ödeme durumu')->disabled(),
                    TextInput::make('payment_provider')->label('Sağlayıcı')->disabled()->placeholder('—'),
                    TextInput::make('payment_ref')->label('Ödeme referansı')->disabled()->placeholder('—'),
                    TextInput::make('stock_state')->label('Stok durumu')->disabled(),
                    TextInput::make('paid_at')->label('Ödeme zamanı')->disabled()
                        ->formatStateUsing(fn ($state) => $state?->format('d.m.Y H:i')),
                ]),

            Section::make('Yasal onay')
                ->description('Müşterinin sipariş anında onayladığı metnin sürümü.')
                ->columns(3)
                ->schema([
                    TextInput::make('contract_version')->label('Sözleşme sürümü')->disabled()->placeholder('—'),
                    TextInput::make('contract_accepted_at')->label('Onay zamanı')->disabled()
                        ->formatStateUsing(fn ($state) => $state?->format('d.m.Y H:i')),
                    TextInput::make('contract_ip')->label('IP')->disabled()->placeholder('—'),
                ]),

            Section::make('Kargo')
                ->columns(3)
                ->schema([
                    TextInput::make('shipping_carrier')->label('Kargo firması'),
                    TextInput::make('tracking_number')->label('Takip numarası'),
                    TextInput::make('shipped_at')->label('Kargoya veriliş')->disabled()
                        ->formatStateUsing(fn ($state) => $state?->format('d.m.Y H:i')),
                    TextInput::make('delivered_at')->label('Teslim')->disabled()
                        ->formatStateUsing(fn ($state) => $state?->format('d.m.Y H:i')),
                ]),

            Section::make('Notlar')
                ->schema([
                    Textarea::make('customer_note')
                        ->label('Müşteri notu')
                        ->disabled()
                        ->rows(2)
                        ->visible(fn (Get $get) => filled($get('customer_note'))),

                    Textarea::make('admin_note')
                        ->label('Yönetici notu')
                        ->rows(3)
                        ->helperText('Yalnızca panelde görünür.'),
                ]),
        ]);
    }
}
