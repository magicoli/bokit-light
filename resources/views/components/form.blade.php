<form action="{{ $action }}" method="POST" class="form">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @foreach($fields as $itemKey => $item)
        @include('components.form-item', ['itemKey' => $itemKey, 'item' => $item])
    @endforeach

    <div class="form-actions">
        <button type="submit" class="button primary">{{ __('forms.save') }}</button>
    </div>
</form>
