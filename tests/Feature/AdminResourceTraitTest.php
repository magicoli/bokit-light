<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class AdminResourceTraitTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }

    #[Test]
    public function admin_routes_are_registered()
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.bookings.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.bookings.list'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.bookings.create'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.bookings.settings'));
    }

    #[Test]
    public function admin_can_access_bookings_list()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings.list'));
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.resource.list');
        $response->assertViewHas('resource', 'bookings');
    }

    #[Test]
    public function admin_can_access_create_page()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings.create'));
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.resource.create');
    }

    #[Test]
    public function admin_can_access_settings_page()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings.settings'));
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.resource.settings');
    }

    #[Test]
    public function guest_is_redirected_from_admin_routes()
    {
        $response = $this->get(route('admin.bookings.list'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function trait_generates_correct_resource_name()
    {
        $this->assertEquals('bookings', Booking::getResourceName());
    }

    #[Test]
    public function trait_generates_menu_config()
    {
        $menu = Booking::adminMenuConfig();
        
        $this->assertArrayHasKey('label', $menu);
        $this->assertArrayHasKey('children', $menu);
        $this->assertArrayHasKey('url', $menu);
        $this->assertIsArray($menu['children']);
        $this->assertGreaterThan(0, count($menu['children']));
    }
}
