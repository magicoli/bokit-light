@extends('layouts.admin')
@section('body-class')
    @parent admin-settings
@endsection

@section('title', __('admin.general_settings'))

@section('content')
<div class="bg-white rounded-lg shadow-sm p-6 max-w-4xl">
    {!! $form->render() !!}
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-timezone').select2({
        placeholder: 'Search timezone...',
        width: '100%'
    });
});
</script>
@endsection
