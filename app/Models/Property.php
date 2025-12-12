<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = ["name", "slug", "settings"];

    protected $casts = [
        "settings" => "array",
    ];

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
        return $this->belongsToMany(User::class, "property_user")
            ->withPivot("role")
            ->withTimestamps();
    }

    /**
     * Get the full name for display
     */
    public function fullname(): string
    {
        return $this->property->name;
    }
}
