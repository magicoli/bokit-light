<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Hash;

/**
 * Password cast - automatically hashes passwords on save
 * 
 * Usage in Model:
 * protected $casts = ['password' => Password::class];
 * 
 * Then simply:
 * $user->password = 'plain-text-password';
 * $user->save(); // Automatically hashed in database
 */
class Password implements CastsAttributes
{
    /**
     * Cast the given value - return the hash as-is for authentication
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        // Return the hash as-is - needed for Laravel Auth
        return $value;
    }

    /**
     * Prepare the given value for storage - hash the password
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array
     */
    public function set($model, string $key, $value, array $attributes)
    {
        // If empty or null, don't update password (keep existing)
        if (empty($value)) {
            return [];
        }

        // Hash the password using Laravel's Hash facade (bcrypt)
        return [$key => Hash::make($value)];
    }
}
