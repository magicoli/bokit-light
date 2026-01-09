@extends('layouts.admin')

@section('body-class')
    @parent resource-create {{ $resource }}-create
@endsection

{{-- @section('title', __('admin.add_resource', [ "resource" => Str::singular(__('app.' . $resource))])) --}}
@section('title',
    \Illuminate\Support\Facades\Lang::has('admin.add_' . Str::singular($resource))
        ? __('admin.add_' . Str::singular($resource))
        : __('admin.add_resource', ['resource' => Str::singular(__('app.' . $resource))])
)

@section('content')
<p class="text-secondary">{{ __('Form creation - TODO') }}</p>
<a href="{{ route('admin.' . $resource . '.index') }}" class="btn">
    {{ __('Back to list') }}
</a>
@endsection
