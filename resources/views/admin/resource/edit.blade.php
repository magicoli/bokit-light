@extends('layouts.admin')

@section('body-class')
    @parent edit-page edit-{{ $resource }} edit-{{ $resource }}-{{ $model->id }}
@endsection

@php
    $modelName = $model->display_name
    ?? $model->title
    ?? $model->name
    ?? Str::singular($resource) . ' #' . $model->id;
@endphp

{{-- Page title should include name of the object edited, not the resource class name --}}
@section('title', __('forms.edit_name', ['name' => $modelName]))

@section('content')
<div class="card">
    <div class="card-header">
        {{-- for debug, we don't need header and footer in final result --}}
        BEGIN FORM
    </div>
    <div class="card-body">
{{-- {!! $form->render() !!} --}}
    </div>
    <div class="card-footer">
        {{-- for debug, we don't need header and footer in final result --}}
        END FORM
    </div>
</div>
@endsection

@section('debug-info')
@endsection
