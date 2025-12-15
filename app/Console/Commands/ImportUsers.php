<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ImportUsers extends Command
{
    protected $signature = "bokit:import-users {file : Path to JSON file containing users}";
    protected $description = "Import users from a JSON file";

    public function handle(): int
    {
        $filePath = $this->argument("file");

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        try {
            $content = file_get_contents($filePath);
            $users = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($users)) {
                throw new \Exception("File must contain a JSON array");
            }

            $this->info("Found " . count($users) . " user(s) in {$filePath}");

            $created = 0;
            $updated = 0;

            foreach ($users as $userData) {
                if (
                    !isset($userData["email"]) ||
                    !isset($userData["password"])
                ) {
                    $this->warn("Skipping user - missing email or password");
                    continue;
                }

                try {
                    $user = User::updateOrCreate(
                        ["email" => $userData["email"]],
                        [
                            "name" => $userData["name"] ?? $userData["email"],
                            "password" => Hash::make($userData["password"]),
                            "is_admin" => $userData["is_admin"] ?? false,
                            "roles" => $userData["roles"] ?? null,
                        ],
                    );

                    if ($user->wasRecentlyCreated) {
                        $created++;
                        $this->line("✓ Created user: {$userData["email"]}");
                    } else {
                        $updated++;
                        $this->line("↻ Updated user: {$userData["email"]}");
                    }
                } catch (\Exception $e) {
                    $this->error(
                        "Failed to process user {$userData["email"]}: " .
                            $e->getMessage(),
                    );
                }
            }

            $this->newLine();
            $this->info(
                "Import completed: {$created} created, {$updated} updated",
            );

            return Command::SUCCESS;
        } catch (\JsonException $e) {
            $this->error("Invalid JSON in {$filePath}: " . $e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("Error reading {$filePath}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
