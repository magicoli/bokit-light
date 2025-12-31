@extends('layouts.admin')

@section('body-class')
    @parent resource-show {{ $resource }}-show
@endsection

@section('title')
    {{ __('admin.' . Str::singular($resource)) }} #{{ $model->id }}
@endsection

@section('content')
    <div class="resource-show-container">
        <p class="placeholder-notice">
            {{ __('admin.show_view_placeholder') }}
        </p>

        {{-- TODO: Implement tabs/actions for object display --}}
        {{-- - View tab: Display object details --}}
        {{-- - Edit tab/button: Link to edit page --}}
        {{-- - Delete button: Confirm and delete --}}
        {{-- - Custom actions based on model --}}

        <div class="object-actions">
            <a href="{{ route('admin.' . $resource . '.edit', $model->id) }}" class="btn btn-primary">
                {{ __('admin.edit') }}
            </a>
            
            <form method="POST" action="{{ route('admin.' . $resource . '.destroy', $model->id) }}" style="display: inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" onclick="return confirm('{{ __('admin.confirm_delete') }}')">
                    {{ __('admin.delete') }}
                </button>
            </form>
            
            <a href="{{ route('admin.' . $resource . '.list') }}" class="btn btn-secondary">
                {{ __('admin.back_to_list') }}
            </a>
        </div>

        <div class="object-details">
            <h3>{{ __('admin.object_details') }}</h3>
            <pre>{{ print_r($model->toArray(), true) }}</pre>
        </div>
    </div>
@endsection
