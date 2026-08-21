<form id="{{ $formId }}" action="{{ $action }}" method="{{ $method === 'GET' ? 'GET' : 'POST' }}" class="form">
    @if($method !== 'GET')
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif
    @endif

    {!! $fieldsHtml !!}

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
