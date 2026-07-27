<?php

use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Magicoli\TwoWayTicket\Filament\Pages\ReportIssue;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\TicketResource;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
use Magicoli\TwoWayTicket\Models\Ticket;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    // A property owner: reaches the panel, and has no business seeing the backlog.
    $this->owner = User::create([
        'name' => 'Owner',
        'email' => 'owner@test.local',
        'password' => bcrypt('password'),
        'is_admin' => false,
    ]);
    // Two of them on purpose: an owner with a single property is sent straight to its page, and
    // would never see the dashboard this file also checks.
    $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
    $this->owner->properties()->attach($this->property->id, ['role' => 'owner']);
    $this->owner->properties()->attach(Property::create(['name' => 'Q', 'slug' => 'q', 'is_active' => true])->id, [
        'role' => 'owner',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('reaches the panel with both plugins registered', function () {
    expect($this->owner->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    $this->actingAs($this->admin)->get('/admin/tickets')->assertOk();
    $this->actingAs($this->admin)->get('/admin/report-issue')->assertOk();
});

it('keeps the backlog to admins', function () {
    $this->actingAs($this->owner);

    expect(TicketResource::canAccess())->toBeFalse();
    expect(TicketResource::canCreate())->toBeFalse();

    $this->get('/admin/tickets')->assertForbidden();
    $this->get('/admin/tickets/create')->assertForbidden();
});

it('lets an owner report an issue', function () {
    $this->actingAs($this->owner);

    expect(ReportIssue::canAccess())->toBeTrue();

    $this->get('/admin/report-issue')->assertOk();
});

it('shows the ticket stats widget to admins only', function () {
    $this->actingAs($this->admin);
    expect(TicketStatsWidget::canView())->toBeTrue();

    $this->actingAs($this->owner);
    expect(TicketStatsWidget::canView())->toBeFalse();
});

it('puts the stats widget on the admin dashboard', function () {
    $this->actingAs($this->admin)->get('/admin')->assertSuccessful()->assertSeeLivewire(TicketStatsWidget::class);
});

it('leaves it off the dashboard of someone who cannot triage', function () {
    // One user per test on purpose: a second actingAs() after a request lands on the panel's
    // login screen instead of switching identity.
    $this->actingAs($this->owner)->get('/admin')->assertSuccessful()->assertDontSeeLivewire(TicketStatsWidget::class);
});

it('files a ticket through the API with the token', function () {
    config(['two-way-ticket.api.token' => 'test-token']);

    $this->postJson(
        '/api/tickets',
        [
            'title' => 'Filed by a script',
            'labels' => ['bug'],
        ],
        ['Authorization' => 'Bearer test-token'],
    )->assertSuccessful();

    expect(Ticket::where('title', 'Filed by a script')->exists())->toBeTrue();
});

it('refuses the API without the token', function () {
    config(['two-way-ticket.api.token' => 'test-token']);

    $this->postJson('/api/tickets', ['title' => 'No token'])->assertUnauthorized();

    expect(Ticket::count())->toBe(0);
});

it('stamps tickets with the running app version', function () {
    config(['two-way-ticket.api.token' => 'test-token']);

    $this->postJson(
        '/api/tickets',
        ['title' => 'Versioned'],
        ['Authorization' => 'Bearer test-token'],
    )->assertSuccessful();

    expect(Ticket::where('title', 'Versioned')->value('app_version'))->toBe(config('app.version'));
});
