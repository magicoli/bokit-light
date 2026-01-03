{{-- Container type: html --}}
@if($field['type'] === 'html')
    <div id="{{ $field['name'] }}" name="{{ $field['name'] }}">
        {!! $field['value'] ?? '' !!}
    </div>

{{-- Container types: section, fields-row, fields-group, input-group --}}
@elseif($field['isContainer'])
    <div class="{{ $field['type'] }} {{ $field['fieldsetClass'] }}">
        @if($field['label'])
            @if($field['type'] === 'section')
                <h3 class="section-title">{{ $field['label'] }}</h3>
            @else
                <label>{{ $field['label'] }}</label>
            @endif
        @endif

        @if($field['type'] === 'input-group')
            <div class="items input-group-items">
                {!! $field['items_content'] ?? '' !!}
            </div>
        @else
            {!! $field['items_content'] ?? '' !!}
        @endif

        @if($field['description'])
            <p class="description">{{ $field['description'] }}</p>
        @endif
    </div>

{{-- Regular fields --}}
@else
    @php
    $value = old($field['name'], $field['value']);
    @endphp

    <fieldset id="{{ $field['name'] }}-fieldset" class="{{ $field['fieldsetClass'] }}">

        @if($field['label'])
        <label for="{{ $field['name'] }}">
            {{ $field['label'] }}
            @if($field['required'] ?? false)
                <span class="required">*</span>
            @endif
        </label>
        @endif

        @if($field['type'] === 'select')
            @php
            $hasOptions = count($field['options']) > 0;
            $selectPlaceholder = $hasOptions ? $field['placeholder'] : __('forms.no_options');
            @endphp
            <select
                id="{{ $field['name'] }}"
                name="{{ $field['name'] }}"
                class="{{ $field['inputClass'] }}"
                placeholder="{{ $field['placeholder'] }}"
                data-no-options-text="{{ __('forms.no_options') }}"
                {{ !$hasOptions ? 'disabled' : '' }}
                {!! $field['attrs'] !!}
            >
                <option value="">{{ $selectPlaceholder }}</option>
                @foreach($field['options'] as $optValue => $optLabel)
                    <option value="{{ $optValue }}" {{ $value == $optValue ? 'selected' : '' }}>
                        {{ $optLabel }}
                    </option>
                @endforeach
            </select>

        @elseif($field['type'] === 'textarea')
            <textarea
                id="{{ $field['name'] }}"
                name="{{ $field['name'] }}"
                class="{{ $field['inputClass'] }}"
                placeholder="{{ $field['placeholder'] }}"
                {!! $field['attrs'] !!}
            >{{ $value }}</textarea>

        @elseif($field['type'] === 'link')
            <a
                id="{{ $field['name'] }}"
                name="{{ $field['name'] }}"
                class="{{ $field['inputClass'] }}"
                {!! $field['attrs'] !!}
            >{{ $value }}</a>

        @else
            {{-- Default: standard input element --}}
            @if(!is_string($value) && $value !== null)
                {{-- Error: non-text value for unknown type --}}
                <div class="field-type-error" style="padding: 0.5rem; background: #fee; border: 1px solid #c33; border-radius: 4px; color: #c33;">
                    <strong>{{ $field['name'] }}:</strong> Unsupported type {{ gettype($value) }}
                </div>
            @else
                <{{ $field['container'] }}
                    type="{{ $field['type'] }}"
                    id="{{ $field['name'] }}"
                    name="{{ $field['name'] }}"
                    class="{{ $field['inputClass'] }}"
                    value="{{ $value }}"
                    placeholder="{{ $field['placeholder'] }}"
                    {!! $field['attrs'] !!}
                >
            @endif
        @endif

        @if($field['description'])
            <p class="field-description">{{ $field['description'] }}</p>
        @endif

        @error($field['name'])
            <span class="error">{{ $message }}</span>
        @enderror
    </fieldset>
@endif
