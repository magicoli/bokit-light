@extends('layouts.admin')

@section('body-class')
    @parent resource-list {{ $resource }}-list
@endsection

@section('title', __('admin.' . $resource))

@section('content')
<div class="admin-page-header">
    <h1>{{ __('admin.' . $resource) }}</h1>
    <div class="actions">
        @if(Route::has('admin.' . $resource . '.create'))
            <a href="{{ route('admin.' . $resource . '.create') }}" class="btn btn-primary">
                {!! icon('plus') !!}
                {{ __('admin.add') }}
            </a>
        @endif
    </div>
</div>

<div class="admin-content">
    @if(isset($items) && $items->isEmpty())
        <p class="text-gray-500">{{ __('No items found') }}</p>
    @else
        <p class="text-gray-500">{{ __('List view - DataList.render() TODO') }}</p>
        
        @if(isset($items) && $items->hasPages())
            <div class="pagination-wrapper">
                {{ $items->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
