<?php

namespace App\Filament\Resources\EpostaSablonlari;

use App\Filament\Concerns\YalnizYonetici;
use App\Filament\Resources\EpostaSablonlari\Pages\EditEpostaSablonu;
use App\Filament\Resources\EpostaSablonlari\Pages\ListEpostaSablonlari;
use App\Models\EpostaSablonu;
use App\Support\EpostaMetni;
use App\Support\EpostaOnizleme;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Müşteri e-postalarının metinleri. Yapı (sipariş tablosu, düğmeler)
 * sabit; yalnız konu, başlık, ana metin ve not değişir.
 */
class EpostaSablonuResource extends Resource
{
    use YalnizYonetici;

    protected static ?string $model = EpostaSablonu::class;

    protected static ?string $slug = 'eposta-metinleri';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'E-posta Metinleri';

    protected static ?string $modelLabel = 'e-posta metni';

    protected static ?string $pluralModelLabel = 'e-posta metinleri';

    protected static string|\UnitEnum|null $navigationGroup = 'Ayarlar';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $varsayilan = fn (?EpostaSablonu $kayit, string $alan) => $kayit ? (EpostaMetni::SABLONLAR[$kayit->anahtar][$alan] ?? '') : '';

        return $schema->components([
            Section::make('Kullanılabilecek değişkenler')
                ->description('Metne yazdığınız bu ifadeler gönderimde gerçek değerle değişir.')
                ->schema([
                    Text::make(fn (?EpostaSablonu $record) => new HtmlString(collect(EpostaMetni::SABLONLAR[$record?->anahtar]['degiskenler'] ?? [])
                        ->map(fn ($d) => '<code>{'.$d.'}</code> '.e(EpostaMetni::DEGISKEN_ACIKLAMALARI[$d] ?? ''))
                        ->implode(' · '))),
                ]),

            Section::make('Metinler')
                ->description('Boş bırakılan alanda gri görünen varsayılan metin kullanılır. Bir notu tamamen kaldırmak için yalnızca "-" yazın.')
                ->schema([
                    TextInput::make('konu')
                        ->label('Konu satırı')
                        ->placeholder(fn (?EpostaSablonu $record) => $varsayilan($record, 'konu'))
                        ->maxLength(190)
                        ->notIn(['-'])
                        ->validationMessages(['not_in' => 'Konu satırı gizlenemez.']),

                    TextInput::make('baslik')
                        ->label('Başlık')
                        ->placeholder(fn (?EpostaSablonu $record) => $varsayilan($record, 'baslik'))
                        ->maxLength(190)
                        ->notIn(['-'])
                        ->validationMessages(['not_in' => 'Başlık gizlenemez.']),

                    Textarea::make('metin')
                        ->label('Ana metin')
                        ->placeholder(fn (?EpostaSablonu $record) => $varsayilan($record, 'metin'))
                        ->rows(4)
                        ->maxLength(2000),

                    Textarea::make('not')
                        ->label('Alttaki not')
                        ->placeholder(fn (?EpostaSablonu $record) => $varsayilan($record, 'not') ?: '(varsayılanda not yok)')
                        ->rows(3)
                        ->maxLength(1000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->whereIn('anahtar', array_keys(EpostaMetni::SABLONLAR)))
            ->columns([
                TextColumn::make('ad')
                    ->label('E-posta')
                    ->getStateUsing(fn (EpostaSablonu $kayit) => $kayit->ad)
                    ->description(fn (EpostaSablonu $kayit) => EpostaMetni::al($kayit->anahtar)['konu'])
                    ->weight('medium'),

                TextColumn::make('durum')
                    ->label('Metin')
                    ->getStateUsing(fn (EpostaSablonu $kayit) => $kayit->degistirildi_mi ? 'Özelleştirildi' : 'Varsayılan')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Varsayılan' ? 'gray' : 'primary'),

                TextColumn::make('updated_at')
                    ->label('Son değişiklik')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->recordActions([
                static::onizlemeEylemi(),
                EditAction::make()->label('Düzenle'),
            ]);
    }

    public static function onizlemeEylemi(): Action
    {
        return Action::make('onizle')
            ->label('Önizle')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (EpostaSablonu $record) => $record->ad.' — önizleme')
            ->modalDescription('Son siparişin (yoksa örnek verinin) bilgileriyle çizildi. Gönderilmez.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Kapat')
            ->modalWidth('4xl')
            ->modalContent(function (EpostaSablonu $record) {
                try {
                    $html = EpostaOnizleme::html($record->anahtar);
                } catch (Throwable $e) {
                    return new HtmlString('<p>Önizleme çizilemedi: '.e($e->getMessage()).'</p>');
                }

                return new HtmlString('<iframe title="E-posta önizleme" style="width:100%;height:70vh;border:1px solid #e5e5e5;border-radius:8px;background:#fff" srcdoc="'.e($html).'"></iframe>');
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEpostaSablonlari::route('/'),
            'edit' => EditEpostaSablonu::route('/{record}/edit'),
        ];
    }
}
