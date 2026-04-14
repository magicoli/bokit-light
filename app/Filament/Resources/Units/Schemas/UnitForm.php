<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Models\Property;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get as FormsGet;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
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
            Section::make(__("app.unit"))
                ->columns(3)
                ->schema([
                    Select::make("property_id")
                        ->label(__("unit.field.property_id"))
                        ->options(Property::query()->pluck("name", "id"))
                        ->required(),

                    TextInput::make("name")
                        ->label(__("unit.field.name"))
                        ->required(),

                    TextInput::make("slug")
                        ->label(__("unit.field.slug"))
                        ->required(),

                    Textarea::make("description")
                        ->label(__("unit.field.description"))
                        ->rows(4)
                        ->columnSpanFull(),

                    Toggle::make("is_active")
                        ->label(__("unit.field.is_active"))
                        ->default(true),
                ]),

            Section::make(__("unit.field.details"))
                ->columns(6)
                ->schema([
                    TextInput::make("unit_type")->label(
                        __("unit.field.unit_type"),
                    ),

                    TextInput::make("bedrooms")
                        ->label(__("unit.field.bedrooms"))
                        ->numeric()
                        ->minValue(0),

                    TextInput::make("max_guests")
                        ->label(__("unit.field.max_guests"))
                        ->numeric()
                        ->minValue(0),
                ]),

            Section::make(__("unit.section.sources"))
                ->schema([
                    Repeater::make("options.sources")
                        ->label(false)
                        ->table([
                            TableColumn::make(__("unit.field.source_type"))
                                ->width("11rem"),
                            TableColumn::make(__("unit.field.source_config"))
                                ->width(null),
                            TableColumn::make(__("unit.field.source_enabled"))
                                ->width("5rem"),
                        ])
                        ->schema([
                            Select::make("type")
                                ->label(__("unit.field.source_type"))
                                ->hiddenLabel()
                                ->options([
                                    "beds24" => __("unit.source_type.beds24"),
                                    "hbook" => __("unit.source_type.hbook"),
                                    "multipass" => __(
                                        "unit.source_type.multipass",
                                    ),
                                    "ical" => __("unit.source_type.ical"),
                                ])
                                ->required()
                                ->live(),

                            Group::make([
                                TextInput::make("room_id")
                                    ->label(__("unit.field.source_beds24_room_id"))
                                    ->numeric()
                                    ->visible(
                                        fn(FormsGet|SchemaGet $get): bool => $get("type") === "beds24",
                                    ),

                                Select::make("hbook_unit_id")
                                    ->label(__("unit.field.source_hbook_unit"))
                                    ->options(fn () => \Modules\Hbook\HbookServiceProvider::getHbookUnitsFromWordPress(
                                        request()->route('record')
                                    ))
                                    ->visible(
                                        fn(FormsGet|SchemaGet $get): bool => $get("type") === "hbook",
                                    ),

                                Select::make("multipass_unit_id")
                                    ->label(__("unit.field.source_multipass_unit"))
                                    ->options(function () {
                                        $unitId = request()->route('record');
                                        return \Modules\Multipass\MultipassServiceProvider::getMultipassUnitsFromWordPress($unitId);
                                    })
                                    ->visible(
                                        fn(FormsGet|SchemaGet $get): bool => $get("type") === "multipass",
                                    ),

                                TextInput::make("url")
                                    ->label(__("unit.field.source_ical_url"))
                                    ->url()
                                    ->visible(
                                        fn(FormsGet|SchemaGet $get): bool => $get("type") === "ical",
                                    ),

                                TextInput::make("label")
                                    ->label(__("unit.field.source_label"))
                                    ->placeholder(
                                        fn(FormsGet|SchemaGet $get): string => match ($get("type")) {
                                            "ical" => "Airbnb iCal",
                                            default => "",
                                        },
                                    )
                                    ->visible(
                                        fn(FormsGet|SchemaGet $get): bool => $get("type") === "ical",
                                    ),
                            ]),

                            Toggle::make("enabled")
                                ->label(__("unit.field.source_enabled"))
                                ->hiddenLabel()
                                ->default(true)
                                ->inline(),
                        ])
                        ->reorderable()
                        ->reorderAction(
                            fn ($action) => $action->icon('bi-grip-vertical'),
                        )
                        ->addActionLabel(__("unit.action.add_source"))
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ];

        foreach (static::$extensions as $extension) {
            $components = $extension($components);
        }

        return $schema->components($components);
    }
}
