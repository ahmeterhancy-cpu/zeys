<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Kullanıcılar';

    protected static ?string $modelLabel = 'kullanıcı';

    protected static ?string $pluralModelLabel = 'kullanıcılar';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Ayarlar';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * Silinebilir mi?
     *
     * Kendini ve SON yöneticiyi silmek yasak: ikisi de paneli kilitler ve
     * geri almanın tek yolu sunucuya erişip komut çalıştırmak olur —
     * SSH'sız cPanel'de bu çok zahmetli.
     */
    public static function silinebilirMi(User $kullanici): bool
    {
        if ($kullanici->id === auth()->id()) {
            return false;
        }

        if ($kullanici->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return false;
        }

        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
