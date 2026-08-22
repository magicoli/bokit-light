<?php

namespace App\Providers;

use App\Backup\Console\Commands\BackupCommand;
use App\Backup\Console\Commands\CleanBackupsCommand;
use App\Backup\Console\Commands\ListBackupsCommand;
use App\Filament\Profile\TimezoneForm;
use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Sync\Ical\BookingSyncIcal;
use App\Sync\Ical\IcalConnector;
use App\Sync\SyncRegistry;
use BezhanSalleh\LanguageSwitch\Enums\Placement;
use BezhanSalleh\LanguageSwitch\Enums\TriggerStyle;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Backup\Commands\BackupCommand as SpatieBackupCommand;
use Spatie\Backup\Commands\CleanupCommand as SpatieCleanupCommand;
use Spatie\Backup\Commands\ListCommand as SpatieListCommand;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The backup commands are replaced, not doubled: whichever registration path resolves
        // them — the package's own, or this application's command directory — lands on the version
        // that knows about the two destinations. `backup:run` and `bokit:backup` are then one
        // command under two names, which is the only way they can be relied on to agree.
        $this->app->bind(SpatieBackupCommand::class, BackupCommand::class);
        $this->app->bind(SpatieCleanupCommand::class, CleanBackupsCommand::class);
        $this->app->bind(SpatieListCommand::class, ListBackupsCommand::class);

        $this->app->singleton(SyncRegistry::class);

        $this->registerModules();
    }

    /**
     * Auto-discover and register module service providers from modules/ directory.
     *
     * Convention: modules/{name}/src/{Name}ServiceProvider.php
     * Namespace:  Modules\{Name}\{Name}ServiceProvider
     */
    private function registerModules(): void
    {
        foreach (glob(base_path('modules/*/src/*ServiceProvider.php')) as $path) {
            $parts = explode('/', str_replace(base_path('/'), '', $path));
            // parts: ['modules', '{name}', 'src', '{Class}.php']
            // Convert hyphenated names to PascalCase: wp-connector → WpConnector
            $moduleName = str_replace('-', '', ucwords($parts[1], '-'));
            $className = basename($path, '.php');
            $class = "Modules\\{$moduleName}\\{$className}";

            if (class_exists($class)) {
                $this->app->register($class);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // filament-edit-profile registers each of its own custom-component candidates the same
        // way (FilamentEditProfileServiceProvider::boot()) - without this, TimezoneForm mounts
        // fine (its first render doesn't need a registered name) but any follow-up Livewire
        // round-trip (fillForm, wire:submit...) fails with "Invalid Livewire snapshot structure",
        // since Livewire has no name to resolve the class by for that second request.
        Livewire::component('timezone_form', TimezoneForm::class);

        $this->registerSourceConnectors();
        $this->ensureConfigIsLoaded();
        $this->createStorageStructure();
        $this->registerGates();
        $this->registerObservers();
        $this->registerRateLimiters();
        $this->configureLanguageSwitch();
        $this->configureFilamentTableDefaults();

        // LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
        //     $switch->locales(config('app.locales', ['en']));
        // });
    }

    /**
     * Rate limit for the public pages, which anyone — including a scanner — can reach.
     *
     * Every rejection is logged with what is needed to judge whether it was deserved: the URL, the
     * address, the agent. The point is not only to limit; it is to find out whether the limit ever
     * fires on ordinary use, which would say something about the pages rather than about the
     * visitor.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by(
            $request->ip(),
        )->response(function (Request $request) {
            Log::warning('[Throttle] Public rate limit reached', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
                'route' => $request->route()?->getName(),
            ]);

            return response()->view('errors.429', [], 429);
        }));
    }

    /**
     * Register core source connectors into the SyncRegistry.
     *
     * Module-specific connectors (Beds24, etc.) are registered by their own
     * ServiceProviders. Only connectors that belong to the core app live here.
     */
    private function registerSourceConnectors(): void
    {
        /** @var SyncRegistry $registry */
        $registry = $this->app->make(SyncRegistry::class);
        $registry->register(new IcalConnector($this->app->make(BookingSyncIcal::class)));
    }

    /**
     * Register model observers
     */
    private function registerObservers(): void
    {
        Booking::observe(BookingObserver::class);
    }

    /**
     * Register authorization gates
     */
    private function registerGates(): void
    {
        // Admin gate - access to admin area
        // Super admins have full access, property managers have limited access
        Gate::define('app', function ($user) {
            if (! $user) {
                return false;
            }

            // Super admins have full access
            if ($user->isAdmin() || $user->hasRole('manager')) {
                return true;
            }

            // Property managers have access to admin area (but some sections may be restricted)
            return $user->hasRole('property_manager');
        });

        // Manage resource gate - admin or owner
        Gate::define('manage-resource', function ($user, $resource) {
            if (! $user) {
                return false;
            }

            // Admins can manage everything
            if ($user->isAdmin()) {
                return true;
            }

            // Owner can manage their own resources
            if (method_exists($resource, 'isOwnedBy')) {
                return $resource->isOwnedBy($user);
            }

            // Fallback to owner_id check
            return isset($resource->owner_id) && $resource->owner_id === $user->id;
        });

        // Manage gate - check if user can manage a model class or instance
        // This is for GLOBAL management rights - only admins and managers
        Gate::define('manage', function ($user, $modelClass = null) {
            if (! $user) {
                return false;
            }

            // Super admins can manage everything
            if ($user->isAdmin()) {
                return true;
            }

            // Convert short class names to full class names
            if (is_string($modelClass) && ! class_exists($modelClass)) {
                $shortName = ucfirst($modelClass);
                $fullClass = "App\\Models\\{$shortName}";

                if (class_exists($fullClass)) {
                    $modelClass = $fullClass;
                } else {
                    return false;
                }
                // Global managers can manage everything
                if ($user->hasRole('manager')) {
                    return true;
                }
            }

            // Property managers do NOT have global manage rights
            return false;
        });

        // Property manager gate - check if user has property_manager role
        // This is a ROLE check, not a permission check
        // Ownership filtering happens in controllers/queries
        Gate::define('property_manager', function ($user) {
            if (! $user) {
                return false;
            }

            // Super admins always have access
            if ($user->isAdmin() || $user->hasRole('manager')) {
                return true;
            }

            // Check if user has property_manager role
            return $user->hasRole('property_manager');
        });

        // Booking manager gate - check if user has booking_manager role
        // This is a ROLE check, not a permission check
        // Ownership filtering happens in controllers/queries
        Gate::define('booking_manager', function ($user) {
            if (! $user) {
                return false;
            }

            // Super admins always have access
            if ($user->isAdmin() || $user->hasRole('manager')) {
                return true;
            }

            // Check if user has booking_manager role
            return $user->hasRole('booking_manager');
        });
    }

    /**
     * Ensure configuration is loaded before creating storage structure
     */
    private function ensureConfigIsLoaded(): void
    {
        // Set view compiled path if not already set
        $viewCompiledPath = storage_path('framework/views');
        if (! Config::has('view.compiled') || empty(Config::get('view.compiled'))) {
            Config::set('view.compiled', $viewCompiledPath);
        }

        // Do NOT rebind blade.compiler here — overriding the singleton with a
        // factory binding would cause new BladeCompiler instances to be created
        // on each resolution, losing all directives registered by Filament,
        // Livewire, etc. The config set above is sufficient.
    }

    /**
     * Create the storage directory structure
     */
    private function createStorageStructure(): void
    {
        // Get paths from configuration
        $directories = [
            Config::get('filesystems.disks.public.root'),
            Config::get('filesystems.disks.local.root'),
            Config::get('cache.stores.file.path'),
            Config::get('session.files'),
            dirname(Config::get('logging.channels.single.path')),
            dirname(Config::get('database.connections.sqlite.database')),
            Config::get('options.path'),
        ];

        // Create directories
        foreach ($directories as $dir) {
            if (! empty($dir) && ! is_dir($dir)) {
                try {
                    mkdir($dir, 0755, true);
                    Log::notice("Created directory {$dir}");
                } catch (\Exception $e) {
                    Log::error("Failed to create directory {$dir}: {$e->getMessage()}");
                }
            }
        }

        // Create files
        $files = [
            Config::get('logging.channels.single.path'),
            Config::get('database.connections.sqlite.database'),
        ];

        foreach ($files as $file) {
            if (! file_exists($file)) {
                try {
                    touch($file);
                    chmod($file, 0644);
                    Log::notice("Created file {$file}");
                } catch (\Exception $e) {
                    Log::error("Failed to create file {$file}: {$e->getMessage()}");
                }
            }
        }
    }

    protected function configureLanguageSwitch(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                // The current tenant's own enabled subset (e.g. Gîtes Mosaïques offering only
                // en/fr/de out of the app-wide list) - Filament::getTenant() is null outside a
                // tenant-scoped panel, falling through to the full app-wide list there
                // (dev/project-tenant-sub-sites.md).
                ->locales(fn (): array => Filament::getTenant()?->availableLocales()
                    ?? config('app.locales', [config('app.default_locale', 'en')]))
                ->renderHook(
                    fn () => auth()->user() ? PanelsRenderHook::USER_MENU_PROFILE_AFTER : PanelsRenderHook::USER_MENU_AFTER
                )
                // ->visible(outsidePanels: true)
                // ->outsidePanelPlacement(Placement::TopCenter)
                ->trigger(style: TriggerStyle::FlagLabel)
                // Signed-in preference first; absent that, an anonymous visitor to a tenant's own
                // URL lands on that tenant's default language rather than whatever the browser
                // happens to prefer (this closure sits in getPreferredLocale()'s cascade right
                // after session/query-string, before Accept-Language).
                ->userPreferredLocale(fn (): ?string => auth()->user()?->locale ?? Filament::getTenant()?->locale())
                // ->circular()
                ->nativeLabel()
                // outhebox/blade-flags ships LANGUAGE flags (language-{code}.svg), not just
                // country ones - the previous config('app.locale_flags') array mapped COUNTRY
                // codes (e.g. 'jp', 'arab') that never matched LOCALE codes ('ja', 'ar'), so
                // Russian/Japanese/Arabic silently had no flag. `php artisan vendor:publish
                // --tag=blade-flags` copies the package's SVGs to public/vendor/blade-flags/,
                // giving each locale a stable URL keyed by its own code, no mapping needed.
                ->flags(collect(config('app.locales', [config('app.default_locale', 'en')]))
                    ->mapWithKeys(fn (string $locale): array => [
                        $locale => asset("vendor/blade-flags/language-{$locale}.svg"),
                    ])
                    ->all());
        });

        Event::listen(function (LocaleChanged $event): void {
            auth()->user()?->update(['locale' => $event->locale]);
        });
    }

    protected function configureFilamentTableDefaults(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginated([10, 25, 50, 100, 'all'])
                ->defaultPaginationPageOption(50)
                ->filtersLayout(FiltersLayout::AboveContent)
                // Dims a whole row when its own record is deactivated (is_active === false) —
                // a record type without that column is untouched (array_key_exists guards it).
                // A resource can still override this by calling ->recordClasses() itself.
                ->recordClasses(function (Model $record): ?string {
                    if (! array_key_exists('is_active', $record->getAttributes())) {
                        return null;
                    }

                    return $record->is_active ? null : 'opacity-50 italic';
                })
                // Icons only in the row-actions column app-wide (Oli) — the label still exists
                // as the button's tooltip. Ungrouped only: an action tucked inside a "..."
                // ActionGroup dropdown keeps its label, since a bare icon there has no room to
                // stay legible. Toolbar/bulk actions are untouched by this hook.
                ->modifyUngroupedRecordActionsUsing(fn (Action $action) => $action->hiddenLabel());
        });

        SelectFilter::configureUsing(function (SelectFilter $filter): void {
            $filter->modifyFormFieldUsing(
                fn (Select $field): Select => $field->hiddenLabel()->placeholder($filter->getLabel()),
            );
        });

        // Filament's own default for a false icon is red ("danger") — reads as an error, not
        // as "just off". Applies to every ->boolean() IconColumn app-wide (Oli: gray, not red).
        IconColumn::configureUsing(function (IconColumn $column): void {
            $column->falseColor('gray');
        });
    }
}
