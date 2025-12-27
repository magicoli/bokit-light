<?php

namespace App\Traits;

use App\Support\Form;

trait FormTrait
{
    /**
     * Get a form instance for this model
     * 
     * @param callable|string $fieldsCallback Method name or callable that returns form layout
     * @param string|null $action Form action URL
     * @return Form
     */
    public static function form($fieldsCallback, ?string $action = null): Form
    {
        return new Form(new static(), $fieldsCallback, $action);
    }
}
