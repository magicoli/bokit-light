<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\IcalSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportConfig extends Command
{
    protected $signature = "bokit:import-config {file? : JSON config file path}";

    protected $description = "Import properties and iCal sources from JSON config";

    public function handle(): int
    {
        $filePath =
            $this->argument("file") ?? storage_path("config/properties.json");

        if (!file_exists($filePath)) {
            $this->error("Config file not found: {$filePath}");
            $this->newLine();
            $this->info(
                "Create a config file at {$filePath} or specify a different path.",
            );
            return self::FAILURE;
        }

        $this->info("📥 Importing config from: {$filePath}");
        $this->newLine();

        // Read and parse JSON
        $json = file_get_contents($filePath);
        $config = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON: " . json_last_error_msg());
            return self::FAILURE;
        }

        if (!isset($config["properties"])) {
            $this->error('Invalid config: missing "properties" key');
            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $stats = ["properties" => 0, "sources" => 0];

            foreach ($config["properties"] as $propertyData) {
                // Create or update property
                $property = Property::updateOrCreate(
                    ["slug" => $propertyData["slug"]],
                    [
                        "name" => $propertyData["name"],
                        "color" => $propertyData["color"] ?? "#3B82F6",
                        // 'capacity' => $propertyData['capacity'] ?? null,
                        "is_active" => $propertyData["is_active"] ?? true,
                        "settings" => $propertyData["settings"] ?? [],
                    ],
                );

                $this->line("✓ Property: <fg=cyan>{$property->name}</>");
                $stats["properties"]++;

                // Sync iCal sources
                if (isset($propertyData["ical_sources"])) {
                    foreach ($propertyData["ical_sources"] as $sourceData) {
                        $source = IcalSource::updateOrCreate(
                            [
                                "property_id" => $property->id,
                                "url" => $sourceData["url"],
                            ],
                            [
                                "name" => $sourceData["name"],
                                "sync_enabled" =>
                                    $sourceData["sync_enabled"] ?? true,
                            ],
                        );

                        $this->line("  → Source: {$source->name}");
                        $stats["sources"]++;
                    }
                }

                $this->newLine();
            }

            DB::commit();

            $this->info("✅ Import successful!");
            $this->line("  Properties: {$stats["properties"]}");
            $this->line("  iCal sources: {$stats["sources"]}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Import failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
