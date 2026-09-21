<?php

namespace App\Filament\Resources\Ozellikler;

use App\Filament\Concerns\YalnizYonetici;
use App\Filament\Resources\Ozellikler\Pages\CreateOzellik;
use App\Filament\Resources\Ozellikler\Pages\EditOzellik;
use App\Filament\Resources\Ozellikler\Pages\ListOzellikler;
use App\Models\Ozellik;
use App\Models\ProductOptionValue;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Ortak özellikler (Beden, Renk…) ve değerleri — WooCommerce'teki
 * Ürünler → Özellikler ekranı. Ürünlerin Varyantlar sekmesi buradan seçer.
 * Bir değerin adı ya da rengi değişirse bütün ürünlerde değişir.
 */
class OzellikResource extends Resource
{
    use YalnizYonetici;

    protected static ?string $model = Ozellik::class;

    protected static ?string $slug = 'ozellikler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Özellikler';

    protected static ?string $modelLabel = 'özellik';

    protected static ?string $pluralModelLabel = 'özellikler';

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('ad')
                        ->label('Özellik adı')
                        ->placeholder('Beden, Renk, Numara…')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(60),

                    Select::make('tur')
                        ->label('Vitrinde gösterim')
                        ->options(Ozellik::TURLER)
                        ->default('text')
                        ->required()
                        ->live(),
                ]),

            Section::make('Değerler')
                ->description('Sürükleyerek sıralayın; vitrinde ve ürün formunda bu sırayla görünür. Ürünlerde kullanılan değer silinemez.')
                ->schema([
                    Repeater::make('degerler')
                        ->hiddenLabel()
                        ->relationship('degerler')
                        ->orderColumn('sira')
                        ->reorderable()
                        ->addActionLabel('Değer ekle')
                        ->grid(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->itemLabel(fn (array $state) => $state['deger'] ?? null)
                        ->columns(fn (Get $get) => $get('tur') === 'color' ? 2 : 1)
                        ->schema([
                            TextInput::make('deger')
                                ->label('Değer')
                                ->required()
                                ->maxLength(40)
                                ->distinct(),

                            ColorPicker::make('renk_kodu')
                                ->label('Renk')
                                ->visible(fn (Get $get) => $get('../../tur') === 'color'),
                        ])
                        // Ürünlerde kullanılan değer silinemez (varyantlar ve renk galerisi ona bağlı)
                        ->deleteAction(fn ($action) => $action->before(function (array $arguments, Repeater $component, $action) {
                            $anahtar = (string) $arguments['item'];
                            $satir = $component->getState()[$anahtar] ?? [];
                            // İlişkili tekrarlayıcı kayıtlı satırları "record-{id}" anahtarıyla tutar
                            $id = str_starts_with($anahtar, 'record-') ? (int) substr($anahtar, 7) : null;

                            if ($id && ProductOptionValue::where('ozellik_degeri_id', $id)->exists()) {
                                Notification::make()
                                    ->title('"'.($satir['deger'] ?? '').'" ürünlerde kullanılıyor')
                                    ->body('Önce ürünlerin Varyantlar sekmesinden çıkarın.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        })),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sira')
            ->reorderable('sira')
            ->modifyQueryUsing(fn ($query) => $query->with('degerler')->withCount('urunEksenleri'))
            ->columns([
                TextColumn::make('ad')->label('Özellik')->weight('medium')->searchable(),

                TextColumn::make('tur')
                    ->label('Gösterim')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'color' ? 'Renk' : 'Yazı')
                    ->color('gray'),

                TextColumn::make('degerler')
                    ->label('Değerler')
                    ->getStateUsing(fn (Ozellik $kayit) => new HtmlString($kayit->degerler
                        ->map(fn ($d) => $d->etiket_html)
                        ->implode('<span style="color:#bbb"> · </span>')))
                    ->html()
                    ->wrap(),

                TextColumn::make('urun_eksenleri_count')
                    ->label('Kullanan ürün')
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make()->label('Sil')
                    ->hidden(fn (Ozellik $kayit) => $kayit->urun_eksenleri_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOzellikler::route('/'),
            'create' => CreateOzellik::route('/create'),
            'edit' => EditOzellik::route('/{record}/edit'),
        ];
    }
}
