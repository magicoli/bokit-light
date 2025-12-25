<?php

namespace App\Traits;

use App\Support\Form;

trait FormTrait
{
    /**
     * Get a form instance for this model
     */
    public static function form(?string $action = null): Form
    {
        $form = new Form(new static());
        
        if ($action) {
            $form->action($action);
        }
        
        return $form;
    }
}
