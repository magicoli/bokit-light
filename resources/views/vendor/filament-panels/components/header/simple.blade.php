{{--
    Override of filament/filament resources/views/components/header/simple.blade.php.
    Shared by Login, Register and RequestPasswordReset (all extend SimplePage), so both
    changes below apply to all three at once — including register/forgot-password before
    they're even enabled, so this doesn't need revisiting later.

    Changes from upstream:
    - The logo links to the panel's home URL, like it already does in the topbar.
      Filament's own component has no such link (see vendor/.../components/logo.blade.php,
      which never wraps its output in an anchor) and there's no render hook scoped to just
      this logo, so an override is the only way in.
    - The language switch is dropped in directly via <x-language-switch::inline />, which
      mounts the Livewire component at this exact spot — no render hook, no placement/CSS
      guesswork (see bezhansalleh/filament-language-switch docs on the inline component).

    Keep this file in sync with upstream — it's small and rarely touched, but check on
    Filament upgrades.
--}}
@props([
    'heading' => null,
    'logo' => true,
    'subheading' => null,
])

<header class="fi-simple-header">
    @if ($logo)
        <a {{ \Filament\Support\generate_href_html(filament()->getHomeUrl()) }}>
            <x-filament-panels::logo />
        </a>
    @endif

    @if (filled($heading))
        <h1 class="fi-simple-header-heading">
            {{ $heading }}
        </h1>
    @endif

    @if (filled($subheading))
        <p class="fi-simple-header-subheading">
            {{ $subheading }}
        </p>
    @endif

    <div class="my-4 p-4">
        <x-language-switch::inline key="switch-header" />
    </div>

</header>
