<form action="{{ $action }}" method="POST" class="form form-{{ $modelSlug }} form-{{ $callbackSlug }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @foreach($fields as $fieldName => $field)
        @include('components.form-field', [
            'fieldName' => $fieldName,
            'field' => $field,
            'model' => $model,
            'fieldOptions' => $fieldOptions
        ])
    @endforeach

    <div class="flex form-actions">
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
