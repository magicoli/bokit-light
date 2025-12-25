<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DataList
{
    private Model $model;
    private ?Collection $items = null;
    private ?string $routePrefix = null;
    private array $columns = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->loadColumnsFromModel();
    }

    /**
     * Load columns configuration from model
     */
    private function loadColumnsFromModel(): void
    {
        $modelClass = get_class($this->model);
        
        if (method_exists($modelClass, 'listColumns')) {
            $this->columns = $modelClass::listColumns();
        }
    }

    /**
     * Set items to display
     */
    public function items(Collection $items): self
    {
        $this->items = $items;
        return $this;
    }

    /**
     * Set route prefix for actions
     */
    public function routePrefix(string $prefix): self
    {
        $this->routePrefix = $prefix;
        return $this;
    }

    /**
     * Format value based on column configuration
     */
    private function formatValue($value, array $column): string
    {
        $format = $column['format'] ?? 'text';
        
        return match($format) {
            'boolean' => $value ? '✓' : '✗',
            'currency' => number_format($value, 2) . ' €',
            'date' => $value ? $value->format('Y-m-d') : '',
            'datetime' => $value ? $value->format('Y-m-d H:i') : '',
            default => (string) $value,
        };
    }

    /**
     * Render the list using Blade view
     */
    public function render(): string
    {
        if (!$this->items) {
            throw new \RuntimeException('Items must be set before rendering. Use list($items, ...)');
        }
        
        return view('components.data-list', [
            'items' => $this->items,
            'columns' => $this->columns,
            'routePrefix' => $this->routePrefix,
            'formatValue' => fn($value, $column) => $this->formatValue($value, $column),
        ])->render();
    }
}
