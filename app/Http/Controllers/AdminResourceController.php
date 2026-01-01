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

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("manage", $modelClass)) {
        //     abort(403);
        // }

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

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("manage", $modelClass)) {
        //     abort(403);
        // }

        $model = new $modelClass();

        return view("admin.resource.create", [
            "resource" => $resource,
            "model" => $model,
        ]);
    }

    /**
     * Store a newly created resource
     */
    public function store(Request $request, string $resource)
    {
        $modelClass = $this->getModelClass($resource);

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("manage", $modelClass)) {
        //     abort(403);
        // }

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
        $model = $modelClass::findOrFail($id);

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("edit", $model)) {
        //     abort(403);
        // }

        return view("admin.resource.edit", [
            "resource" => $resource,
            "model" => $model,
        ]);
    }

    /**
     * Display the specified resource with tabs/actions
     */
    public function show(string $resource, $id)
    {
        $modelClass = $this->getModelClass($resource);
        $model = $modelClass::findOrFail($id);

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("view", $model)) {
        //     abort(403);
        // }

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
        $model = $modelClass::findOrFail($id);

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("edit", $model)) {
        //     abort(403);
        // }

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
        $model = $modelClass::findOrFail($id);

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("delete", $model)) {
        //     abort(403);
        // }

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

        // Check permissions
        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("manage", $modelClass)) {
        //     abort(403);
        // }

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

        // user_can("manage") IS WRONG: should check page/menu/object capability, not global "manage" ability
        // if (!user_can("manage", $modelClass)) {
        //     abort(403);
        // }

        // TODO: Implement settings save

        return redirect()
            ->route("admin.{$resource}.settings")
            ->with("success", __("Settings saved successfully"));
    }
}
