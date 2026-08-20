<?php

namespace App\Mcp\Tools;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Magicoli\AssistantMcpEngine\Models\Assistant;

/**
 * Full detail for one booking — id from list_bookings. Shares Booking::toDetailPayload() with
 * the calendar's own booking modal (CalendarController::booking()), so the two never drift:
 * full invoice amounts (price/deposit/paid/balance, computed once — bookings-lessons-learned.md
 * §4), the complete group member list when it's a group reservation, and source/origin links.
 */
#[Name('get_booking')]
#[Title('Get Booking')]
#[Description("Full detail for one booking by id (from list_bookings), including price/deposit/paid/balance and, for a group reservation, every member. 'deposit' is the amount configured to be due, not evidence it was paid — check 'paid' for that.")]
class GetBookingTool extends Tool
{
    public function __construct(public ?Assistant $assistant = null, public ?User $user = null) {}

    public function handle(Request $request): Response
    {
        if (! $this->assistant) {
            return Response::error('No tenant context — this tool needs a resolved Assistant (see BookingServer::boot()).');
        }

        $booking = Booking::with(['unit.property', 'originSource'])->find($request->integer('booking_id'));

        if (! $booking) {
            return Response::error("No booking with id {$request->integer('booking_id')}.");
        }

        $property = $booking->property ?? $booking->unit?->property;

        if (! $property || $property->assistant_id !== $this->assistant->id) {
            return Response::error('Access denied.');
        }

        if (! $this->user?->hasAccessTo($property)) {
            return Response::error('Access denied.');
        }

        return Response::json($booking->toDetailPayload());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()
                ->description('id from list_bookings')
                ->required(),
        ];
    }
}
