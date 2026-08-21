<?php

use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Magicoli\TwoWayTicket\Filament\Pages\ReportIssue;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\TicketResource;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
use Magicoli\TwoWayTicket\Models\Ticket;

describe('Two Way Tickets', function () {

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
        $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
        $this->owner->properties()->attach($this->property->id, ['role' => 'owner']);

        Filament::setCurrentPanel(Filament::getPanel('app'));
    });

    test('reaches the panel with both plugins registered', function () {
        expect($this->owner->canAccessPanel(Filament::getPanel('app')))->toBeTrue();

        $this->actingAs($this->admin)->get("/app/{$this->property->slug}/tickets")->assertOk();
        $this->actingAs($this->admin)->get("/app/{$this->property->slug}/report-issue")->assertOk();
    });

    test('keeps the backlog to admins', function () {
        $this->actingAs($this->owner);

        expect(TicketResource::canAccess())->toBeFalse();
        expect(TicketResource::canCreate())->toBeFalse();

        $this->get("/app/{$this->property->slug}/tickets")->assertForbidden();
        $this->get("/app/{$this->property->slug}/tickets/create")->assertForbidden();
    });

    test('lets an owner report an issue', function () {
        $this->actingAs($this->owner);

        expect(ReportIssue::canAccess())->toBeTrue();

        $this->get("/app/{$this->property->slug}/report-issue")->assertOk();
    });

    test('shows the ticket stats widget to admins only', function () {
        $this->actingAs($this->admin);
        expect(TicketStatsWidget::canView())->toBeTrue();

        $this->actingAs($this->owner);
        expect(TicketStatsWidget::canView())->toBeFalse();
    });

    test('puts the stats widget on the admin dashboard', function () {
        $this->actingAs($this->admin)->get("/app/{$this->property->slug}")->assertSuccessful()->assertSeeLivewire(TicketStatsWidget::class);
    });

    test('leaves it off the dashboard of someone who cannot triage', function () {
        // One user per test on purpose: a second actingAs() after a request lands on the panel's
        // login screen instead of switching identity.
        $this->actingAs($this->owner)->get("/app/{$this->property->slug}")->assertSuccessful()->assertDontSeeLivewire(TicketStatsWidget::class);
    });

    test('files a ticket through the API with the token', function () {
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

    test('refuses the API without the token', function () {
        config(['two-way-ticket.api.token' => 'test-token']);

        $this->postJson('/api/tickets', ['title' => 'No token'])->assertUnauthorized();

        expect(Ticket::count())->toBe(0);
    });

    test('stamps tickets with the running app version', function () {
        config(['two-way-ticket.api.token' => 'test-token']);

        $this->postJson(
            '/api/tickets',
            ['title' => 'Versioned'],
            ['Authorization' => 'Bearer test-token'],
        )->assertSuccessful();

        expect(Ticket::where('title', 'Versioned')->value('app_version'))->toBe(config('app.version'));
    });
});
