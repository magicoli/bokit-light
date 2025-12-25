@php
    $type = $item['type'] ?? 'text';
@endphp

@if($type === 'section')
    {{-- Section with label and description --}}
    <div class="section" data-section="{{ $itemKey }}">
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
    </div>

@elseif($type === 'fields-row')
    {{-- Row of fields displayed side by side --}}
    <div class="fields-row" data-row="{{ $itemKey }}">
        @if(isset($item['label']))
            <div class="row-label">{{ $item['label'] }}</div>
        @endif
        
        @if(isset($item['items']))
            @foreach($item['items'] as $subKey => $subItem)
                <div class="form-field">
                    @include('components.form-field', ['fieldName' => $subKey, 'field' => $subItem])
                </div>
            @endforeach
        @endif
    </div>

@elseif($type === 'input-group')
    {{-- Input group for related inputs (e.g., amount + currency, date range) --}}
    <div class="input-group" data-group="{{ $itemKey }}">
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
    </div>

@else
    {{-- Regular field (text, select, checkbox, etc.) --}}
    <div class="form-field">
        @include('components.form-field', ['fieldName' => $itemKey, 'field' => $item])
    </div>
@endif
