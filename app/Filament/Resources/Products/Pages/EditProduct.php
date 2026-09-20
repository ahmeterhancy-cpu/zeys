<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\VariantMatrix;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kombinasyonUret')
                ->label('Kombinasyonları üret')
                ->icon('heroicon-o-squares-plus')
                ->color('primary')
                ->modalHeading('Beden × Renk kombinasyonlarını üret')
                ->modalDescription(
                    'Tanımlı eksenlerin bütün birleşimleri için SKU oluşturulur. '
                    .'HÂLİHAZIRDA VAR OLAN varyantların fiyatı ve stoğu KORUNUR; '
                    .'yalnızca eksik kombinasyonlar eklenir.'
                )
                ->modalSubmitActionLabel('Üret')
                ->schema([
                    TextInput::make('varsayilan_fiyat')
                        ->label('Yeni varyantlar için başlangıç fiyatı (TL)')
                        ->numeric()
                        ->minValue(0)
                        ->default(fn (Product $kayit) => (float) $kayit->min_price ?: 0)
                        ->helperText('Sonradan her satırda ayrı ayrı değiştirilebilir.'),
                ])
                ->action(function (array $data, Product $record) {
                    $record->load('options.values');

                    $eksenler = [];

                    foreach ($record->options as $eksen) {
                        if ($eksen->values->isEmpty()) {
                            continue;
                        }

                        $eksenler[$eksen->name] = [
                            'kind' => $eksen->kind,
                            'values' => $eksen->values->map(fn ($d) => [
                                'value' => $d->value,
                                'color_hex' => $d->color_hex,
                            ])->all(),
                        ];
                    }

                    if ($eksenler === []) {
                        Notification::make()
                            ->title('Önce eksen tanımlayın')
                            ->body('Varyantlar sekmesinde en az bir eksen ve değerleri olmalı.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $oncekiSayi = $record->variants()->count();

                    app(VariantMatrix::class)->generate(
                        $record,
                        $eksenler,
                        (float) ($data['varsayilan_fiyat'] ?? 0)
                    );

                    $yeniSayi = $record->fresh()->variants()->count();
                    $eklenen = $yeniSayi - $oncekiSayi;

                    Notification::make()
                        ->title($eklenen > 0 ? $eklenen.' yeni kombinasyon eklendi' : 'Yeni kombinasyon yok')
                        ->body('Toplam '.$yeniSayi.' varyant. Fiyat ve stokları Varyantlar tablosundan girin.')
                        ->success()
                        ->send();
                }),

            Action::make('vitrindeGor')
                ->label('Vitrinde gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (Product $record) => url('/urun/'.$record->slug))
                ->openUrlInNewTab(),

            DeleteAction::make()->label('Sil'),
        ];
    }
}
