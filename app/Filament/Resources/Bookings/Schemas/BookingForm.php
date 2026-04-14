<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Property;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__("app.booking"))
                ->columns(4)
                ->columnSpan(2)
                ->schema([
                    Select::make("property_id")
                        ->label(__("booking.field.property_id"))
                        ->options(Property::query()->pluck("name", "id"))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($set) => $set("unit_id", null)),

                    Select::make("unit_id")
                        ->label(__("booking.field.unit_id"))
                        ->options(
                            fn(Get $get) => Unit::query()
                                ->when(
                                    $get("property_id"),
                                    fn($q, $id) => $q->where(
                                        "property_id",
                                        $id,
                                    ),
                                )
                                ->pluck("name", "id"),
                        )
                        ->required()
                        ->live(),

                    TextInput::make("guest_name")
                        ->columnSpan(2)
                        ->label(__("booking.field.guest_name"))
                        ->required(),

                    DatePicker::make("check_in")
                        ->label(__("booking.field.check_in"))
                        ->required()
                        ->native(false),

                    DatePicker::make("check_out")
                        ->label(__("booking.field.check_out"))
                        ->required()
                        ->native(false),
                    Select::make("status")
                        ->label(__("booking.field.status"))
                        ->options(BookingsTable::statusOptions())
                        ->default("confirmed")
                        ->required(),
                ]),

            Section::make(__("booking.section.guests"))
                ->columns(3)
                ->schema([
                    TextInput::make("adults")
                        ->label(__("booking.field.adults"))
                        ->numeric()
                        ->minValue(0),

                    TextInput::make("children")
                        ->label(__("booking.field.children"))
                        ->numeric()
                        ->minValue(0),

                    TextInput::make("guests")
                        ->label(__("booking.field.guests"))
                        ->numeric()
                        ->minValue(0)
                        ->helperText(__("booking.guests_auto_calculated")),
                ]),

            Section::make(__("booking.section.pricing"))
                ->columns(2)
                ->schema([
                    TextInput::make("price")
                        ->label(__("booking.field.price"))
                        ->numeric()
                        ->prefix("€")
                        ->step(0.01),

                    TextInput::make("commission")
                        ->label(__("booking.field.commission"))
                        ->numeric()
                        ->prefix("€")
                        ->step(0.01),
                ]),

            Section::make(__("booking.field.source_name"))
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make("source_name")->label(
                        __("booking.field.source_name"),
                    ),

                    TextInput::make("uid")->label(__("booking.field.uid")),

                    Toggle::make("is_manual")->label(
                        __("booking.field.is_manual"),
                    ),
                ]),

            Section::make(__("booking.field.notes"))
                ->columnSpanFull()
                ->schema([
                    Textarea::make("notes")
                        ->label(__("booking.field.notes"))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
