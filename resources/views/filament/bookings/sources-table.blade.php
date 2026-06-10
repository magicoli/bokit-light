<table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="text-align: start; padding: 0.25rem 1rem 0.25rem 0; font-weight: 500; opacity: 0.6;">{{ __('booking.field.source_name') }}</th>
            <th style="text-align: start; padding: 0.25rem 1rem 0.25rem 0; font-weight: 500; opacity: 0.6;">{{ __('booking.source.external_id') }}</th>
            <th style="text-align: start; padding: 0.25rem 0; font-weight: 500; opacity: 0.6;">{{ __('booking.source.last_seen') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($getRecord()->sources as $source)
            <tr style="border-top: 1px solid rgba(128, 128, 128, 0.2);">
                <td style="padding: 0.375rem 1rem 0.375rem 0; white-space: nowrap;">{{ $source->display_label }}</td>
                <td style="padding: 0.375rem 1rem 0.375rem 0; word-break: break-all;">
                    @if ($source->external_url)
                        <a href="{{ $source->external_url }}" target="_blank" rel="noopener" style="text-decoration: underline;">{{ $source->external_id }}</a>
                    @else
                        {{ $source->external_id }}
                    @endif
                </td>
                <td style="padding: 0.375rem 0; white-space: nowrap;">{{ $source->last_seen_at?->format('d/m/Y H:i') ?? '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
