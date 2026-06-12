<?php

namespace Modules\Beds24\Commands;

use App\Models\Property;
use Illuminate\Console\Command;
use Modules\Beds24\Services\Beds24V2ApiService;

/**
 * One-time Beds24 API V2 setup: exchange an invite code (generated at
 * https://beds24.com/control3.php?pagetype=apiv2, scope "bookings") for a
 * refresh token, stored in the property options.
 */
class Beds24ConnectCommand extends Command
{
    protected $signature = 'beds24:connect
                            {property : Property slug or ID}
                            {code : Invite code generated in Beds24}';

    protected $description = 'Connect a property to the Beds24 API V2 (exchange an invite code for a refresh token)';

    public function handle(): int
    {
        $key = $this->argument('property');
        $property = Property::where('slug', $key)->orWhere('id', (int) $key)->first();

        if (! $property) {
            $this->error("Property '{$key}' not found.");

            return self::FAILURE;
        }

        try {
            $refreshToken = (new Beds24V2ApiService($property))
                ->exchangeInviteCode($this->argument('code'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $options = $property->options ?? [];
        $options['beds24_refresh_token'] = $refreshToken;
        $property->options = $options;
        $property->save();

        $this->info("Refresh token stored for {$property->name}. The Beds24 V2 API is ready.");

        return self::SUCCESS;
    }
}
