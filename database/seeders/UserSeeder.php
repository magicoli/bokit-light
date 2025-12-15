<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Use the dedicated command instead:");
        $this->command->info("php artisan bokit:import-users tmp/users.json");
        $this->command->info("");
        $this->command->info("See USERS-CONFIG.md for more information.");
    }
}
