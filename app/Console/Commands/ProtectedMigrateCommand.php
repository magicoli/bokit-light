<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProtectedMigrateCommand extends Command
{
    protected $signature = 'migrate:fresh
                            {--force : Force the operation to run}
                            {--seed : Seed the database after migration}
                            {--drop-views : Drop all tables and views}
                            {--drop-types : Drop all tables and types}
                            {--path=* : The path(s) to the migrations files to be executed}
                            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                            {--schema-path= : The path to a schema dump file}
                            {--database= : The database connection to use}
                            {--step : Force the migrations to be run so they can be rolled back individually}';

    protected $description = "PROTECTED: This command is disabled in this project. Use the web interface for migrations.";

    public function handle()
    {
        $this->error("⛔ MIGRATION COMMANDS ARE DISABLED");
        $this->newLine();
        $this->warn(
            "This project manages migrations automatically through the web interface.",
        );
        $this->warn("Direct database operations via artisan are not allowed.");
        $this->newLine();
        $this->info("To apply migrations:");
        $this->line("  1. Visit any page of the App in your browser");
        $this->line("  2. The UpdateController will handle migrations safely");
        $this->newLine();

        return 1;
    }
}
