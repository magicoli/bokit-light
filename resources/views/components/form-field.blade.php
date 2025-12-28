@php
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? null; // Label can be null for containers
    $default = $field['default'] ?? null;
    $value = old($fieldName, $model->$fieldName ?? $default ?? null);
    $value_only = old($fieldName, $model->$fieldName ?? null);
    $attributes = $field['attributes'] ?? [];

    $required = $field['required'] ?? false;
    if($field['required'] ?? false) {
        $attributes['required'] = true;
    }
    if($field['checked'] ?? false) {
        $attributes['checked'] = true;
    }
    if($field['disabled'] ?? false) {
        $attributes['disabled'] = true;
    }
    if($field['readonly'] ?? false) {
        $attributes['readonly'] = true;
    }

    $options = $fieldOptions[$fieldName] ?? $field['options'] ?? [];
    $description = $field['description'] ?? null;
    $inputClass = trim("input-{$type} " . ($field['class'] ?? ''));
    $placeholder = $field['placeholder'] ?? $attributes['placeholder'] ?? null;

    $container = "input";

    // Check if this is a container type (has items)
    $isContainer = in_array($type, ['html', 'section', 'fields-row', 'input-group']);

    $fieldsetClass = trim("form-field field-{$type} field-{$fieldName} " . ($field['fieldset_class'] ?? ''));

    // Type-specific processing
    switch($type) {
        case "html":
        case "section":
        case "fields-row":
        case "input-group":
            // Container types - no further processing needed
            break;

        case "date-range":
            $attributes['flatpickr-mode'] = "range";
        case "date":
            $type = "text";
            $inputClass = trim("flatpickr-input $fieldsetClass");
            break;

        case "switch":
        case "checkbox":
            switch(old($fieldName, $default)) {
                case "1":
                    $attributes['checked'] = true;
                    break;

                default:
                    unset($attributes['checked']);
            }
            $value = 1;
            break;

        case "textarea":
            $container = "textarea";
            break;

        default:
            // Standard input types - set label if not provided
            if ($label === null) {
                $label = ucfirst(str_replace('_', ' ', $fieldName));
            }
            break;
    }

    $attrs = array_to_attrs($attributes);
    $default = sanitize_field_value($default);
    $value = sanitize_field_value($value);
@endphp

@if($type === 'html')
    {{-- HTML content type - special case with no wrapper --}}
    <div id="{{ $fieldName }}" name="{{ $fieldName }}">
        {!! $field['value'] ?? '' !!}
    </div>

@elseif($isContainer)
    {{-- ALL CONTAINERS: section, fields-row, input-group --}}
        <div class="{{ $type }}">
        @if($label)
            @if($type === 'section')
                <h3 class="section-title">{{ $label }}</h3>
            @else
                <label>{{ $label }}</label>
            @endif
        @endif

        @if(isset($field['items']) && is_array($field['items']))
            @if($type=="input-group")
            <div class="items {{ $type }}-items">
            @endif
            @foreach($field['items'] as $subKey => $subItem)
                @include('components.form-field', [
                    'fieldName' => $subKey,
                    'field' => $subItem,
                    'model' => $model,
                    'fieldOptions' => $fieldOptions
                ])
            @endforeach
            @if($type=="input-group")
            </div>
            @endif
        @endif

        @if($description)
            <p class="description">{{ $description }}</p>
        @endif
    </div>
@else
    <fieldset id="{{ $fieldName }}-fieldset" class="{{ $fieldsetClass }}">

    {{-- ACTUAL FIELD RENDERING --}}
    @if($label)
    <label for="{{ $fieldName }}">
        {{ $label }}
        @if($required)
            <span class="required">*</span>
        @endif
    </label>
    @endif

    @if($type === 'select')
        @php
        $hasOptions = count($options) > 0;
        $selectPlaceholder = $hasOptions ? $placeholder : __('forms.no_options');
        $selectDisabled = !$hasOptions;
        @endphp
        <select
            id="{{ $fieldName }}"
            name="{{ $fieldName }}"
            placeholder="{{ $placeholder }}"
            data-no-options-text="{{ __('forms.no_options') }}"
            {{ $selectDisabled ? 'disabled' : '' }}
            {!! $attrs !!}
        >
            <option value="">{{ $selectPlaceholder }}</option>
            @foreach($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" {{ old($fieldName, $value) == $optValue ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
            @endforeach
        </select>

    @else
        <{{ $container }}
            type="{{ $type }}"
            id="{{ $fieldName }}"
            name="{{ $fieldName }}"
            class="{{ $inputClass }}"
            value="{{ old($fieldName, $value) }}"
            default="{{ $default }}"
            placeholder="{{ $placeholder }}"
            {!! $attrs !!}
        >
    @endif

    @if($description)
        <p class="field-description">{{ $description }}</p>
    @endif

    @error($fieldName)
        <span class="error">{{ $message }}</span>
    @enderror
    </fieldset>

@endif
