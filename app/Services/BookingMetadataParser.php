<?php

namespace App\Services;

class BookingMetadataParser
{
    /**
 * DEPRECATED - Handled by css exclusively
 * Get color for booking status
 *
 * @param string|null $status
 * @return string Hex color code
 */
// public static function getStatusColor(?string $status): string
// {
//     $status = strtolower($status ?? "");
//     $colors = [
//         "cancelled" => "#88888840", // Gray 50%
//         "vanished" => "#88888840", // Gray 50%
//         "inquiry" => "#f59e0b80", // Orange 50%
//         "request" => "#f59e0bc0", // Orange - booking requests
//         "new" => "#3b82f6", // Blue - new/pending bookings
//         "confirmed" => "#10b981", // Green - confirmed bookings
//         "blocked" => "#000000c0", // Black 50%
//         "unavailable" => "#00000080", // Black 50%
//     ];
//     return $colors[$status] ?? "#888888";
// }
/**
 * DEPRECATED Get human-readable status label
 *
 * @param string|null $status
 * @return string
 */
// public static function getStatusLabel(?string $status): string
// {
//     $labels = [
//         "inquiry" => __("app.status.inquiry"), // Non-blocking
//         "request" => __("app.status.request"), // Blocking (TODO: add a deadline logic to release the dates)
//         "new" => __("app.status.new"), // Blocking
//         "confirmed" => __("app.status.confirmed"), // Blocking
//         "cancelled_by_owner" => __("app.status.cancelled_by_owner"), // Non-blocking
//         "cancelled_by_guest" => __("app.status.cancelled_by_guest"), // Non-blocking (TODO: add a deadline logic to hide the booking)
//         "vanished" => __("app.status.vanished"), // Non-blocking (TODO: add a deadline logic to hide the booking)
//         "deleted" => __("app.status.deleted"), // Non-blocking, not shown in the UI
//         "blocked" => __("app.status.blocked"), // Blocking
//         "unavailable" => __("app.status.unavailable"), // Blocking
//         "undefined" => __("app.status.undefined"), // Blocking
//     ];
//     $status = strtolower($status ?? "undefined");
//     return $labels[$status] ?? "Unknown " . ucfirst($status);
// }
}
