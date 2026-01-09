@extends('layouts.admin')

@section('body-class')
    @parent resource-create {{ $resource }}-create
@endsection

@section('title',
    \Illuminate\Support\Facades\Lang::has('admin.add_' . Str::singular($resource))
        ? __('admin.add_' . Str::singular($resource))
        : __('admin.add_resource', ['resource' => Str::singular(__('app.' . $resource))])
)

@section('content')
    <div class="card">
        <div class="card-header">
            <a href="{{ route('admin.' . $resource . '.index') }}" class="btn btn-secondary">
                {{ __('admin.back_to_list') }}
            </a>
        </div>
        <div class="card-body">
            {!! $formContent !!}
        </div>
    </div>
@endsection
