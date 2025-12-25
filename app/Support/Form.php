<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class Form
{
    private Model $model;
    private ?string $action = null;
    private string $method = 'POST';
    private array $fields = [];
    private array $fieldOptions = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->loadFieldsFromModel();
    }

    /**
     * Load fields structure from model
     */
    private function loadFieldsFromModel(): void
    {
        $modelClass = get_class($this->model);
        
        if (method_exists($modelClass, 'formFields')) {
            $this->fields = $modelClass::formFields();
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
            throw new \RuntimeException('Form action must be set before rendering. Use form(route(...))');
        }
        
        return view('components.form', [
            'action' => $this->action,
            'method' => $this->method,
            'fields' => $this->fields,
            'fieldOptions' => $this->fieldOptions,
            'model' => $this->model,
        ])->render();
    }
}
