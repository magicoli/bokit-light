@php
    $type = $item['type'] ?? 'text';
    $label = $item['label'] ?? ucfirst(str_replace('_', ' ', $itemKey));
    $fieldsetClass = $item['fieldset_class'] ?? $item['class'] ?? '';
@endphp

<div class="form-field {{ $type }} {{ $itemKey }} form-item-{{ $itemKey }} {{ $type }}-wrapper {{ $fieldsetClass }}" data-{{ $type}}="{{ $itemKey }}">
    @if($type === 'html')
        <div name="{{ $itemKey }}" id="{{ $itemKey }}">
            {!! $item['value'] ?? "" !!}
        </div>

    @elseif($type === 'date-range')
    {{-- Date range creates two date fields (from/to) --}}
    @php
        $fromField = $itemKey . '_from';
        $toField = $itemKey . '_to';
    @endphp

    <fieldset class="form-field field-date-range field-{{ $itemKey }}">
        @if($label)
            <label class="date-range-label">{{ $label }}</label>
        @endif

        <div class="date-range">
            <input
                type="date"
                name="{{ $fromField }}"
                id="{{ $fromField }}"
                value="{{ old($fromField, $model->$fromField ?? '') }}"
                @foreach(($item['attributes'] ?? []) as $attr => $attrValue)
                    {{ $attr }}="{{ $attrValue }}"
                @endforeach
            >
            <span class="separator">→</span>
            <input
                type="date"
                name="{{ $toField }}"
                id="{{ $toField }}"
                value="{{ old($toField, $model->$toField ?? '') }}"
                @foreach(($item['attributes'] ?? []) as $attr => $attrValue)
                    {{ $attr }}="{{ $attrValue }}"
                @endforeach
            >
        </div>

        @if(isset($item['description']))
            <p class="field-description">{{ $item['description'] }}</p>
        @endif

        @error($fromField)
            <span class="error">{{ $message }}</span>
        @enderror
        @error($toField)
            <span class="error">{{ $message }}</span>
        @enderror
    </fieldset>

    @elseif($type === 'section')
        {{-- Section with label and description --}}
        @if(isset($item['label']))
            <div class="section-header">
                <h3 class="section-title">{{ $item['label'] }}</h3>
            </div>
        @endif

        @if(isset($item['description']))
            <p class="section-description">{{ $item['description'] }}</p>
        @endif

        @if(isset($item['items']))
            @foreach($item['items'] as $subKey => $subItem)
                @include('components.form-item', ['itemKey' => $subKey, 'item' => $subItem])
            @endforeach
        @endif

    @elseif($type === 'fields-row')
        {{-- Row of fields displayed side by side --}}
        @if(isset($item['label']))
            <div class="row-label">{{ $item['label'] }}</div>
        @endif

        @if(isset($item['items']))
            @foreach($item['items'] as $subKey => $subItem)
                @php
                    $subType = $subItem['type'] ?? 'text';
                @endphp

                @if(in_array($subType, ['date-range', 'section', 'fields-row', 'input-group']))
                    {{-- Recursive call for special types --}}
                    @include('components.form-item', ['itemKey' => $subKey, 'item' => $subItem])
                @else
                    {{-- Direct field rendering for simple types --}}
                    @include('components.form-field', ['fieldName' => $subKey, 'field' => $subItem])
                @endif
            @endforeach
        @endif

    @elseif($type === 'input-group')
    {{-- Input group for related inputs (e.g., amount + currency, date range) --}}
        @if(isset($item['label']))
            <label>{{ $item['label'] }}</label>
        @endif

        <div class="input-group-items">
            @if(isset($item['items']))
                @foreach($item['items'] as $subKey => $subItem)
                    @include('components.form-field', ['fieldName' => $subKey, 'field' => $subItem])
                @endforeach
            @endif
        </div>

    @else
    {{-- Regular field (text, select, checkbox, etc.) --}}
        @include('components.form-field', ['fieldName' => $itemKey, 'field' => $item])
    @endif
</div>
