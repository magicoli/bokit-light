@extends('layouts.admin')

@section('body-class')
    @parent edit-page edit-{{ $resource }} edit-{{ $resource }}-{{ $model->id }}
@endsection

{{-- Page title should include name of the object edited, not the resource class name --}}
@section('title', __('forms.edit_name', ['name' => $displayName]))

@section('content')
<div class="card">
    <div class="card-header">
        {{-- for debug, we don't need header and footer in final result --}}
        BEGIN FORM
    </div>
    <div class="card-body">
        {!! $formContent !!}
    </div>
    <div class="card-footer">
        {{-- for debug, we don't need header and footer in final result --}}
        END FORM
    </div>
</div>
@endsection
