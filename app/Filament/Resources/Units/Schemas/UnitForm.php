<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Models\Property;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    /**
     * Additional schema components registered by modules.
     *
     * @var array<callable(array): array>
     */
    protected static array $extensions = [];

    /**
     * Register a callback that can append components to the unit form.
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
            Section::make(__('app.unit'))
                ->columns(2)
                ->schema([
                    Select::make('property_id')
                        ->label(__('unit.field.property_id'))
                        ->options(Property::query()->pluck('name', 'id'))
                        ->required(),

                    TextInput::make('name')
                        ->label(__('unit.field.name'))
                        ->required(),

                    TextInput::make('slug')
                        ->label(__('unit.field.slug'))
                        ->required(),

                    TextInput::make('unit_type')
                        ->label(__('unit.field.unit_type')),

                    TextInput::make('bedrooms')
                        ->label(__('unit.field.bedrooms'))
                        ->numeric()
                        ->minValue(0),

                    TextInput::make('max_guests')
                        ->label(__('unit.field.max_guests'))
                        ->numeric()
                        ->minValue(0),

                    Toggle::make('is_active')
                        ->label(__('unit.field.is_active'))
                        ->default(true),
                ]),

            Section::make(__('unit.field.description'))
                ->schema([
                    Textarea::make('description')
                        ->label(__('unit.field.description'))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ];

        foreach (static::$extensions as $extension) {
            $components = $extension($components);
        }

        return $schema->components($components);
    }
}
