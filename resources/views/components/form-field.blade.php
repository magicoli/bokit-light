@php
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? null; // Label can be null for containers
    $default = $field['default'] ?? null;
    $value = old($fieldName, $model->$fieldName ?? ($field['default'] ?? null));
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
            $type = "text";
            $inputClass = trim("date-range-input flatpickr-input $fieldsetClass");
            if($default) {
                $attributes["defaultDate"] = is_array($default) ? json_encode($default) : $default;
                $default = null;
            }
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
@endphp

@if($type === 'html')
    {{-- HTML content type - special case with no wrapper --}}
    <div name="{{ $fieldName }}" id="{{ $fieldName }}">
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

        @if($description)
            <p class="section-description">{{ $description }}</p>
        @endif

        @if(isset($field['items']) && is_array($field['items']))
            {{-- <div class="items"> --}}
            @foreach($field['items'] as $subKey => $subItem)
                @include('components.form-field', [
                    'fieldName' => $subKey,
                    'field' => $subItem,
                    'model' => $model,
                    'fieldOptions' => $fieldOptions
                ])
            @endforeach
            {{-- </div> --}}
        @endif
    </div>
@else
    <fieldset class="{{ $fieldsetClass }}">

    {{-- ACTUAL FIELD RENDERING --}}
    <label for="{{ $fieldName }}">
        {{ $label }}
        @if($required)
            <span class="required">*</span>
        @endif
    </label>

    @if($type === 'select')
        <select
            id="{{ $fieldName }}"
            name="{{ $fieldName }}"
            {!! $attrs !!}
        >
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
