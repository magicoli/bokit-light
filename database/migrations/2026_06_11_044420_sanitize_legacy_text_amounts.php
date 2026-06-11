<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legacy iCal imports stored raw feed strings such as
 * "849,60 EUR/254,88 EUR/594,72 EUR" (total/paid/due) in the numeric
 * price column — SQLite accepts them, but the decimal cast crashes on
 * read. Extract the total as price and the paid amount into metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $rows = DB::table('bookings')
            ->selectRaw('id, price, commission, metadata, typeof(price) as price_type, typeof(commission) as commission_type')
            ->whereRaw("typeof(price) = 'text' or typeof(commission) = 'text'")
            ->get();

        foreach ($rows as $row) {
            $update = [];

            if ($row->price_type === 'text') {
                $amounts = $this->amounts((string) $row->price);
                $update['price'] = $amounts[0] ?? null;

                if (isset($amounts[1])) {
                    $metadata = json_decode($row->metadata ?? '', true) ?: [];
                    $metadata['paid'] ??= $amounts[1];
                    $update['metadata'] = json_encode($metadata);
                }
            }

            if ($row->commission_type === 'text') {
                $amounts = $this->amounts((string) $row->commission);
                $update['commission'] = $amounts[0] ?? null;
            }

            DB::table('bookings')->where('id', $row->id)->update($update);
        }
    }

    public function down(): void
    {
        // Data sanitization is one-way.
    }

    /**
     * Extract the amounts of a legacy feed string ("849,60 EUR/254,88 EUR"),
     * handling French decimal commas and thousand separators.
     *
     * @return list<float>
     */
    private function amounts(string $raw): array
    {
        $values = [];

        foreach (explode('/', $raw) as $part) {
            $part = str_replace(["\u{00A0}", "\u{202F}", ' '], '', $part);

            if (! preg_match('/\d+(?:[.,]\d+)*/', $part, $matches)) {
                continue;
            }

            $number = $matches[0];

            if (str_contains($number, ',')) {
                $number = str_replace('.', '', $number);
                $number = str_replace(',', '.', $number);
            }

            $values[] = round((float) $number, 2);
        }

        return $values;
    }
};
