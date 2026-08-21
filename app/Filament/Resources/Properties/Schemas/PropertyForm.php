<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Models\Property;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * The one schema shared by PropertyResource's own EditProperty page (admin/owner CRUD) and
 * EditTenantProfile (the self-service "this tenant's own settings" page) - a single source of
 * truth so neither loses fields the other has, including whatever modules inject via extend()
 * (dev/project-timezone-and-tenant-settings.md).
 *
 * Identity fields live in their own Tab; each module gets its own Tab too (Beds24, WordPress
 * connector...) rather than everything piling into one long scrolling form.
 */
class PropertyForm
{
    /**
     * Additional tabs registered by modules.
     *
     * @var array<callable(array<int, Tab>): array<int, Tab>>
     */
    protected static array $extensions = [];

    /**
     * Register a callback that can append a tab to the property form.
     * Called by module ServiceProviders during boot.
     *
     * @param  callable(array<int, Tab>): array<int, Tab>  $callback  Receives the array of tabs so
     *                                                                far, returns it with its own
     *                                                                Tab::make(...) appended.
     */
    public static function extend(callable $callback): void
    {
        static::$extensions[] = $callback;
    }

    public static function configure(Schema $schema): Schema
    {
        $tabs = [
            Tab::make(__('property.tab.identity.label'))
                ->schema([
                    Toggle::make('is_active')
                        ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                    TextInput::make('name')
                        ->label(__('property.field.name'))
                        ->required(),
                    TextInput::make('slug')
                        ->label(__('app.slug'))
                        ->required(),
                    FileUpload::make('logo')
                        ->label(__('property.field.logo'))
                        ->image()
                        ->disk('public')
                        ->visibility('public')
                        ->directory('property-logos'),
                    FileUpload::make('avatar_url')
                        ->label(__('property.field.icon'))
                        ->image()
                        ->avatar()
                        ->disk('public')
                        ->visibility('public')
                        ->directory('avatars'),
                    Select::make('timezone')
                        ->label(__('property.field.timezone'))
                        ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->searchable()
                        ->native(false)
                        ->placeholder(__('app.default_value', ['value' => Property::defaultTimezone()])),
                    Select::make('locale')
                        ->label(__('property.field.locale'))
                        ->options(static::localeOptions(config('app.locales', [config('app.default_locale', 'en')])))
                        ->native(false)
                        // config('app.default_locale'), NOT config('app.locale') - the latter is
                        // overwritten per-request by whichever locale the CURRENT viewer is seeing
                        // (Property::locale()'s own docblock explains why).
                        ->placeholder(__('app.default_value', ['value' => static::localeLabel(config('app.default_locale', 'en'))])),
                    Select::make('locales')
                        ->label(__('property.field.locales'))
                        ->helperText(__('property.field.locales_description'))
                        ->options(static::localeOptions(config('app.locales', [config('app.locale', 'en')])))
                        ->multiple()
                        ->native(false)
                        ->placeholder(__('app.default_value', ['value' => __('property.field.locales_all')])),
                ]),
        ];

        foreach (static::$extensions as $extension) {
            $tabs = $extension($tabs);
        }

        return $schema
            ->components([
                Tabs::make()->tabs($tabs),
            ])
            ->inlineLabel();
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, string>
     */
    protected static function localeOptions(array $codes): array
    {
        return array_combine($codes, array_map(static::localeLabel(...), $codes));
    }

    protected static function localeLabel(string $code): string
    {
        return str(locale_get_display_name(locale: $code, displayLocale: $code))->title()->toString();
    }
}
