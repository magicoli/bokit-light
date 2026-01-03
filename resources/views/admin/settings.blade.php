@extends('layouts.admin')
@section('body-class')
    @parent admin-settings
@endsection

@section('title', __('admin.general_settings'))
@section('subtitle', 'La forme (debug)')

@section('content')
<div class="card max-w-4xl">
    {!! $form->render() !!}
</div>
@endsection
