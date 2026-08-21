<?php

namespace App\Models;

use App\Casts\Password;
use App\Traits\AdminResourceTrait;
use App\Traits\TimezoneTrait;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Magicoli\AssistantMcpEngine\Contracts\AssistantUser;
use Magicoli\AssistantMcpEngine\Models\MailAccount;

class User extends Authenticatable implements AssistantUser, FilamentUser, HasTenants
{
    use AdminResourceTrait;
    use HasApiTokens;
    use TimezoneTrait;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        // "auth_provider",
        // "auth_provider_id",
        'is_admin',
        'roles',
        'options',
        'locale',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'roles' => 'array',
        'options' => 'array',
        'email_verified_at' => 'datetime',
        'password' => Password::class,
    ];

    protected $appends = ['actions'];

    protected $list_columns = ['actions', 'name', 'email', 'roles'];

    protected static $icon = 'users';

    protected static $order = 20;

    /**
     * Get the properties for this user
     */
    public function properties()
    {
        return $this->belongsToMany(Property::class, 'property_user')->withPivot('role')->withTimestamps();
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin || $this->hasRole('admin');
    }

    /**
     * Magicoli\AssistantMcpEngine\Contracts\AssistantUser — cascaded preference lookup, reusing
     * the existing options JSON column rather than a dedicated one.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    /**
     * Satisfies both Filament\Models\Contracts\HasTenants (the App panel's own tenancy,
     * Property::class — dev/project-app-panel-tenancy.md) and
     * Magicoli\AssistantMcpEngine\Contracts\AssistantUser, which declares the exact same method
     * for the same purpose. Just hasAccessTo() under a name Filament looks for by convention —
     * kept as one implementation rather than two, since nothing in bokit calls this with
     * anything but a Property.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Property && $this->hasAccessTo($tenant);
    }

    /**
     * Filament\Models\Contracts\HasTenants — every property this user may switch into. Reuses
     * the existing properties() pivot; isAdmin() (this install's own platform-operator flag, see
     * canAccessTenant()) sees every property, not just their own attachments. Inactive properties
     * are excluded, matching the rest of the app (e.g. Calendar's own is_active filter) — an
     * admin still manages them through PropertyResource, which isn't tenant-scoped.
     *
     * @return Collection<int, Property>
     */
    public function getTenants(Panel $panel): array|Collection
    {
        return $this->isAdmin()
            ? Property::where('is_active', true)->get()
            : $this->properties()->where('is_active', true)->get();
    }

    /**
     * Magicoli\AssistantMcpEngine\Contracts\AssistantUser — bokit has no mail-account feature of
     * its own; declared only so engine code that type-hints AssistantUser stays satisfied. The
     * mail_accounts table doesn't exist here (assistant-mcp-engine's migrations are
     * dont-discover'd — see dev/project-bokit-mcp-server.md), so nothing in bokit's own tool
     * list may actually query this relation.
     */
    public function mailAccounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }

    /**
     * Panel access — implementing FilamentUser makes this rule apply in
     * EVERY environment (local included), so local behaves like prod.
     * Admins and managers see everything; property owners/managers get in
     * and resource queries are scoped to their properties via forUser().
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->hasAnyRole(['manager', 'property_manager']) || $this->properties()->exists();
    }

    /**
     * Landing page after login: the admin panel when the user may access
     * it, the classic dashboard otherwise.
     */
    public function homeUrl(): string
    {
        return $this->canAccessPanel(Filament::getPanel('app')) ? '/app' : '/dashboard';
    }

    /**
     * Get primary role for CSS classes (admin or user)
     */
    public function getPrimaryRole(): string
    {
        return $this->isAdmin() ? 'admin' : 'user';
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles ?? []);
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return ! empty(array_intersect($roles, $this->roles ?? []));
    }

    /**
     * Add a role to the user
     */
    public function addRole(string $role): void
    {
        $roles = $this->roles ?? [];
        if (! in_array($role, $roles)) {
            $roles[] = $role;
            $this->roles = $roles;
            $this->save();
        }
    }

    /**
     * Remove a role from the user
     */
    public function removeRole(string $role): void
    {
        $roles = $this->roles ?? [];
        $key = array_search($role, $roles);
        if ($key !== false) {
            unset($roles[$key]);
            $this->roles = array_values($roles);
            $this->save();
        }
    }

    /**
     * Get all user roles as array
     */
    public function getRoles(): array
    {
        return $this->roles ?? [];
    }

    /**
     * Check if user has access to a property
     */
    public function hasAccessTo(Property $property): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $propertyUser = $this->properties()->where('properties.id', $property->id)->first();

        if (! $propertyUser) {
            return false;
        }

        $userRole = $propertyUser->pivot->role;

        // Tous les rôles peuvent voir (user, admin, owner, manager)
        return in_array($userRole, ['user', 'admin', 'owner', 'manager']);
    }

    /**
     * Check if user can manage a property
     */
    public function canManageProperty(Property $property): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $propertyUser = $this->properties()->where('properties.id', $property->id)->first();

        if (! $propertyUser) {
            return false;
        }

        return in_array(
            $propertyUser->pivot->role,
            [
                'admin',
                'owner',
                'manager',
            ],
        );
    }
}
