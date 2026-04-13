<?php

namespace Modules\WpConnector\Services;

use App\Models\Property;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WordPress REST API client.
 *
 * Authenticates via WordPress Application Passwords (Basic Auth).
 * Connection settings are stored per-property in property.options:
 *   wp_url          - WordPress site base URL
 *   wp_user         - WordPress username
 *   wp_app_password - WordPress Application Password
 */
class WpConnectorService
{
    private string $baseUrl;
    private string $user;
    private string $appPassword;

    public function __construct(private readonly Property $property)
    {
        $this->baseUrl     = rtrim($property->options['wp_url'] ?? '', '/');
        $this->user        = $property->options['wp_user'] ?? '';
        $this->appPassword = $property->options['wp_app_password'] ?? '';
    }

    /**
     * Check whether this service is properly configured for the property.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl)
            && ! empty($this->user)
            && ! empty($this->appPassword);
    }

    /**
     * Make an authenticated GET request to the WP REST API.
     *
     * @param  string  $endpoint  e.g. '/wp-json/bokit/v1/bookings/hbook'
     * @param  array<string, mixed>  $params
     */
    public function get(string $endpoint, array $params = []): Response
    {
        return $this->client()->get($this->baseUrl . $endpoint, $params);
    }

    /**
     * Make an authenticated POST request to the WP REST API.
     *
     * @param  string  $endpoint
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): Response
    {
        return $this->client()->post($this->baseUrl . $endpoint, $data);
    }

    /**
     * Build the authenticated HTTP client.
     */
    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->user, $this->appPassword)
            ->timeout(30)
            ->acceptJson();
    }
}
