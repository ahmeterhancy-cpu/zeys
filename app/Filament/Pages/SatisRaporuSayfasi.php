<?php

namespace App\Filament\Pages;

use App\Services\SatisRaporu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

/**
 * Satış raporu — tarih aralığı seçilir, özet + günlük seri + en çok
 * satan varyantlar gösterilir, aynısı CSV olarak indirilebilir.
 *
 * Hesap App\Services\SatisRaporu'da; tanımlar (ödeme tarihi esas, iade
 * nasıl sayılır) oradaki açıklamada.
 */
class SatisRaporuSayfasi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Satış Raporu';

    protected static ?string $title = 'Satış Raporu';

    protected static string|\UnitEnum|null $navigationGroup = 'Satış';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'satis-raporu';

    protected string $view = 'filament.pages.satis-raporu';

    public ?array $filtre = [];

    public function mount(): void
    {
        $this->form->fill([
            'baslangic' => now()->startOfMonth()->toDateString(),
            'bitis' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filtre')
            ->columns(2)
            ->components([
                DatePicker::make('baslangic')
                    ->label('Başlangıç')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->live()
                    ->required(),

                DatePicker::make('bitis')
                    ->label('Bitiş')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->live()
                    ->required(),
            ]);
    }

    #[Computed]
    public function rapor(): array
    {
        return app(SatisRaporu::class)->hesapla(
            $this->filtre['baslangic'] ?? now()->startOfMonth()->toDateString(),
            $this->filtre['bitis'] ?? now()->toDateString(),
        );
    }

    /** Hazır aralıklar — en sık sorulanlar tek tıkla. */
    public function aralik(string $ad): void
    {
        [$bas, $son] = match ($ad) {
            'bugun' => [now(), now()],
            'dun' => [now()->subDay(), now()->subDay()],
            '7gun' => [now()->subDays(6), now()],
            'buay' => [now()->startOfMonth(), now()],
            'gecenay' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'buyil' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };

        $this->form->fill(['baslangic' => $bas->toDateString(), 'bitis' => $son->toDateString()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')
                ->label('CSV indir')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $r = $this->rapor();

                    return response()->streamDownload(
                        function () use ($r) {
                            echo app(SatisRaporu::class)->csv($r);
                        },
                        'zeys-satis-'.$r['baslangic']->format('Ymd').'-'.$r['bitis']->format('Ymd').'.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),
        ];
    }
}
