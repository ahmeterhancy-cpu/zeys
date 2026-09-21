<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\Faturalar;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /*
             * Muhasebeciye verilecek döküm: fatura bilgileri (TCKN/VKN, unvan,
             * adres) ve tutarlar. Kişisel veri + ciro içerdiği için yalnız yönetici.
             */
            Action::make('muhasebe')
                ->label('Muhasebe dökümü')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->authorize(fn () => Yetki::yonetici())
                ->modalHeading('Muhasebe dökümü (CSV)')
                ->modalDescription('Seçilen aralıkta ÖDENEN siparişler; fatura bilgileri, tutarlar ve kesilen fatura numaraları. Excel ile açılır.')
                ->modalSubmitActionLabel('İndir')
                ->fillForm(fn () => [
                    'baslangic' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                    'bitis' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                ])
                ->schema([
                    DatePicker::make('baslangic')->label('Başlangıç')->required()->native(false)->displayFormat('d.m.Y'),
                    DatePicker::make('bitis')->label('Bitiş')->required()->native(false)->displayFormat('d.m.Y')->afterOrEqual('baslangic'),
                ])
                ->action(fn (array $data) => response()->streamDownload(
                    function () use ($data) {
                        echo app(Faturalar::class)->muhasebeCsv($data['baslangic'], $data['bitis']);
                    },
                    'zeys-muhasebe-'.str_replace('-', '', substr($data['baslangic'], 0, 10)).'-'.str_replace('-', '', substr($data['bitis'], 0, 10)).'.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                )),

            CreateAction::make(),
        ];
    }
}
