<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Hesap')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Ad Soyad')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('email')
                        ->label('E-posta')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(190),

                    Select::make('role')
                        ->label('Rol')
                        ->options([
                            'customer' => 'Müşteri',
                            'staff' => 'Personel (panele girer, kısıtlı)',
                            'admin' => 'Yönetici (panele girer, tam yetki)',
                        ])
                        ->default('customer')
                        ->required()
                        /*
                         * Kendi rolünü değiştiremezsin: yanlışlıkla "müşteri"
                         * seçip kaydeden yönetici panelden anında atılır ve
                         * geri dönemez.
                         */
                        ->disabled(fn (?User $record) => $record && $record->id === auth()->id())
                        ->helperText(fn (?User $record) => $record && $record->id === auth()->id()
                            ? 'Kendi rolünüzü değiştiremezsiniz.'
                            : 'Personel: sipariş hazırlama, kargo, iade lojistiği, stok, yorum. '
                                .'Para iadesi, fiyat, silme, rapor ve ayarlar yalnız yöneticide. '
                                .'Müşteri yalnızca vitrindeki hesabını kullanır.'),

                    TextInput::make('password')
                        ->label('Parola')
                        ->password()
                        ->revealable()
                        ->rule(Password::min(10))
                        // Yeni kullanıcıda zorunlu, düzenlemede boş bırakılırsa değişmez
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Boş bırakırsanız parola değişmez.'
                            : 'En az 10 karakter.'),
                ]),
        ]);
    }
}
