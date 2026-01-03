@vite(['resources/css/form.css'])

<form id="{{ $modelSlug }}-{{ $callbackSlug }}" action="{{ $action }}" method="{{ $method === 'GET' ? 'GET' : 'POST' }}" class="form form-{{ $modelSlug }} form-{{ $callbackSlug }}">
    @if($method !== 'GET')
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif
    @endif

    @foreach($fields as $fieldName => $field)
        @include('components.form-field', [
            'fieldName' => $fieldName,
            'field' => $field,
            'model' => $model,
            'values' => $values,
            'fieldOptions' => $fieldOptions
        ])
    @endforeach

    <div class="form-actions">
        @foreach($buttons as $key => $button)
            <button
                type="{{ $button['type'] ?? 'button' }}"
                class="{{ $button['class'] ?? 'button' }}"
                @if(isset($button['attributes']))
                    {!! array_to_attrs($button['attributes']) !!}
                @endif
            >
                {{ $button['label'] }}
            </button>
        @endforeach
    </div>
</form>
