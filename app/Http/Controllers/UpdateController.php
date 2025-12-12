<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class UpdateController extends Controller
{
    public function index()
    {
        $pending = $this->getPendingMigrations();
        
        return view('update', [
            'pendingMigrations' => $pending,
            'count' => count($pending),
        ]);
    }

    public function execute()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            
            return response()->json([
                'success' => true,
                'message' => 'Database updated successfully!',
                'output' => Artisan::output(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function getPendingMigrations(): array
    {
        try {
            $files = File::glob(database_path('migrations/*.php'));
            $ran = DB::table('migrations')->pluck('migration')->toArray();
            
            $pending = [];
            foreach ($files as $file) {
                $name = str_replace('.php', '', basename($file));
                if (!in_array($name, $ran)) {
                    $pending[] = [
                        'name' => $name,
                        'file' => basename($file),
                    ];
                }
            }
            
            return $pending;
        } catch (\Exception $e) {
            return [];
        }
    }
}
