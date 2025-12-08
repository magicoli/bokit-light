<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;

class CleanupProperty extends Command
{
    protected $signature = 'bokit:cleanup-property {slug : Property slug to delete}';
    
    protected $description = 'Delete a property and all its related data';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        
        $property = Property::where('slug', $slug)->first();
        
        if (!$property) {
            $this->error("Property '{$slug}' not found.");
            return self::FAILURE;
        }
        
        $this->info("Found property: {$property->name}");
        $this->line("  - {$property->icalSources->count()} iCal sources");
        $this->line("  - {$property->bookings->count()} bookings");
        $this->newLine();
        
        if (!$this->confirm('Delete this property and all its data?', false)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }
        
        // Delete (cascade will handle sources and bookings)
        $property->delete();
        
        $this->info('✅ Property deleted successfully!');
        
        return self::SUCCESS;
    }
}
