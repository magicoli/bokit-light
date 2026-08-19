<?php

return [
    'label' => 'Booking',
    'plural_label' => 'Bookings',
    'field' => [
        'status' => 'Status',
        'guest_name' => 'Name',
        'check_in' => 'Check-in',
        'check_out' => 'Check-out',
        'guests' => 'Persons',
        'adults' => 'Adults',
        'children' => 'Children',
        'source_name' => 'Source',
        'price' => 'Price',
        'commission' => 'Commission',
        'notes' => 'Notes',
        'unit_name' => 'Unit',
        'ota_url' => 'OTA URL',
        'ota_link' => 'OTA Link',
        'actions' => 'Actions',
        'metadata' => 'Raw Data',
        'metadata.status' => 'Status',
        'metadata.api_source' => 'Source',
        'metadata.email' => 'Email',
        'metadata.guests' => 'Phone',
        'is_manual' => 'Manual Booking',
        'group_id' => 'Group ID',
        'unit_id' => 'Unit ID',
        'property_id' => 'Property ID',
        'uid' => 'UID',
        'country' => 'Country',
        'comments' => 'Comments',
        'paid' => 'Paid',
        'balance' => 'Balance',
        'deposit' => 'Deposit',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'deleted_at' => 'Deleted',
        'group' => 'Group',
    ],
    'status' => [

        // Canonical statuses (see Booking::STATUSES)
        'confirmed' => 'Confirmed',
        'option' => 'Option',
        'quote' => 'Quote',
        'blocked' => 'Blocked',
        'cancelled' => 'Cancelled',
        'vanished' => 'Vanished',
        'deleted' => 'Deleted',
        'undefined' => 'Undefined',
        'unavailable' => 'Unavailable',
        'cancelled_by_owner' => 'Cancelled by owner',
        'cancelled_by_guest' => 'Cancelled by guest',
    ],

    'tag.new' => 'New',

    'title' => [
        // One-line booking title (widgets, mail subjects)
        'units' => ':count unit|:count units',
        'dates' => 'from :from to :to',
    ],

    'widget' => [
        // Dashboard widgets
        'ongoing' => 'Ongoing stays',
        'upcoming' => 'Upcoming stays',
        'options' => 'Pending options',
        'quotes' => 'Pending quotes',
        'see_all' => 'See all',
    ],

    'filter' => [
        // Display filters
        'show_cancelled' => 'Show cancelled',
        'show_quotes' => 'Show quotes',
        'effective_only' => 'Effective bookings only',
        'period' => 'Period',
    ],

    'period' => [
        'ongoing' => 'Ongoing',
        'upcoming' => 'Upcoming',
        'current' => 'Ongoing or upcoming',
        'past' => 'Past',
    ],

    'section' => [
        // Form sections
        'guests' => 'Guests',
        'pricing' => 'Pricing',
        'metadata' => 'Metadata',
        'sources' => 'Sources',
        'group' => 'Group reservation',
        'invoice' => 'Invoice detail',
    ],

    'source' => [
        // Sources
        'origin' => 'Origin',
        'beds24' => 'Open in Beds24',
        'external_id' => 'External ID',
        'last_seen' => 'Last seen',
        'pending_origin' => 'Origin (not connected)',
        'not_connected' => 'not connected',
    ],

    'group' => [
        'none' => 'No group',
        'total' => 'Total',
    ],

    'invoice' => [
        // Invoice detail
        'description' => 'Description',
        'qty' => 'Qty',
        'unit_price' => 'Unit price',
        'amount' => 'Amount',
        'total' => 'Total',
        'payment' => 'Payment',
    ],

    'push.failed' => 'Push to channels failed',
    'protected_origin' => 'Origin OTA manages this booking — dates and price are read-only.',
    'guests_auto_calculated' => 'Auto-calculated if empty',
];
