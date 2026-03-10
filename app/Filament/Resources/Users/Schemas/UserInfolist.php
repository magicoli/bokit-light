<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.user'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('user.field.name')),
                        TextEntry::make('email')->label(__('user.field.email')),
                        IconEntry::make('is_admin')->label(__('user.field.is_admin'))->boolean(),
                        TextEntry::make('roles')->label(__('user.field.roles'))->badge(),
                    ]),
            ]);
    }
}
