<?php

namespace App\Filament\Resources\Rates\Schemas;

use App\Models\Property;
use App\Models\Rate;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__("app.rate"))
                ->columns(6)
                ->schema([
                    Select::make("property_id")
                        ->label(__("rates.field.property_id"))
                        ->options(Property::query()->pluck("name", "id"))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($set) => $set("unit_id", null)),

                    Select::make("unit_id")
                        ->label(__("rates.field.unit_id"))
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
                        ->placeholder(__("rates.all_units")),

                    TextInput::make("name")->label(__("rates.field.name")),

                    TextInput::make("unit_type")->label(
                        __("rates.field.unit_type"),
                    ),

                    TextInput::make("coupon_code")->label(
                        __("rates.field.coupon_code"),
                    ),

                    Select::make("priority")
                        ->label(__("rates.field.priority"))
                        ->options([
                            "high" => __("rates.priority_high"),
                            "normal" => __("rates.priority_normal"),
                            "low" => __("rates.priority_low"),
                        ])
                        ->default("normal"),

                    Toggle::make("is_active")
                        ->label(__("rates.field.is_active"))
                        ->default(true),
                ]),

            Section::make(__("rates.section_pricing"))
                ->columns(4)
                ->schema([
                    TextInput::make("base")
                        ->label(__("rates.field.base"))
                        ->numeric()
                        ->prefix("€")
                        ->step(0.01)
                        ->required(),

                    Select::make("parent_rate_id")
                        ->label(__("rates.parent_rate"))
                        ->options(Rate::query()->pluck("name", "id"))
                        ->placeholder(__("rates.no_parent_rate")),

                    TextInput::make("calculation_formula")
                        ->label(__("rates.field.calculation_formula"))
                        ->default("base * booking_nights")
                        ->required()
                        ->helperText(
                            __("rates.allowed_variables") .
                                ": base, parent_rate, booking_nights, guests, adults, children",
                        )
                        ->columnSpan(2),
                    // ->columnSpanFull(),
                ]),

            Section::make(__("rates.section_dates"))
                ->columns(4)
                ->schema([
                    DatePicker::make("booking_from")
                        ->label(__("rates.field.booking_from"))
                        ->native(false),

                    DatePicker::make("booking_to")
                        ->label(__("rates.field.booking_to"))
                        ->native(false),

                    DatePicker::make("stay_from")
                        ->label(__("rates.field.stay_from"))
                        ->native(false),

                    DatePicker::make("stay_to")
                        ->label(__("rates.field.stay_to"))
                        ->native(false),
                ]),
        ]);
    }
}
