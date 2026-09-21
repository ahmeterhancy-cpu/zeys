<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Concerns\YalnizYonetici;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Ana sayfa slaytları ve afişleri.
 *
 * Bir yerde (slayt / üçlü afiş / ikili afiş) yayında kayıt yoksa ana sayfa
 * otomatik içeriğe döner; bu yüzden ekran boşken de site düzgün görünür.
 */
class BannerResource extends Resource
{
    use YalnizYonetici;

    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Slayt ve Afişler';

    protected static ?string $modelLabel = 'slayt / afiş';

    protected static ?string $pluralModelLabel = 'slayt ve afişler';

    protected static string|\UnitEnum|null $navigationGroup = 'Vitrin';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Yer ve görsel')
                ->columns(2)
                ->schema([
                    Select::make('yer')
                        ->label('Nerede görünsün')
                        ->options(Banner::YERLER)
                        ->required()
                        ->live()
                        ->default('slayt'),

                    Select::make('metin_konumu')
                        ->label('Metin kutusu')
                        ->options(['sag' => 'Sağda', 'sol' => 'Solda'])
                        ->default('sag')
                        ->visible(fn (Get $get) => $get('yer') === 'slayt')
                        ->helperText('Görselde yüz/ürün hangi taraftaysa metni öbür tarafa koyun.'),

                    FileUpload::make('gorsel')
                        ->disk('public') // vitrin storage/ altından okur; .env'ye bırakılmaz
                        ->label('Görsel')
                        ->image()
                        ->directory('vitrin')
                        ->maxSize(6144)
                        ->helperText(fn (Get $get) => match ($get('yer')) {
                            'slayt' => 'Önerilen: 1920×800 px, yatay. Boş bırakılırsa sade renkli zemin.',
                            'afis' => 'Önerilen: 900×500 px. Metin solda durur; görselin sol yarısı sade olsun.',
                            default => 'Önerilen: 1300×500 px. Boş bırakılırsa sade renkli zemin.',
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('Metin ve bağlantı')
                ->columns(2)
                ->schema([
                    TextInput::make('ust_metin')
                        ->label('Üst satır (el yazısı)')
                        ->placeholder('Yeni koleksiyon')
                        ->maxLength(60),

                    TextInput::make('baslik')
                        ->label('Başlık')
                        ->required()
                        ->maxLength(80),

                    Textarea::make('alt_metin')
                        ->label('Kısa açıklama')
                        ->rows(2)
                        ->maxLength(300)
                        ->columnSpanFull(),

                    TextInput::make('dugme_metni')
                        ->label('Düğme / bağlantı metni')
                        ->placeholder('Alışverişe başla')
                        ->maxLength(40),

                    TextInput::make('baglanti')
                        ->label('Bağlantı')
                        ->placeholder('/koleksiyon/yaz-26')
                        ->helperText('Sitedeki bir sayfa için "/" ile başlayın; başka site için tam adres (https://…).')
                        ->rule('regex:/^(\/|https?:\/\/)/')
                        ->validationMessages(['regex' => 'Bağlantı "/" ya da "https://" ile başlamalı.'])
                        ->maxLength(255),
                ]),

            Section::make('Yayın')
                ->columns(4)
                ->schema([
                    Toggle::make('aktif')->label('Yayında')->default(true)->inline(false),
                    TextInput::make('sira')->label('Sıra')->numeric()->default(0)->helperText('Küçük olan önce.'),
                    DateTimePicker::make('baslangic')->label('Başlangıç')->seconds(false)->helperText('Boş = hemen'),
                    DateTimePicker::make('bitis')->label('Bitiş')->seconds(false)->helperText('Boş = süresiz')
                        ->afterOrEqual('baslangic'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sira')
            ->reorderable('sira')
            ->columns([
                ImageColumn::make('gorsel')
                    ->disk('public')
                    ->label('Görsel')
                    ->height(56)
                    ->width(110)
                    ->placeholder('—'),

                TextColumn::make('baslik')
                    ->label('Başlık')
                    ->description(fn (Banner $kayit) => $kayit->ust_metin)
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('yer')
                    ->label('Yer')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str(Banner::YERLER[$state] ?? $state)->before(' ('))
                    ->color(fn (string $state) => match ($state) {
                        'slayt' => 'primary',
                        'afis' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('baglanti')->label('Bağlantı')->color('gray')->placeholder('—')->limit(40),

                TextColumn::make('bitis')
                    ->label('Takvim')
                    ->getStateUsing(fn (Banner $kayit) => match (true) {
                        $kayit->bitis && $kayit->bitis->isPast() => 'Süresi doldu',
                        $kayit->baslangic && $kayit->baslangic->isFuture() => $kayit->baslangic->format('d.m.Y H:i').' başlar',
                        (bool) $kayit->bitis => $kayit->bitis->format('d.m.Y H:i').' biter',
                        default => 'Süresiz',
                    })
                    ->color(fn (Banner $kayit) => $kayit->bitis?->isPast() ? 'danger' : 'gray'),

                ToggleColumn::make('aktif')->label('Yayında'),
            ])
            ->filters([
                SelectFilter::make('yer')->label('Yer')->options(Banner::YERLER),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Henüz slayt ya da afiş yok')
            ->emptyStateDescription('Boşken ana sayfa, görselli koleksiyonları slayt ve afiş olarak kendiliğinden gösterir.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
