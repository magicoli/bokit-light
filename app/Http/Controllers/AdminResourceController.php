<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Support\DataList;
use App\Support\Form;

/**
 * Generic admin resource controller
 * Handles all resources using AdminResourceTrait
 */
class AdminResourceController extends Controller
{
    /**
     * Get model class from route parameter
     */
    private function getModelClass(string $resource): string
    {
        $className = Str::studly(Str::singular($resource));
        $modelClass = "App\\Models\\{$className}";

        if (!class_exists($modelClass)) {
            abort(404, "Model {$modelClass} not found");
        }

        return $modelClass;
    }

    /**
     * Get capability from model's config
     */
    private function getCapability(string $modelClass): string
    {
        if (method_exists($modelClass, "getConfig")) {
            $config = $modelClass::getConfig();
            return $config["capability"] ?? "manage";
        }
        return "manage";
    }

    /**
     * Check if user has access to this resource section
     */
    private function checkAccess(string $modelClass): void
    {
        $capability = $this->getCapability($modelClass);

        if (!user_can($capability)) {
            abort(403);
        }
    }

    /**
     * Check if user can access a specific object
     * For property_manager: check ownership
     */
    private function checkObjectAccess(string $modelClass, $object): void
    {
        $user = auth()->user();

        // Admins always have access
        if ($user->isAdmin()) {
            return;
        }

        // Managers have global access
        if ($user->hasRole("manager")) {
            return;
        }

        // Property managers: check ownership
        if ($user->hasRole("property_manager")) {
            // Check via isOwnedBy method if available
            if (method_exists($object, "isOwnedBy")) {
                if (!$object->isOwnedBy($user)) {
                    abort(403);
                }
                return;
            }

            // Check via property relationship
            if (method_exists($object, "property") && $object->property) {
                $property = $object->property;
                if (
                    $property->users()->where("users.id", $user->id)->exists()
                ) {
                    return;
                }
                abort(403);
            }

            // Direct property_id check for Property model itself
            if ($object instanceof \App\Models\Property) {
                if ($object->users()->where("users.id", $user->id)->exists()) {
                    return;
                }
                abort(403);
            }

            // No ownership found
            abort(403);
        }
    }

    /**
     * Display a listing of resources
     */
    public function index(string $resource)
    {
        // Index redirects to list by default
        return $this->list($resource);
    }

    /**
     * Show the form for creating a new resource
     */
    public function list(string $resource)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        $model = new $modelClass();

        return view("admin.resource.list", [
            "resource" => $resource,
            "model" => $model,
        ]);
    }

    /**
     * Show the form for creating a new resource
     */
    public function create(string $resource)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        $model = new $modelClass();

        // Create form - formAdd() exists in ModelConfigTrait by default
        $form = new Form(
            $model,
            [$modelClass, 'formAdd'],
            route("admin.{$resource}.store"),
        );

        return view("admin.resource.create", [
            "resource" => $resource,
            "model" => $model,
            "formContent" => $form->render(),
        ]);
    }

    /**
     * Store a newly created resource
     */
    public function store(Request $request, string $resource)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        // TODO: Validation
        $item = $modelClass::create($request->all());

        return redirect()
            ->route("admin.{$resource}.index")
            ->with("success", __("Item created successfully"));
    }

    /**
     * Show the form for editing the specified resource
     */
    public function edit(string $resource, $id)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        $model = $modelClass::findOrFail($id);
        $this->checkObjectAccess($modelClass, $model);

        // Create form - formEdit() exists in ModelConfigTrait by default
        $form = new Form(
            $model,
            [$modelClass, 'formEdit'],
            route("admin.{$resource}.update", $id),
        );

        $displayName =
            $model->display_name ??
            ($model->title ??
                ($model->name ?? Str::singular($resource) . " #" . $model->id));

        return view("admin.resource.edit", [
            "resource" => $resource,
            "model" => $model,
            "displayName" => $displayName,
            "formContent" => $form->render(),
        ]);
    }

    /**
     * Display the specified resource with tabs/actions
     */
    public function show(string $resource, $id)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        $model = $modelClass::findOrFail($id);
        $this->checkObjectAccess($modelClass, $model);

        return view("admin.resource.show", [
            "resource" => $resource,
            "model" => $model,
        ]);
    }

    /**
     * Update the specified resource
     */
    public function update(Request $request, string $resource, $id)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        $model = $modelClass::findOrFail($id);
        $this->checkObjectAccess($modelClass, $model);

        // TODO: Validation
        $model->update($request->all());

        return redirect()
            ->route("admin.{$resource}.index")
            ->with("success", __("Item updated successfully"));
    }

    /**
     * Remove the specified resource
     */
    public function destroy(string $resource, $id)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        $model = $modelClass::findOrFail($id);
        $this->checkObjectAccess($modelClass, $model);

        $model->delete();

        return redirect()
            ->route("admin.{$resource}.index")
            ->with("success", __("Item deleted successfully"));
    }

    /**
     * Show resource settings
     */
    public function settings(string $resource)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        $model = new $modelClass();

        return view("admin.resource.settings", [
            "resource" => $resource,
            "model" => $model,
        ]);
    }

    /**
     * Save resource settings
     */
    public function saveSettings(Request $request, string $resource)
    {
        $modelClass = $this->getModelClass($resource);
        $this->checkAccess($modelClass);

        // TODO: Implement settings save

        return redirect()
            ->route("admin.{$resource}.settings")
            ->with("success", __("Settings saved successfully"));
    }
}
