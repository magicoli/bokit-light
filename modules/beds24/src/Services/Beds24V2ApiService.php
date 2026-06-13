<?php

namespace Modules\Beds24\Services;

use App\Models\Property;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Beds24 API V2 client (https://beds24.com/api/v2/).
 *
 * Authentication: a per-property refresh token (property.options
 * beds24_refresh_token, obtained once from an invite code via
 * `beds24:connect`) is exchanged for a short-lived auth token, cached
 * until shortly before it expires. A long-life token can be stored in
 * beds24_token instead and is then used directly.
 *
 * Refresh tokens stay valid as long as they are used at least once every
 * 30 days.
 */
class Beds24V2ApiService
{
    private const API_URL = 'https://api.beds24.com/v2';

    public function __construct(private readonly Property $property) {}

    public function isConfigured(): bool
    {
        return ! empty($this->property->options['beds24_refresh_token'])
            || ! empty($this->property->options['beds24_token']);
    }

    /**
     * Exchange an invite code for a refresh token (one-time setup).
     *
     * @return string the refresh token
     *
     * @throws \RuntimeException
     */
    public static function exchangeInviteCode(string $code): string
    {
        $response = Http::timeout(30)
            ->withHeaders(['code' => $code, 'deviceName' => 'bokit'])
            ->get(self::API_URL.'/authentication/setup');

        // The setup response carries both a short-lived 'token' and the
        // permanent 'refreshToken' — we must store the latter.
        $refreshToken = $response->json('refreshToken');

        if (! $response->successful() || empty($refreshToken)) {
            throw new \RuntimeException('Invite code exchange failed: '.($response->json('error') ?? $response->body()));
        }

        return $refreshToken;
    }

    /**
     * Create or update bookings. Each entry without an 'id' is created,
     * entries with an 'id' are updated (cancellation = status 'cancelled').
     *
     * @param  array<int, array<string,mixed>>  $bookings
     * @return array<int, array<string,mixed>> one result per booking, with
     *                                         'success' and, for creations, the new booking id
     *
     * @throws \RuntimeException
     */
    public function postBookings(array $bookings): array
    {
        $response = Http::timeout(60)
            ->connectTimeout(30)
            ->retry(2, 2000, fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false)
            ->withHeaders(['token' => $this->authToken()])
            ->post(self::API_URL.'/bookings', $bookings);

        if (! $response->successful()) {
            throw new \RuntimeException("Beds24 V2 POST /bookings failed (HTTP {$response->status()}): ".$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Current auth token: the configured long-life token, or one obtained
     * from the refresh token and cached until shortly before expiry.
     *
     * @throws \RuntimeException
     */
    protected function authToken(): string
    {
        $longLife = $this->property->options['beds24_token'] ?? null;

        if (! empty($longLife)) {
            return $longLife;
        }

        $refreshToken = $this->property->options['beds24_refresh_token'] ?? null;

        if (empty($refreshToken)) {
            throw new \RuntimeException('Beds24 V2 not configured for this property (no refresh token)');
        }

        return Cache::remember(
            "beds24_v2_token_{$this->property->id}",
            now()->addMinutes(20),
            function () use ($refreshToken): string {
                $response = Http::timeout(30)
                    ->withHeaders(['refreshToken' => $refreshToken])
                    ->get(self::API_URL.'/authentication/token');

                $token = $response->json('token');

                if (! $response->successful() || empty($token)) {
                    throw new \RuntimeException('Beds24 V2 token refresh failed: '.($response->json('error') ?? $response->body()));
                }

                return $token;
            },
        );
    }
}
