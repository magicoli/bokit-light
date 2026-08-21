<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
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

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    // A user can belong to several properties (property_user, many-to-many) — no single property
    // relationship for Filament to auto-scope by (dev/project-app-panel-tenancy.md). Already
    // admin-only below; unchanged behaviour (every user, not just the current tenant's).
    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('user.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('app.admin');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema)->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * User management is for administrators only.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
