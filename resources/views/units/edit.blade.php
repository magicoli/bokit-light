@extends('layouts.app')

@section('title', __('app.edit_unit_title') . ' - ' . $unit->name)

@section('styles')
@vite('resources/css/forms.css')
@vite('resources/css/properties.css')
@vite('resources/css/units.css')
@endsection

@section('scripts')
<script>
// Initialize sources data for Alpine
window.unitSourcesData = {!! json_encode($unit->icalSources->map(function($source) {
    return [
        'id' => $source->id,
        'type' => $source->type,
        'url' => $source->url,
        'last_sync_at' => $source->last_synced_at ? $source->last_synced_at->diffForHumans() : null,
    ];
})->values()) !!};
</script>
@vite('resources/js/units-edit.js')
@endsection

@section('content')
<div class="unit-edit-container">
    <!-- Header -->
    <div class="unit-header">
        <a href="{{ route('properties') }}" class="back-link">
            ← {{ __('app.back_to_properties') }}
        </a>
        <div class="title-row">
            <h1 class="title">{{ $unit->property->name }} / {{ $unit->name }}</h1>
            <span class="subtitle">{{ __('app.edit_unit_title') }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">
            <svg class="icon" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p class="message">{{ session('success') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('units.update', [$unit->property, $unit]) }}" class="unit-form" x-data="unitForm()">
        @csrf
        @method('PUT')

        <!-- Basic Information -->
        <div class="section">
            <h2 class="title">{{ __('app.basic_information') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-field">
                    <label class="label">
                        {{ __('app.unit_name') }} <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $unit->name) }}"
                        class="input"
                        required
                        @input="updateSlug"
                        x-ref="nameInput"
                    >
                    @error('name')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label class="label">
                        {{ __('app.slug') }} <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="slug"
                        value="{{ old('slug', $unit->slug) }}"
                        class="input"
                        required
                        x-ref="slugInput"
                        @input="manuallyEdited = true"
                    >
                    @error('slug')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Calendar Sources -->
        <div class="section">
            <div class="header">
                <h2 class="title">{{ __('app.calendar_sources_title') }}</h2>
                <button
                    type="button"
                    @click="addSource"
                    class="add-button"
                >
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('app.add_source') }}
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(source, index) in sources" :key="index">
                    <div class="source-item">
                        <button
                            type="button"
                            @click="removeSource(index)"
                            class="remove-button"
                            x-show="sources.length > 1"
                        >
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <input type="hidden" :name="`sources[${index}][id]`" x-model="source.id">

                        <div class="source-grid">
                            <div class="form-field">
                                <label class="label">{{ __('app.type') }}</label>
                                <select
                                    :name="`sources[${index}][type]`"
                                    x-model="source.type"
                                    class="input"
                                >
                                    <option value="ical">{{ __('app.ical') }}</option>
                                    <option value="beds24" disabled>{{ __('app.beds24_coming_soon') }}</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label class="label">{{ __('app.url') }} <span class="required">*</span></label>
                                <input
                                    type="url"
                                    :name="`sources[${index}][url]`"
                                    x-model="source.url"
                                    class="input"
                                    placeholder="https://calendar.example.com/my-unit.ics"
                                    required
                                >
                            </div>
                        </div>

                        <template x-if="source.last_sync_at">
                            <div class="last-sync">
                                {{ __('app.last_synced') }}: <span x-text="source.last_sync_at"></span>
                            </div>
                        </template>
                    </div>
                </template>

                <div x-show="sources.length === 0" class="sources-empty">
                    {{ __('app.no_sources_configured') }}
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <a href="{{ route('properties') }}" class="cancel-link">
                {{ __('app.cancel') }}
            </a>
            <button
                type="submit"
                class="submit-button"
            >
                {{ __('app.save_changes') }}
            </button>
        </div>
    </form>
</div>
@endsection
