<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class Form
{
    private Model $model;
    private ?string $action = null;
    private string $method = "POST";
    private array $fields = [];
    private array $fieldOptions = [];
    private $fieldsCallback;

    public function __construct(Model $model, $fieldsCallback, ?string $action = null)
    {
        $this->model = $model;
        $this->fieldsCallback = $fieldsCallback;
        $this->action = $action;
        $this->loadFields($fieldsCallback);
    }

    /**
     * Load fields from callback
     */
    private function loadFields($callback): void
    {
        if (is_string($callback)) {
            // Method on model class
            $modelClass = get_class($this->model);
            if (!method_exists($modelClass, $callback)) {
                throw new \BadMethodCallException("Method {$callback} does not exist on {$modelClass}");
            }
            $this->fields = $modelClass::$callback();
        } elseif (is_array($callback)) {
            // [class, method] or [$object, method]
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('Array callback must be callable [class, method]');
            }
            $this->fields = call_user_func($callback);
        } elseif (is_callable($callback)) {
            // Direct callable/closure
            $this->fields = $callback();
        } else {
            throw new \InvalidArgumentException('Fields callback must be a string method name, array [class, method], or callable');
        }
    }

    /**
     * Set form action URL
     */
    public function action(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Set form method
     */
    public function method(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Set options for a specific field
     */
    public function fieldOptions(string $fieldName, array $options): self
    {
        $this->fieldOptions[$fieldName] = $options;
        return $this;
    }

    /**
     * Render the form using Blade view
     */
    public function render(): string
    {
        if (!$this->action) {
            throw new \RuntimeException(
                "Form action must be set before rendering. Use form('method', route(...))",
            );
        }

        // Generate class names for styling
        $modelSlug = strtolower(class_basename($this->model));
        $callbackSlug = is_string($this->fieldsCallback) 
            ? str_replace('_', '-', strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $this->fieldsCallback)))
            : 'custom';

        return view("components.form", [
            "action" => $this->action,
            "method" => $this->method,
            "fields" => $this->fields,
            "fieldOptions" => $this->fieldOptions,
            "model" => $this->model,
            "modelSlug" => $modelSlug,
            "callbackSlug" => $callbackSlug,
        ])->render();
    }
}
