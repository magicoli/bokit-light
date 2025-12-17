@extends('layouts.app')

@section('title', 'Edit Unit - ' . $unit->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('properties.index') }}" class="text-sm text-blue-600 hover:text-blue-800 mb-2 inline-block">
            ← Back to Properties
        </a>
        <div class="flex items-baseline gap-3">
            <h1 class="text-3xl font-bold text-gray-900">{{ $unit->property->name }} / {{ $unit->name }}</h1>
            <span class="text-sm text-gray-500">Edit Unit</span>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 flex items-start">
            <svg class="w-5 h-5 text-green-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('units.update', [$unit->property, $unit]) }}" class="bg-white rounded-lg shadow-sm p-6 space-y-6" x-data="unitForm()">
        @csrf
        @method('PUT')

        <!-- Basic Information -->
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Unit Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $unit->name) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                        @input="updateSlug"
                        x-ref="nameInput"
                    >
                    @error('name')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="slug"
                        value="{{ old('slug', $unit->slug) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                        x-ref="slugInput"
                        @input="manuallyEdited = true"
                    >
                    @error('slug')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Calendar Sources -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Calendar Sources</h2>
                <button 
                    type="button" 
                    @click="addSource"
                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Source
                </button>
            </div>
            
            <div class="space-y-3">
                <template x-for="(source, index) in sources" :key="index">
                    <div class="border border-gray-200 rounded-lg p-4 relative">
                        <button 
                            type="button" 
                            @click="removeSource(index)"
                            class="absolute top-3 right-3 text-gray-400 hover:text-red-600 transition-colors"
                            x-show="sources.length > 1"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        
                        <input type="hidden" :name="`sources[${index}][id]`" x-model="source.id">
                        
                        <div class="grid grid-cols-1 md:grid-cols-[150px_1fr] gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select
                                    :name="`sources[${index}][type]`"
                                    x-model="source.type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="ical">iCal</option>
                                    <option value="beds24" disabled>Beds24 (coming soon)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">URL <span class="text-red-500">*</span></label>
                                <input
                                    type="url"
                                    :name="`sources[${index}][url]`"
                                    x-model="source.url"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="https://calendar.example.com/my-unit.ics"
                                    required
                                >
                            </div>
                        </div>
                        
                        <template x-if="source.last_sync_at">
                            <div class="mt-2 text-xs text-gray-500">
                                Last synced: <span x-text="source.last_sync_at"></span>
                            </div>
                        </template>
                    </div>
                </template>
                
                <div x-show="sources.length === 0" class="text-center py-8 text-gray-500">
                    No calendar sources configured. Click "Add Source" to get started.
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
            <a href="{{ route('properties.index') }}" class="text-gray-600 hover:text-gray-800">
                Cancel
            </a>
            <button 
                type="submit"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
function unitForm() {
    return {
        manuallyEdited: false,
        sources: {!! json_encode($unit->icalSources->map(function($source) {
            return [
                'id' => $source->id,
                'type' => $source->type,
                'url' => $source->url,
                'last_sync_at' => $source->last_synced_at ? $source->last_synced_at->diffForHumans() : null,
            ];
        })->values()) !!},
        
        init() {
            if (this.sources.length === 0) {
                this.addSource();
            }
        },
        
        addSource() {
            this.sources.push({
                id: null,
                type: 'ical',
                url: '',
                last_sync_at: null
            });
        },
        
        removeSource(index) {
            if (this.sources.length > 1) {
                this.sources.splice(index, 1);
            }
        },
        
        updateSlug() {
            if (!this.manuallyEdited) {
                const name = this.$refs.nameInput.value;
                const slug = name
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                
                this.$refs.slugInput.value = slug;
            }
        }
    }
}
</script>
@endsection
