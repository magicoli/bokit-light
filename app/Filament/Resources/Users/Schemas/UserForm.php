<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.user'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('user.field.name'))
                            ->required(),

                        TextInput::make('email')
                            ->label(__('user.field.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label(__('user.field.password'))
                            ->password()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),

                        Toggle::make('is_admin')
                            ->label(__('user.field.is_admin')),
                    ]),
            ]);
    }
}
