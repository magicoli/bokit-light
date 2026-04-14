<?php

namespace Modules\WpConnector\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WpApiService
{
    /**
     * Get units from WordPress for a specific plugin
     */
    public function getUnits(string $plugin, array $propertyConfig): array
    {
        try {
            $wpUrl = rtrim($propertyConfig['wp_url'] ?? '', '/');
            $username = $propertyConfig['wp_user'] ?? '';
            $password = $propertyConfig['wp_app_password'] ?? '';
            
            if (empty($wpUrl) || empty($username) || empty($password)) {
                Log::warning("WP Connector: Missing configuration for property {$propertyConfig['id'] ?? 'unknown'}");
                return [];
            }
            
            // Build the API endpoint based on plugin
            $endpoint = match ($plugin) {
                'hbook' => '/wp-json/hbook/v1/units',
                'multipass' => '/wp-json/bokit/v1/multipass-units',
                default => throw new \InvalidArgumentException("Unknown plugin: {$plugin}"),
            };
            
            $response = Http::withBasicAuth($username, $password)
                ->accept('application/json')
                ->timeout(10)
                ->get("{$wpUrl}{$endpoint}");
            
            if ($response->successful()) {
                $units = $response->json('units', []);
                
                // Format: [['id' => 'wp-id', 'name' => 'Unit Name'], ...]
                return array_map(function ($unit) use ($plugin) {
                    return [
                        'id' => $unit['id'] ?? $unit['ID'] ?? '',
                        'name' => $unit['name'] ?? $unit['post_title'] ?? 'Unknown',
                    ];
                }, $units);
            }
            
            Log::error("WP Connector: Failed to fetch {$plugin} units", [
                'url' => $wpUrl,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            
            return [];
        } catch (\Exception $e) {
            Log::error("WP Connector: Exception fetching {$plugin} units", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Get HBook units from WordPress
     */
    public function getHbookUnits(array $propertyConfig): array
    {
        return $this->getUnits('hbook', $propertyConfig);
    }
    
    /**
     * Get Multipass units from WordPress
     */
    public function getMultipassUnits(array $propertyConfig): array
    {
        return $this->getUnits('multipass', $propertyConfig);
    }
}
