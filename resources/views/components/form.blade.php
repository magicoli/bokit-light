<form action="{{ $action }}" method="POST" class="form">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @foreach($fields as $itemKey => $item)
        @include('components.form-item', ['itemKey' => $itemKey, 'item' => $item])
    @endforeach

    <div class="flex form-actions">
        <button type="submit" class="button primary ms-auto">{{ __('forms.save') }}</button>
    </div>
</form>
