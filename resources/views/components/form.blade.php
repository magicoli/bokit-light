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
        <button type="submit" class="button primary ms-auto">{{ __('forms.save') }}</button>
    </div>
</form>
