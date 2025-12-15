<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'auth_provider',
        'auth_provider_id',
        'is_admin',
        'roles',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'roles' => 'array',
    ];

    /**
     * Get the properties for this user
     */
    public function properties()
    {
        return $this->belongsToMany(Property::class, 'property_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin || $this->hasRole('admin');
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
        return !empty(array_intersect($roles, $this->roles ?? []));
    }

    /**
     * Add a role to the user
     */
    public function addRole(string $role): void
    {
        $roles = $this->roles ?? [];
        if (!in_array($role, $roles)) {
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

        $propertyUser = $this->properties()
            ->where('properties.id', $property->id)
            ->first();

        if (!$propertyUser) {
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

        $propertyUser = $this->properties()
            ->where('properties.id', $property->id)
            ->first();

        if (!$propertyUser) {
            return false;
        }

        return in_array($propertyUser->pivot->role, ['admin', 'owner', 'manager']);
    }
}
