{{--
    Inline filter selects — rendered in the table toolbar via TOOLBAR_START hook.
    Injected as a direct child of fi-ta-header-toolbar (flex justify-between).
    Filters sit on the left; search + column toggle stay on the right.
    Uses Filament's own input components so styles match the panel CSS.
--}}
@php
    $f = $__livewire->tableFilters ?? [];

    $statusValue = $f['status']['value'] ?? '';
    $periodValue = $f['period']['value'] ?? '';
    $unitValue   = $f['unit']['value'] ?? '';
    $sourceValue = $f['source_name']['value'] ?? '';
    $effectiveOn = (bool) ($f['effective']['isActive'] ?? true);

    $statusOptions = \App\Filament\Resources\Bookings\Tables\BookingsTable::statusOptions();
    $periodOptions = \App\Filament\Resources\Bookings\Tables\BookingsTable::periodOptions();

    $unitOptions = \App\Models\Unit::forUser()
        ->orderBy('name')
        ->pluck('name', 'id')
        ->all();

    $sourceOptions = \App\Models\Booking::query()
        ->whereNotNull('source_name')
        ->distinct()
        ->pluck('source_name')
        ->mapWithKeys(fn (string $v): array => [$v => \App\Models\Booking::sourceSlug($v)])
        ->sort()
        ->all();
@endphp

<div class="flex items-center gap-x-3">

    {{-- Status --}}
    <x-filament::input.wrapper inline-suffix>
        <x-filament::input.select wire:model.live="tableFilters.status.value" :inline-suffix="$statusValue !== ''" aria-label="{{ __('booking.field.status') }}">
            <option value="">{{ __('booking.field.status') }}</option>
            @foreach($statusOptions as $val => $label)
                <option value="{{ $val }}" @selected($val === $statusValue)>{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
        @if($statusValue !== '')
            <x-slot:suffix>
                <button type="button" wire:click="$set('tableFilters.status.value', '')" aria-label="{{ __('app.clear') }}" style="display:flex;align-items:center;cursor:pointer;color:var(--color-gray-400)">
                    <x-filament::icon icon="heroicon-m-x-mark" style="width:1rem;height:1rem" />
                </button>
            </x-slot:suffix>
        @endif
    </x-filament::input.wrapper>

    {{-- Period --}}
    <x-filament::input.wrapper inline-suffix>
        <x-filament::input.select wire:model.live="tableFilters.period.value" :inline-suffix="$periodValue !== ''" aria-label="{{ __('booking.filter.period') }}">
            <option value="">{{ __('booking.filter.period') }}</option>
            @foreach($periodOptions as $val => $label)
                <option value="{{ $val }}" @selected($val === $periodValue)>{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
        @if($periodValue !== '')
            <x-slot:suffix>
                <button type="button" wire:click="$set('tableFilters.period.value', '')" aria-label="{{ __('app.clear') }}" style="display:flex;align-items:center;cursor:pointer;color:var(--color-gray-400)">
                    <x-filament::icon icon="heroicon-m-x-mark" style="width:1rem;height:1rem" />
                </button>
            </x-slot:suffix>
        @endif
    </x-filament::input.wrapper>

    {{-- Unit --}}
    <x-filament::input.wrapper inline-suffix>
        <x-filament::input.select wire:model.live="tableFilters.unit.value" :inline-suffix="$unitValue !== ''" aria-label="{{ __('booking.field.unit_name') }}">
            <option value="">{{ __('booking.field.unit_name') }}</option>
            @foreach($unitOptions as $id => $name)
                <option value="{{ $id }}" @selected((string) $id === (string) $unitValue)>{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
        @if($unitValue !== '')
            <x-slot:suffix>
                <button type="button" wire:click="$set('tableFilters.unit.value', '')" aria-label="{{ __('app.clear') }}" style="display:flex;align-items:center;cursor:pointer;color:var(--color-gray-400)">
                    <x-filament::icon icon="heroicon-m-x-mark" style="width:1rem;height:1rem" />
                </button>
            </x-slot:suffix>
        @endif
    </x-filament::input.wrapper>

    {{-- Source --}}
    <x-filament::input.wrapper inline-suffix>
        <x-filament::input.select wire:model.live="tableFilters.source_name.value" :inline-suffix="$sourceValue !== ''" aria-label="{{ __('booking.field.source_name') }}">
            <option value="">{{ __('booking.field.source_name') }}</option>
            @foreach($sourceOptions as $val => $label)
                <option value="{{ $val }}" @selected($val === $sourceValue)>{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
        @if($sourceValue !== '')
            <x-slot:suffix>
                <button type="button" wire:click="$set('tableFilters.source_name.value', '')" aria-label="{{ __('app.clear') }}" style="display:flex;align-items:center;cursor:pointer;color:var(--color-gray-400)">
                    <x-filament::icon icon="heroicon-m-x-mark" style="width:1rem;height:1rem" />
                </button>
            </x-slot:suffix>
        @endif
    </x-filament::input.wrapper>

    {{-- Effective bookings only (hides cancelled/deleted/vanished) --}}
    <label class="flex items-center gap-x-1.5 text-sm cursor-pointer select-none" style="white-space:nowrap">
        <x-filament::input.checkbox wire:model.live="tableFilters.effective.isActive" :checked="$effectiveOn" />
        {{ __('booking.filter.effective_only') }}
    </label>

</div>
