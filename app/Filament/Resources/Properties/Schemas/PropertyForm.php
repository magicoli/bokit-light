<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Models\Property;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * The one schema shared by PropertyResource's own EditProperty page (admin/owner CRUD) and
 * EditTenantProfile (the self-service "this tenant's own settings" page) - a single source of
 * truth so neither loses fields the other has, including whatever modules inject via extend()
 * (dev/project-timezone-and-tenant-settings.md).
 */
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
            // Timezone and logo are first-class property fields, same tier as name/slug (not
            // nested in options) - null means "inherit the app-wide default".
            Select::make('timezone')
                ->label(__('property.field.timezone'))
                ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()
                ->native(false)
                ->placeholder(__('app.default_value', ['value' => Property::defaultTimezone()])),
            FileUpload::make('logo')
                ->label(__('property.field.logo'))
                ->image()
                ->disk('public')
                ->visibility('public')
                ->directory('property-logos'),
            // Same "null inherits the app default" shape as timezone. Both draw from the
            // app-wide list (config('app.locales')), not this property's own enabled subset - the
            // subset is what visitors see in the switcher, the default just needs to be a locale
            // the app supports at all (dev/project-tenant-sub-sites.md).
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
        ];

        foreach (static::$extensions as $extension) {
            $components = $extension($components);
        }

        return $schema->components($components);
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
