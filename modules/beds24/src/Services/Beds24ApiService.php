<?php

namespace Modules\Beds24\Services;

use App\Models\Property;
use App\Support\Options;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Beds24 JSON API v1 client.
 *
 * API key is account-level (Options / config fallback to env).
 * Property key is per-property (stored in property.options['beds24_prop_key']).
 *
 * @see https://www.beds24.com/api/json/
 */
class Beds24ApiService
{
    private const API_URL = 'https://api.beds24.com/json/';

    private string $apiKey;

    private string $propKey;

    public function __construct(private readonly Property $property)
    {
        // apiKey: property-level > global Options > config/env
        $this->apiKey = $property->options['beds24_api_key']
            ?? Options::get('beds24.api_key')
            ?? config('beds24.api_key', '');

        $this->propKey = $property->options['beds24_prop_key'] ?? '';
    }

    /**
     * Fetch bookings from Beds24.
     *
     * @param  array{
     *     arrivalFrom?: string,
     *     arrivalTo?: string,
     *     departureFrom?: string,
     *     departureTo?: string,
     *     roomId?: int|string,
     *     bookId?: int|string,
     *     status?: string,
     *     modifiedSince?: string,
     *     searchText?: string,
     *     includeInvoice?: bool,
     *     includeInfoItems?: bool,
     *     limit?: int,
     *     offset?: int,
     * } $params
     * @return array<int, array<string, mixed>>
     */
    public function getBookings(array $params = []): array
    {
        $payload = array_merge([
            'authentication' => [
                'apiKey' => $this->apiKey,
                'propKey' => $this->propKey,
            ],
            'includeInvoice' => true,
            'includeInfoItems' => false,
            'limit' => 1000,
        ], $params);

        $response = Http::timeout(60)
            ->connectTimeout(30)
            ->retry(2, 2000, fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false)
            ->post(self::API_URL.'getBookings', $payload);

        if (! $response->successful()) {
            Log::error('[Beds24] getBookings HTTP error', [
                'property_id' => $this->property->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json();

        if (isset($data['error'])) {
            Log::error('[Beds24] getBookings API error', [
                'property_id' => $this->property->id,
                'error' => $data['error'],
                'errorCode' => $data['errorCode'] ?? null,
            ]);

            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Check whether this service is properly configured for the property.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->propKey);
    }
}
