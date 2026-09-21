<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\UrunCsv;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('csvDisa')
                    ->label('Fiyat/stok tablosunu indir')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => response()->streamDownload(
                        function () {
                            echo app(UrunCsv::class)->disaAktar();
                        },
                        'zeys-stok-'.now()->format('Y-m-d-Hi').'.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    )),

                Action::make('csvIce')
                    ->label('Fiyat/stok tablosu yükle')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('Fiyat/stok tablosu yükle')
                    ->modalDescription(
                        'İndirdiğiniz tabloyu Excel\'de düzenleyip geri yükleyin. SKU\'ya göre eşleşir; '
                        .'boş bırakılan hücre değiştirilmez. Tek satırda hata varsa hiçbir satır '
                        .'uygulanmaz ve hatalar listelenir. Yeni varyant bu yolla açılmaz.'
                    )
                    ->modalSubmitActionLabel('Yükle ve uygula')
                    ->schema([
                        FileUpload::make('dosya')
                            ->label('CSV dosyası')
                            ->disk('local')
                            ->directory('csv-ice-aktar')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                            ->maxSize(2048)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $yol = $data['dosya'];
                        $sonuc = app(UrunCsv::class)->iceAktar(Storage::disk('local')->get($yol) ?? '');
                        Storage::disk('local')->delete($yol);

                        if ($sonuc['hatalar'] !== []) {
                            $ilk = array_slice($sonuc['hatalar'], 0, 8);
                            $kalan = count($sonuc['hatalar']) - count($ilk);

                            Notification::make()
                                ->title(count($sonuc['hatalar']).' hata — hiçbir değişiklik uygulanmadı')
                                ->body(implode('<br>', array_map('e', $ilk)).($kalan > 0 ? "<br>… ve {$kalan} hata daha" : ''))
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($sonuc['guncellenen'].' varyant güncellendi')
                            ->body($sonuc['degismeyen'] > 0 ? $sonuc['degismeyen'].' satırda değişiklik yoktu.' : null)
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Toplu fiyat/stok')
                ->icon('heroicon-o-table-cells')
                ->button()
                ->color('gray'),

            CreateAction::make(),
        ];
    }
}
