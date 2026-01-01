<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class Form
{
    private ?Model $model = null;
    private array $values = [];
    private ?string $action = null;
    private string $method = "POST";
    private array $fields = [];
    private array $fieldOptions = [];
    private $fieldsCallback;
    private array $buttons = [];

    /**
     * @param Model|array|null $data Model instance, array of values, or null
     * @param callable|string|array $fieldsCallback Method name, callable, or array [class, method]
     * @param string|null $action Form action URL
     */
    public function __construct($data, $fieldsCallback, ?string $action = null)
    {
        // Handle different data types
        if ($data instanceof Model) {
            $this->model = $data;
        } elseif (is_array($data)) {
            $this->values = $data;
        } elseif ($data !== null) {
            throw new \InvalidArgumentException('Form data must be a Model, array, or null');
        }

        $this->fieldsCallback = $fieldsCallback;
        $this->action = $action;
        $this->loadFields($fieldsCallback);
        
        // Default buttons: reset + submit
        $this->buttons = [
            'reset' => [
                'label' => __('forms.reset'),
                'type' => 'reset',
                'class' => 'button secondary'
            ],
            'submit' => [
                'label' => __('forms.save'),
                'type' => 'submit',
                'class' => 'button primary ms-auto'
            ]
        ];
    }

    /**
     * Load fields from callback
     */
    private function loadFields($callback): void
    {
        if (is_string($callback)) {
            // Method on model class (or static method if no model)
            if ($this->model) {
                $modelClass = get_class($this->model);
                if (!method_exists($modelClass, $callback)) {
                    throw new \BadMethodCallException("Method {$callback} does not exist on {$modelClass}");
                }
                $this->fields = $modelClass::$callback();
            } else {
                throw new \InvalidArgumentException('Cannot use string method callback without a Model');
            }
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
     * Set values (for forms without model)
     */
    public function values(array $values): self
    {
        $this->values = $values;
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
     * Set submit button label
     */
    public function submitButton(string $label): self
    {
        $this->buttons['submit']['label'] = $label;
        return $this;
    }

    /**
     * Set all buttons at once
     * 
     * @param array $buttons Format: ['submit' => ['label' => '...', 'type' => '...', 'class' => '...']]
     */
    public function buttons(array $buttons): self
    {
        $this->buttons = $buttons;
        return $this;
    }

    /**
     * Add a button
     */
    public function addButton(string $key, string $label, array $attributes = []): self
    {
        $this->buttons[$key] = array_merge([
            'label' => $label,
            'type' => 'button',
            'class' => 'button'
        ], $attributes);
        return $this;
    }

    /**
     * Remove reset button
     */
    public function withoutReset(): self
    {
        unset($this->buttons['reset']);
        return $this;
    }

    /**
     * Add/override reset button (already included by default)
     */
    public function withReset(string $label = null): self
    {
        $this->buttons['reset'] = [
            'label' => $label ?? __('forms.reset'),
            'type' => 'reset',
            'class' => 'button secondary'
        ];
        return $this;
    }

    /**
     * Render the form using Blade view
     */
    public function render(): string
    {
        if (!$this->action) {
            throw new \RuntimeException(
                "Form action must be set before rendering. Use action(route(...))",
            );
        }

        // Generate class names for styling
        $modelSlug = $this->model 
            ? strtolower(class_basename($this->model))
            : 'nomodel';
        $callbackSlug = is_string($this->fieldsCallback) 
            ? str_replace('_', '-', strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $this->fieldsCallback)))
            : 'custom';

        return view("components.form", [
            "action" => $this->action,
            "method" => $this->method,
            "fields" => $this->fields,
            "fieldOptions" => $this->fieldOptions,
            "model" => $this->model,
            "values" => $this->values,
            "modelSlug" => $modelSlug,
            "callbackSlug" => $callbackSlug,
            "buttons" => $this->buttons,
        ])->render();
    }
}
