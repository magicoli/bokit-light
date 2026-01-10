@extends('layouts.app')

@section('title', __('app.edit_unit_title') . ' - ' . $unit->name)

@push('styles')
@vite('resources/css/form.css')
@vite('resources/css/properties.css')
@vite('resources/css/units.css')
@endpush

@push('scripts')
@vite('resources/js/units-edit.js')
@endpush

@section('content')
    <form id="unit-edit-form" method="POST" action="{{ route('units.update', [$unit->property, $unit]) }}" class=""
          data-sources="{{ $unit->icalSources->toJson() }}">
        @csrf
    <div class="card">
        <h2 class="title">{{ __('app.basic_information') }}</h2>
        <!-- Basic Information -->
        <div class="section">

            <div class="fields-row">
                <fieldset class="flex-grow">
                    <label class="label">
                        {{ __('app.unit_name') }} <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $unit->name) }}"
                        class="w-full"
                        required
                        @input="updateSlug"
                        x-ref="nameInput"
                    >
                    @error('name')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </fieldset>

                <fieldset class="flex-1">
                    <label class="label">
                        {{ __('app.slug') }} <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="slug"
                        value="{{ old('slug', $unit->slug) }}"
                        class="w-full"
                        required
                        x-ref="slugInput"
                        @input="manuallyEdited = true"
                    >
                    @error('slug')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </fieldset>
            </div>
        </div>

            <h2 class="title">{{ __('app.calendar_sources_title') }}</h2>
        <!-- Calendar Sources -->
        <div class="section p-0 m-0">

            <!-- Table -->
            <table class="sources-table m-0">
                <thead>
                    <tr>
                        <th class="w-auto">{{ __('app.type') }}</th>
                        <th class="w-full">{{ __('app.url') }}</th>
                        <th class="w-auto"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(source, index) in sources" :key="index">
                        <tr>
                            <td>
                                <input type="hidden" :name="`sources[${index}][id]`" x-model="source.id">
                                <select
                                    :name="`sources[${index}][type]`"
                                    x-model="source.type"
                                    class="input w-full"
                                >
                                    <option value="ical">{{ __('app.ical') }}</option>
                                    <option value="beds24" disabled>{{ __('app.beds24_coming_soon') }}</option>
                                </select>
                            </td>
                            <td>
                                <div class="flex">
                                <input
                                    type="url"
                                    :name="`sources[${index}][url]`"
                                    x-model="source.url"
                                    class="input flex-grow"
                                    placeholder="https://calendar.example.com/my-unit.ics"
                                    required
                                >
                                </div>
                            </td>
                            <td>
                                <button
                                    type="button icon-button"
                                    @click="removeSource(index)"
                                    class="text-secondary hover:text-red-600 transition-colors border-0 border-transparent"
                                    x-show="sources.length > 1"
                                    title="{{ __('app.delete') }}"
                                >
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr x-show="source.last_sync_at" class="bg-gray-50">
                            <td colspan="3" class="px-4 py-2 text-xs text-secondary">
                                {{ __('app.last_synced') }}: <span x-text="source.last_sync_at"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
                    <tr>
                        <td colspan="3 text-right">
                            <div class="flex justify-space-between">
                            <button type="button" @click="addSource" class="add-button ms-auto">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('app.add_source') }}
                            </button>
                            </div>
                        </td>
                    </tr>
            </table>

            <!-- Empty state -->
            <div x-show="sources.length === 0" class="sources-empty">
                {{ __('app.no_sources_configured') }}
            </div>
        </div>

        <template x-if="source.last_sync_at">
            <div class="last-sync">
                {{ __('app.last_synced') }}: <span x-text="source.last_sync_at"></span>
            </div>
        </template>

        <div x-show="sources.length === 0" class="sources-empty">
            {{ __('app.no_sources_configured') }}
        </div>

        <!-- Actions -->
        <div class="card-footer form-actions">
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
    </div>
    </form>
@endsection
