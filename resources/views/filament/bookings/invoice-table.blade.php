@php
    /** @var \App\Models\Booking $record */
    $record = $getRecord();
    $lines = $record->getMetadata('invoice_lines', []);
    $cell = 'padding: 0.375rem 1rem 0.375rem 0;';
    $head = 'text-align: start; padding: 0.25rem 1rem 0.25rem 0; font-weight: 500; opacity: 0.6;';
    $money = fn (float $value): string => \Illuminate\Support\Number::currency($value, 'EUR', app()->getLocale());

    $charges = array_filter($lines, fn (array $line): bool => ($line['type'] ?? '') !== '200');
    $payments = array_filter($lines, fn (array $line): bool => ($line['type'] ?? '') === '200');
    $lineAmount = fn (array $line): float => $line['amount'] ?? round(($line['qty'] ?? 1) * ($line['price'] ?? 0), 2);
    $total = array_sum(array_map($lineAmount, $charges));
@endphp
<table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="{{ $head }}">{{ __('booking.invoice.description') }}</th>
            <th style="{{ $head }} text-align: end;">{{ __('booking.invoice.qty') }}</th>
            <th style="{{ $head }} text-align: end;">{{ __('booking.invoice.unit_price') }}</th>
            <th style="{{ $head }} text-align: end;">{{ __('booking.invoice.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($charges as $line)
            <tr style="border-top: 1px solid rgba(128, 128, 128, 0.2);">
                <td style="{{ $cell }}">{{ $line['description'] }}</td>
                <td style="{{ $cell }} text-align: end;">{{ rtrim(rtrim(number_format($line['qty'] ?? 1, 2, '.', ''), '0'), '.') }}</td>
                <td style="{{ $cell }} text-align: end; white-space: nowrap;">{{ $money((float) ($line['price'] ?? 0)) }}</td>
                <td style="{{ $cell }} text-align: end; white-space: nowrap;">{{ $money($lineAmount($line)) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: 2px solid rgba(128, 128, 128, 0.4); font-weight: 600;">
            <td style="{{ $cell }}">{{ __('booking.invoice.total') }}</td>
            <td></td>
            <td></td>
            <td style="{{ $cell }} text-align: end; white-space: nowrap;">{{ $money($total) }}</td>
        </tr>
        @foreach ($payments as $line)
            <tr style="border-top: 1px solid rgba(128, 128, 128, 0.2); opacity: 0.75;">
                <td style="{{ $cell }}">{{ $line['description'] }}</td>
                <td></td>
                <td style="{{ $cell }} text-align: end;">{{ __('booking.invoice.payment') }}</td>
                <td style="{{ $cell }} text-align: end; white-space: nowrap;">&minus;&nbsp;{{ $money(abs($lineAmount($line))) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
