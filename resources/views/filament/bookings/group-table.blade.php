@php
    /** @var \App\Models\Booking $record */
    $record = $getRecord();
    $members = $record->groupMembers();
    $cell = 'padding: 0.375rem 1rem 0.375rem 0; white-space: nowrap;';
    $head = 'text-align: start; padding: 0.25rem 1rem 0.25rem 0; font-weight: 500; opacity: 0.6;';
    $money = fn (?float $value): string => $value !== null
        ? \Illuminate\Support\Number::currency($value, 'EUR', app()->getLocale())
        : '-';
@endphp
<table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="{{ $head }}">{{ __('booking.field.unit_name') }}</th>
            <th style="{{ $head }}">{{ __('booking.field.guest_name') }}</th>
            <th style="{{ $head }}">{{ __('booking.field.check_in') }}</th>
            <th style="{{ $head }}">{{ __('booking.field.check_out') }}</th>
            <th style="{{ $head }}">{{ __('booking.field.adults') }}</th>
            <th style="{{ $head }}">{{ __('booking.field.children') }}</th>
            <th style="{{ $head }} text-align: end;">{{ __('booking.field.price') }}</th>
            <th style="{{ $head }}">{{ __('booking.field.status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($members as $member)
            <tr style="border-top: 1px solid rgba(128, 128, 128, 0.2); {{ $member->id === $record->id ? 'font-weight: 600;' : '' }}">
                <td style="{{ $cell }}">
                    @if ($member->id === $record->id)
                        {{ $member->unit?->name }}
                    @else
                        <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $member]) }}" style="text-decoration: underline;">{{ $member->unit?->name }}</a>
                    @endif
                </td>
                <td style="{{ $cell }}">{{ $member->guest_name }}</td>
                <td style="{{ $cell }}">{{ $member->check_in->format('d/m/Y') }}</td>
                <td style="{{ $cell }}">{{ $member->check_out->format('d/m/Y') }}</td>
                <td style="{{ $cell }}">{{ $member->adults ?? '-' }}</td>
                <td style="{{ $cell }}">{{ $member->children ?? '-' }}</td>
                <td style="{{ $cell }} text-align: end;">{{ $money($member->getRawOriginal('price') !== null ? (float) $member->getRawOriginal('price') : null) }}</td>
                <td style="{{ $cell }}">{{ __('booking.status.'.$member->status) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: 2px solid rgba(128, 128, 128, 0.4); font-weight: 600;">
            <td style="{{ $cell }}">{{ __('booking.group.total') }}</td>
            <td style="{{ $cell }}"></td>
            <td style="{{ $cell }}">{{ $members->min('check_in')?->format('d/m/Y') }}</td>
            <td style="{{ $cell }}">{{ $members->max('check_out')?->format('d/m/Y') }}</td>
            <td style="{{ $cell }}">{{ $members->sum('adults') ?: '-' }}</td>
            <td style="{{ $cell }}">{{ $members->sum('children') ?: '-' }}</td>
            <td style="{{ $cell }} text-align: end;">{{ $money($members->sum(fn ($m) => (float) $m->getRawOriginal('price'))) }}</td>
            <td style="{{ $cell }}"></td>
        </tr>
    </tbody>
</table>
