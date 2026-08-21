<?php

namespace App\Models;

use App\Traits\AdminResourceTrait;
use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use AdminResourceTrait;
    use TimezoneTrait;

    protected $fillable = ['name', 'slug', 'is_active', 'timezone', 'logo', 'locale', 'locales', 'options'];

    protected $casts = [
        'is_active' => 'boolean',
        'options' => 'array',
        'locales' => 'array',
    ];

    protected $appends = ['actions'];

    protected $list_columns = ['actions', 'name'];

    protected static $icon = 'building';

    /**
     * Get the units for this property
     */
    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get the users for this property
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'property_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the full name for display
     */
    public function fullname(): string
    {
        return $this->property->name;
    }

    /**
     * This property's own default language, falling back to the app-wide default when unset
     * (dev/project-tenant-sub-sites.md) - same shape as TimezoneTrait::timezone(). Reads
     * config('app.default_locale'), NOT config('app.locale') - the latter gets overwritten by
     * Laravel's own app()->setLocale() (called on every request by the language switcher) with
     * whichever locale the CURRENT viewer happens to be seeing, which is exactly the "detection
     * leaking into the property's stored default" Oli flagged.
     */
    public function locale(): string
    {
        return (isset($this->attributes['locale']) && $this->attributes['locale'] !== '')
            ? $this->attributes['locale']
            : config('app.default_locale', 'en');
    }

    /**
     * The subset of the app-wide locale list this property actually offers visitors, falling
     * back to every app-wide locale when it hasn't configured a subset of its own.
     *
     * @return array<int, string>
     */
    public function availableLocales(): array
    {
        return $this->locales ?: config('app.locales', [config('app.default_locale', 'en')]);
    }
}
