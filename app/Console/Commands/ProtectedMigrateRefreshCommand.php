<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProtectedMigrateRefreshCommand extends Command
{
    protected $signature = "migrate:refresh {--force}";
    protected $description = "PROTECTED: This command is disabled in this project.";

    public function handle()
    {
        $this->error(
            "⛔ MIGRATION COMMANDS ARE DISABLED - Use /legacy-admin/update",
        );
        return 1;
    }
}
