<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PropertyForm
{
    /**
     * Additional schema components registered by modules.
     *
     * @var array<callable(array): array>
     */
    protected static array $extensions = [];

    /**
     * Register a callback that can append components to the property form.
     * Called by module ServiceProviders during boot.
     *
     * @param  callable(array): array  $callback  Receives the components array, returns the modified array.
     */
    public static function extend(callable $callback): void
    {
        static::$extensions[] = $callback;
    }

    public static function configure(Schema $schema): Schema
    {
        $components = [
            TextInput::make('slug')
                ->required(),
            TextInput::make('name')
                ->label(__('property.field.name'))
                ->required(),
            Toggle::make('is_active')
                ->required(),
        ];

        foreach (static::$extensions as $extension) {
            $components = $extension($components);
        }

        return $schema->components($components);
    }
}
