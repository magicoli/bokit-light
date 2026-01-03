<?php

namespace App\Traits;

use App\Support\Form;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Settings pages management trait
 * 
 * Provides settings page functionality for both:
 * - Site-wide settings (when used in controllers)
 * - Model-specific options (when used in models)
 * 
 * Storage:
 * - Global: Options table via Options::get()/set()
 * - Model: JSON column 'options' in model table
 * 
 * Required in using class:
 * - settingsFields(): array - Define form fields
 * - $settingsCapability (optional) - Required capability (default: 'admin')
 */
trait SettingsTrait
{
    /**
     * Register settings routes
     * Called from AppServiceProvider
     */
    public static function registerSettingsRoutes(): void
    {
        $class = static::class;
        $isModel = is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class);

        if ($isModel) {
            // Model settings routes: /admin/{resource}/{id}/settings
            $resource = str_replace('_', '-', \Illuminate\Support\Str::plural(
                \Illuminate\Support\Str::snake(class_basename($class))
            ));
            
            Route::middleware(['auth', 'admin'])->group(function () use ($resource, $class) {
                Route::get("/admin/{$resource}/{id}/settings", [$class, 'settings'])
                    ->name("admin.{$resource}.settings");
                Route::post("/admin/{$resource}/{id}/settings", [$class, 'saveSettings'])
                    ->name("admin.{$resource}.settings.save");
            });
        } else {
            // Controller settings routes: /admin/settings (already defined in web.php)
            // No need to register here
        }
    }

    /**
     * Show settings page
     */
    public function settings()
    {
        $fieldsData = static::settingsFields();
        $this->checkSettingsAccess($fieldsData);

        $isModel = $this instanceof \Illuminate\Database\Eloquent\Model;
        
        // Extract capability and fields
        $capability = $fieldsData['capability'] ?? null;
        unset($fieldsData['capability']);
        $fields = $fieldsData;
        
        // Get current values from actual fields (not sections/containers)
        $values = [];
        $this->extractFieldKeys($fields, $values);
        
        // Set action URL
        if ($isModel) {
            $action = route('admin.' . $this->getResourceName() . '.settings.save', $this->id);
        } else {
            $action = route('admin.settings.save');
        }

        // Create form
        $form = new Form(
            $values,
            fn() => $fields,
            $action
        );

        return view('admin.settings', [
            'form' => $form,
            'model' => $isModel ? $this : null,
            'title' => $isModel 
                ? __('admin.settings_for', ['name' => $this->name ?? $this->id])
                : __('admin.general_settings'),
        ]);
    }
    
    /**
     * Recursively extract field keys and get their values
     */
    private function extractFieldKeys(array $fields, array &$values): void
    {
        foreach ($fields as $key => $field) {
            // Skip non-field entries
            if (!is_array($field)) {
                continue;
            }
            
            // If it's a container with items, recurse
            if (isset($field['items']) && is_array($field['items'])) {
                $this->extractFieldKeys($field['items'], $values);
            } else {
                // It's an actual field - get its value
                $values[$key] = $this->get($key);
            }
        }
    }

    /**
     * Save settings
     */
    public function saveSettings(Request $request)
    {
        $fieldsData = static::settingsFields();
        $this->checkSettingsAccess($fieldsData);

        // Extract fields (skip capability)
        unset($fieldsData['capability']);
        
        // Get all field keys
        $fieldKeys = [];
        $this->extractFieldKeysOnly($fieldsData, $fieldKeys);
        
        // Save each value using set() which handles validation
        foreach ($fieldKeys as $key) {
            if ($request->has($key)) {
                $this->set($key, $request->input($key));
            }
        }

        // Redirect back
        $isModel = $this instanceof \Illuminate\Database\Eloquent\Model;
        if ($isModel) {
            $redirectRoute = route('admin.' . $this->getResourceName() . '.settings', $this->id);
        } else {
            $redirectRoute = route('admin.settings');
        }

        return redirect($redirectRoute)
            ->with('success', __('app.settings_saved'));
    }
    
    /**
     * Extract only field keys (for validation/saving)
     */
    private function extractFieldKeysOnly(array $fields, array &$keys): void
    {
        foreach ($fields as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            
            if (isset($field['items']) && is_array($field['items'])) {
                $this->extractFieldKeysOnly($field['items'], $keys);
            } else {
                $keys[] = $key;
            }
        }
    }

    /**
     * Get option value with fallback to global options
     * 
     * @param string $key Option key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function options(string $key, $default = null)
    {
        return $this->get($key, $default);
    }

    /**
     * Get option value with fallback to global options
     * 
     * @param string $key Option key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $isModel = $this instanceof \Illuminate\Database\Eloquent\Model;
        
        if ($isModel) {
            // Try to get from model's options column
            $modelOptions = $this->getAttribute('options') ?? [];
            
            if (array_key_exists($key, $modelOptions)) {
                return $modelOptions[$key];
            }
            
            // Fallback to global options
            return options($key, $default);
        }
        
        // Controller context - use global options
        return options($key, $default);
    }

    /**
     * Set option value with validation
     * 
     * @param string $key Option key
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, $value): void
    {
        // Get field definition for validation
        $fieldsData = static::settingsFields();
        unset($fieldsData['capability']);
        
        $fieldDef = $this->findFieldDefinition($key, $fieldsData);
        
        if ($fieldDef) {
            // Validate if rules are defined
            if (isset($fieldDef['validation'])) {
                $validator = \Illuminate\Support\Facades\Validator::make(
                    [$key => $value],
                    [$key => $fieldDef['validation']]
                );
                
                if ($validator->fails()) {
                    throw new \Illuminate\Validation\ValidationException($validator);
                }
            }
            
            // Check required
            if (($fieldDef['required'] ?? false) && empty($value)) {
                throw new \InvalidArgumentException("Field {$key} is required");
            }
        }
        
        $isModel = $this instanceof \Illuminate\Database\Eloquent\Model;
        
        if ($isModel) {
            // Save to model's options column
            $modelOptions = $this->getAttribute('options') ?? [];
            $modelOptions[$key] = $value;
            $this->setAttribute('options', $modelOptions);
            $this->save();
        } else {
            // Save to global options
            Options::set($key, $value);
        }
    }
    
    /**
     * Find field definition by key (recursively search in sections)
     */
    private function findFieldDefinition(string $key, array $fields): ?array
    {
        foreach ($fields as $fieldKey => $field) {
            if ($fieldKey === $key) {
                return $field;
            }
            
            if (isset($field['items']) && is_array($field['items'])) {
                $found = $this->findFieldDefinition($key, $field['items']);
                if ($found) {
                    return $found;
                }
            }
        }
        
        return null;
    }

    /**
     * Check if user has access to settings
     */
    private function checkSettingsAccess(array $fieldsData): void
    {
        $capability = $fieldsData['capability'] ?? 'admin';
        
        if (!user_can($capability)) {
            abort(403);
        }
    }

    /**
     * Get resource name for routes (models only)
     */
    private function getResourceName(): string
    {
        return str_replace('_', '-', \Illuminate\Support\Str::plural(
            \Illuminate\Support\Str::snake(class_basename($this))
        ));
    }

    /**
     * Add 'options' to fillable when trait is used
     */
    public function initializeSettingsTrait(): void
    {
        $this->fillable = array_merge($this->fillable ?? [], ['options']);
    }
}
