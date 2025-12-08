<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCheckoutDates extends Command
{
    protected $signature = 'bokit:fix-checkout-dates';
    
    protected $description = 'Convert check_out dates from iCal format (last night) to real checkout dates (+1 day)';

    public function handle(): int
    {
        $this->info('Converting check_out dates to real checkout format...');
        $this->newLine();
        
        $bookings = Booking::all();
        $this->info("Found {$bookings->count()} bookings to convert.");
        $this->newLine();
        
        $bar = $this->output->createProgressBar($bookings->count());
        
        DB::beginTransaction();
        
        try {
            foreach ($bookings as $booking) {
                // Add one day to check_out
                $booking->check_out = $booking->check_out->addDay();
                $booking->save();
                $bar->advance();
            }
            
            DB::commit();
            $bar->finish();
            
            $this->newLine();
            $this->newLine();
            $this->info('✅ All check_out dates converted successfully!');
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Conversion failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
