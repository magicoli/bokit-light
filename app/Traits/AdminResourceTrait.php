<?php

namespace App\Traits;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * AdminResourceTrait
 *
 * Owner-based query scoping (scopeForUser/scopeForPropertyManager), used directly by Filament
 * resources via ::forUser(). Route auto-registration and menu building used to live here too,
 * before the legacy admin CRUD system they served was replaced by Filament resources/panels
 * and removed.
 *
 * Usage:
 *   class Booking extends Model {
 *       use AdminResourceTrait;
 *   }
 */
trait AdminResourceTrait
{
    use FormTrait;
    use ListTrait;

    /**
     * Scope query to user's authorized records
     *
     * Filters based on user role:
     * - Admin/manager: sees everything (no filter)
     * - property_manager: sees only records they own or have access to
     *
     * Override this method in models that need custom filtering logic.
     *
     * @param  User|null  $user  User to filter for (defaults to current user)
     */
    public function scopeForUser(Builder $query, $user = null): Builder
    {
        $user = $user ?? auth()->user();

        // No user or admin/manager: no filtering
        if (! $user || $user->isAdmin() || $user->hasRole('manager')) {
            return $query;
        }

        // Everyone else sees the properties they are attached to
        // (property_user pivot) — nothing when not attached to any.
        return $this->scopeForPropertyManager($query, $user);
    }

    /**
     * Filter query for property_manager role
     *
     * Default implementation filters via property.users relationship.
     * Override in specific models if needed.
     *
     * @param  User  $user
     */
    protected function scopeForPropertyManager(Builder $query, $user): Builder
    {
        // For Property model: direct users relationship
        if ($this instanceof Property) {
            return $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        // For models with property relationship: filter via property.users
        if (method_exists($this, 'property')) {
            return $query->whereHas('property.users', function ($q) use (
                $user,
            ) {
                $q->where('users.id', $user->id);
            });
        }

        // No property relationship: no access
        return $query->whereRaw('1 = 0');
    }
}
