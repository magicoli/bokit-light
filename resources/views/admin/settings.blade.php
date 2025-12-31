@extends('layouts.admin')
@section('body-class')
    @parent admin-settings
@endsection

@php
    use Illuminate\Support\Facades\Cache;
@endphp

@section('title', __('admin.general_settings'))

@section('content')
<form method="POST" action="{{ route('admin.settings.save') }}" class="bg-white rounded-lg shadow-sm p-6 max-w-4xl">
    @csrf

    <!-- Display Timezone -->
    <div class="mb-6">
        <label for="display_timezone" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('app.display_timezone') }}
        </label>
        <select name="display_timezone" id="display_timezone"
                class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            @php
                $currentTz = options('display.timezone', config('app.timezone', 'UTC'));
            @endphp
            {!! \App\Models\User::timezoneOptionsHtml($currentTz) !!}
        </select>
        <p class="text-sm text-gray-500 mt-1">
            {{ __('app.display_timezone_help') }}
        </p>
    </div>

    <div class="flex justify-end">
        <button type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
            {{ __('app.save_settings') }}
        </button>
    </div>
</form>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#display_timezone').select2({
        placeholder: 'Search timezone...',
        width: '100%'
    });
});
</script>
@endsection
