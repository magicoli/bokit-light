@php
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
    $required = $field['required'] ?? false;
    $value = old($fieldName, $model->$fieldName ?? ($field['default'] ?? null));
    $attributes = $field['attributes'] ?? [];
    $options = $fieldOptions[$fieldName] ?? $field['options'] ?? [];
    $description = $field['description'] ?? null;
    $fieldsetClass = $field['fieldset_class'] ?? $field['class'] ?? '';
    $placeholder = $field['placeholder'] ?? $attributes['placeholder'] ?? null;
@endphp

<fieldset class="form-field field-{{ $type }} field-{{ $fieldName }} {{ $fieldsetClass }}">
    <label for="{{ $fieldName }}">
        {{ $label }}
        @if($required)
            <span class="required">*</span>
        @endif
    </label>

    @if($type === 'select')
        <select
            name="{{ $fieldName }}"
            id="{{ $fieldName }}"
            {{ $required ? 'required' : '' }}
            @foreach($attributes as $attr => $attrValue)
                @if($attr !== 'placeholder')
                    {{ $attr }}="{{ $attrValue }}"
                @endif
            @endforeach
        >
            <option value="">{{ $placeholder ?? __('forms.select') }}</option>
            @foreach($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" {{ old($fieldName, $value) == $optValue ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
            @endforeach
        </select>

    @elseif($type === 'textarea')
        <textarea
            name="{{ $fieldName }}"
            id="{{ $fieldName }}"
            {{ $required ? 'required' : '' }}
            @if($placeholder)
                placeholder="{{ $placeholder }}"
            @endif
            @foreach($attributes as $attr => $attrValue)
                @if($attr !== 'placeholder')
                    {{ $attr }}="{{ $attrValue }}"
                @endif
            @endforeach
        >{{ old($fieldName, $value) }}</textarea>

    @elseif($type === 'checkbox')
        <input
            type="checkbox"
            name="{{ $fieldName }}"
            id="{{ $fieldName }}"
            value="1"
            {{ old($fieldName, $value) ? 'checked' : '' }}
            @foreach($attributes as $attr => $attrValue)
                {{ $attr }}="{{ $attrValue }}"
            @endforeach
        >

    @else
        <input
            type="{{ $type }}"
            name="{{ $fieldName }}"
            id="{{ $fieldName }}"
            value="{{ old($fieldName, $value) }}"
            {{ $required ? 'required' : '' }}
            @if($placeholder)
                placeholder="{{ $placeholder }}"
            @endif
            @foreach($attributes as $attr => $attrValue)
                @if($attr !== 'placeholder')
                    {{ $attr }}="{{ $attrValue }}"
                @endif
            @endforeach
        >
    @endif

    @if($description)
        <p class="field-description">{{ $description }}</p>
    @endif

    @error($fieldName)
        <span class="error">{{ $message }}</span>
    @enderror
</fieldset>
