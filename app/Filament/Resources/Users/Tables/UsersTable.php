<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'admin' ? 'Yönetici' : 'Müşteri')
                    ->color(fn (string $state) => $state === 'admin' ? 'primary' : 'gray'),

                TextColumn::make('orders_count')
                    ->label('Sipariş')
                    ->counts('orders')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Kayıt')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->options(['admin' => 'Yönetici', 'customer' => 'Müşteri']),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (User $kayit) => UserResource::silinebilirMi($kayit))
                    ->modalDescription('Kullanıcının siparişleri silinmez; misafir sipariş olarak kalır.'),
            ])
            ->emptyStateHeading('Kullanıcı yok');
    }
}
