<?php

return [
    'field.status' => 'Status',
    'field.guest_name' => 'Name',
    'field.check_in' => 'Check-in',
    'field.check_out' => 'Check-out',
    'field.guests' => 'Persons',
    'field.adults' => 'Adults',
    'field.children' => 'Children',
    'field.source_name' => 'Source',
    'field.price' => 'Price',
    'field.commission' => 'Commission',
    'field.notes' => 'Notes',
    'field.unit_name' => 'Unit',
    'field.ota_url' => 'OTA URL',
    'field.ota_link' => 'OTA Link',
    'field.actions' => 'Actions',
    'field.metadata' => 'Raw Data',
    'field.metadata.status' => 'Status',
    'field.metadata.api_source' => 'Source',
    'field.metadata.email' => 'Email',
    'field.metadata.guests' => 'Phone',
    'field.is_manual' => 'Manual Booking',
    'field.group_id' => 'Group ID',
    'field.unit_id' => 'Unit ID',
    'field.property_id' => 'Property ID',
    'field.uid' => 'UID',
    'field.country' => 'Country',
    'field.comments' => 'Comments',
    'field.paid' => 'Paid',
    'field.balance' => 'Balance',
    'field.deposit' => 'Deposit',
    'field.created_at' => 'Created',
    'field.updated_at' => 'Updated',
    'field.deleted_at' => 'Deleted',

    // Canonical statuses (see Booking::STATUSES)
    'status.confirmed' => 'Confirmed',
    'status.option' => 'Option',
    'status.quote' => 'Quote',
    'status.blocked' => 'Blocked',
    'status.cancelled' => 'Cancelled',
    'status.vanished' => 'Vanished',
    'status.deleted' => 'Deleted',
    'status.undefined' => 'Undefined',
    'status.unavailable' => 'Unavailable',
    'status.cancelled_by_owner' => 'Cancelled by owner',
    'status.cancelled_by_guest' => 'Cancelled by guest',

    'tag.new' => 'New',

    // One-line booking title (widgets, mail subjects)
    'title.units' => ':count unit|:count units',
    'title.dates' => 'from :from to :to',

    // Dashboard widgets
    'widget.ongoing' => 'Ongoing stays',
    'widget.upcoming' => 'Upcoming stays',
    'widget.options' => 'Pending options',
    'widget.quotes' => 'Pending quotes',
    'widget.see_all' => 'See all',

    // Display filters
    'filter.show_cancelled' => 'Show cancelled',
    'filter.show_quotes' => 'Show quotes',
    'filter.effective_only' => 'Effective bookings only',
    'filter.period' => 'Period',
    'period.ongoing' => 'Ongoing',
    'period.upcoming' => 'Upcoming',
    'period.current' => 'Ongoing or upcoming',
    'period.past' => 'Past',

    // Form sections
    'section.guests' => 'Guests',
    'section.pricing' => 'Pricing',
    'section.metadata' => 'Metadata',

    // Sources
    'section.sources' => 'Sources',
    'source.origin' => 'Origin',
    'source.external_id' => 'External ID',
    'source.last_seen' => 'Last seen',
    'source.pending_origin' => 'Origin (not connected)',

    'source.not_connected' => 'not connected',

    'field.group' => 'Group',
    'group.none' => 'No group',

    'section.group' => 'Group reservation',
    'group.total' => 'Total',

    // Invoice detail
    'section.invoice' => 'Invoice detail',
    'invoice.description' => 'Description',
    'invoice.qty' => 'Qty',
    'invoice.unit_price' => 'Unit price',
    'invoice.amount' => 'Amount',
    'invoice.total' => 'Total',
    'invoice.payment' => 'Payment',

    // Helper text
    'guests_auto_calculated' => 'Auto-calculated if empty',
];
