<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Resources\Properties\Schemas\PropertyForm;
use App\Models\Property;
use App\Models\User;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Consolidates every tenant (Property) preference in one self-service page - reuses
 * PropertyForm::configure() directly, the same schema PropertyResource's own EditProperty page
 * uses, so nothing sync-critical (Beds24 invite code, module-injected fields...) is duplicated or
 * lost here (dev/project-timezone-and-tenant-settings.md).
 */
class EditTenantProfile extends BaseEditTenantProfile
{
    public static function getLabel(): string
    {
        return __('property.label');
    }

    public function form(Schema $schema): Schema
    {
        return PropertyForm::configure($schema);
    }

    /**
     * The base class's canView() authorizes via Laravel's generic authorize()/Policy system -
     * this app doesn't use one for Property (no PropertyPolicy exists), it uses its own
     * hasAccessTo(), the same check canAccessTenant() already relies on for switching into this
     * tenant in the first place.
     */
    public static function canView(Model $tenant): bool
    {
        $user = auth()->user();

        return $user instanceof User && $tenant instanceof Property && $user->hasAccessTo($tenant);
    }
}
