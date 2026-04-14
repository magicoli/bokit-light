<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Models\Property;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get as FormsGet;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
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
                ->description(__("unit.section.sources_description"))
                ->columns(1)
                ->schema([
                    Repeater::make("options.sources")
                        ->label(false)
                        ->schema([
                            Select::make("type")
                                ->label(__("unit.field.source_type"))
                                ->options([
                                    "beds24" => __("unit.source_type.beds24"),
                                    "hbook" => __("unit.source_type.hbook"),
                                    "multipass" => __(
                                        "unit.source_type.multipass",
                                    ),
                                    "ical" => __("unit.source_type.ical"),
                                ])
                                ->required()
                                ->live()
                                ->columnSpan(1),

                            TextInput::make("room_id")
                                ->label(__("unit.field.source_beds24_room_id"))
                                ->numeric()
                                ->visible(
                                    fn(FormsGet|SchemaGet $get): bool => $get(
                                        "type",
                                    ) === "beds24",
                                )
                                ->columnSpan(1),

                            TextInput::make("url")
                                ->label(__("unit.field.source_ical_url"))
                                ->url()
                                ->visible(
                                    fn(FormsGet|SchemaGet $get): bool => $get(
                                        "type",
                                    ) === "ical",
                                )
                                ->columnSpan(2),

                            TextInput::make("label")
                                ->label(__("unit.field.source_label"))
                                ->placeholder(
                                    fn(
                                        FormsGet|SchemaGet $get,
                                    ): string => match ($get("type")) {
                                        "ical" => "Airbnb iCal",
                                        default => "",
                                    },
                                )
                                ->visible(
                                    fn(FormsGet|SchemaGet $get): bool => $get(
                                        "type",
                                    ) === "ical",
                                )
                                ->columnSpan(1),

                            Toggle::make("enabled")
                                ->label(__("unit.field.source_enabled"))
                                ->default(true)
                                ->columnSpan(1),
                        ])
                        ->columns(4)
                        ->reorderable()
                        ->addActionLabel(__("unit.action.add_source"))
                        ->defaultItems(0)
                        ->itemLabel(
                            fn(array $state): string => match (
                                $state["type"] ?? ""
                            ) {
                                "beds24" => "Beds24" .
                                    (!empty($state["room_id"])
                                        ? " (room #{$state["room_id"]})"
                                        : ""),
                                "hbook" => "HBook",
                                "multipass" => "Multipass",
                                "ical" => "iCal" .
                                    (!empty($state["label"])
                                        ? " — {$state["label"]}"
                                        : ""),
                                default => "Source",
                            },
                        )
                        ->collapsible()
                        ->collapsed()
                        ->orderable()
                        ->grid(["default" => 1])
                        ->columnSpanFull(),
                ]),
        ];

        foreach (static::$extensions as $extension) {
            $components = $extension($components);
        }

        return $schema->components($components);
    }
}
