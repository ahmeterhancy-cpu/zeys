<?php

namespace App\Filament\Resources\LegalDocuments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Yasal metin formu.
 *
 * `version` ve `is_current` BİLEREK salt okunur.
 *
 * Üretecin bıraktığı hâlde `is_current` bir anahtar, `version` serbest
 * metindi. İki sorun çıkarıyordu:
 *
 * 1. Yayımlanmış bir metnin gövdesi YERİNDE düzenlenebiliyordu. Oysa
 *    siparişler `contract_version` ile bir sürüme işaret ediyor; metin
 *    değişirse müşterinin neyi onayladığı kanıtlanamaz hâle gelir —
 *    sürümlemenin bütün amacı budur.
 * 2. Elle `is_current` açılınca aynı slug'da iki yürürlükteki sürüm
 *    oluşabiliyordu; vitrin hangisini göstereceğini bilemezdi.
 *
 * Metin değişikliği "Yeni sürüm yayımla" eylemiyle yapılır; eski sürüm
 * silinmez, yalnızca yürürlükten kalkar.
 */
class LegalDocumentForm
{
    /** @return array<string, string> */
    public static function slugSecenekleri(): array
    {
        return [
            'on-bilgilendirme' => 'Ön Bilgilendirme Formu',
            'mesafeli-satis' => 'Mesafeli Satış Sözleşmesi',
            'iade-degisim' => 'İade ve Değişim Koşulları',
            'kvkk' => 'KVKK Aydınlatma Metni',
            'cerez' => 'Çerez Politikası',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Metin')
                ->columns(3)
                ->schema([
                    Select::make('slug')
                        ->label('Metin türü')
                        ->options(static::slugSecenekleri())
                        ->required()
                        ->disabledOn('edit')
                        ->helperText('Vitrindeki adres bundan türer.'),

                    TextInput::make('version')
                        ->label('Sürüm')
                        ->disabled()
                        ->helperText('Yayımlandığında otomatik verilir.'),

                    TextInput::make('is_current')
                        ->label('Yürürlükte mi')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state ? 'Evet' : 'Hayır'),

                    TextInput::make('title')
                        ->label('Başlık')
                        ->required()
                        ->maxLength(190)
                        ->columnSpanFull(),

                    Textarea::make('body')
                        ->label('Metin (HTML)')
                        ->required()
                        ->rows(22)
                        ->columnSpanFull()
                        ->helperText(
                            'Değişiklik yapıp kaydetmek bu sürümü DEĞİŞTİRİR. '
                            .'Yayımlanmış bir metni güncellemek için üstteki '
                            .'"Yeni sürüm yayımla" düğmesini kullanın.'
                        ),
                ]),
        ]);
    }
}
