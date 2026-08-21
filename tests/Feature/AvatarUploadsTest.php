<?php

use App\Filament\Pages\Tenancy\EditTenantProfile;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Property avatar', function () {
    test('has no avatar url when unset', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        expect($property->getFilamentAvatarUrl())->toBeNull();
    });

    test('resolves the avatar url through the public disk when set', function () {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/property.png', 'fake-image');

        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true,
            'avatar_url' => 'avatars/property.png',
        ]);

        expect($property->getFilamentAvatarUrl())->toBe(Storage::url('avatars/property.png'));
    });

    test('can be uploaded and saved through the tenant profile form', function () {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
            'is_admin' => true,
        ]);
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($property);

        Livewire::test(EditTenantProfile::class)
            ->fillForm(['avatar_url' => UploadedFile::fake()->image('icon.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $property->fresh()->avatar_url;
        expect($path)->not->toBeNull();
        Storage::disk('public')->assertExists($path);
    });
});

describe('User avatar', function () {
    test('the profile page offers an avatar upload field', function () {
        $user = User::create([
            'name' => 'U', 'email' => 'u@test.local', 'password' => 'secret-password',
        ]);

        $this->actingAs($user);

        $this->get('/edit-profile')
            ->assertSuccessful()
            ->assertSee('avatar_url', escape: false);
    });
});
