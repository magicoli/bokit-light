{{--
    An override of Filament's own filament-panels::components.user-menu, shipped by
    magicoli/extra-navigation-items and prepended to the filament-panels view namespace by the
    service provider (a host app's own resources/views/vendor/filament-panels copy still wins).

    Why the package needs it: the USER_MENU_BEFORE / USER_MENU_AFTER render hooks — where
    NavigationItemsPlugin places its items by default — live INSIDE this component, and Filament's
    stock version renders nothing at all when no user is signed in. The service provider also
    renders this component at the end of the topbar when signed out (Filament's topbar omits it),
    so on a public page reached while logged out the hooks still have somewhere to land.

    While it is here it also:
    - puts the user's first name beside the avatar in the trigger (styling in the package's CSS);
    - turns the profile header into an "Edit profile" link, since the name is already in the
      trigger — the header would only repeat it;
    - when signed out with nothing but a login route, makes the trigger itself go straight to the
      login page; only when registration or password reset is also enabled does it open a menu.

    Everything app-specific is resolved through the filament() manager — getLoginUrl(),
    getProfileUrl(), getRegistrationUrl(), getRequestPasswordResetUrl() and their has* guards — so
    nothing here is tied to a particular application. Kept in step with Filament v5's user-menu.
--}}
@props([
    'position' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Enums\UserMenuPosition;
    use Illuminate\Support\Arr;
    use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;

    $user = filament()->auth()->user();

    if ($user) {
        $userName = filament()->getUserName($user);
        $userFirstName = method_exists($user, 'firstName') ? $user->firstName() : $userName;

        $items = $this->getUserMenuItems();

        $itemsBeforeAndAfterThemeSwitcher = collect($items)
            ->groupBy(fn (Action $item): bool => $item->getSort() < 0, preserveKeys: true)
            ->all();
        $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
        $itemsAfterThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();

        $hasProfileHeader = $itemsBeforeThemeSwitcher->has('profile') &&
            blank(($item = Arr::first($itemsBeforeThemeSwitcher))->getUrl()) &&
            (! $item->hasAction());

        if ($itemsBeforeThemeSwitcher->has('profile')) {
            $itemsBeforeThemeSwitcher = $itemsBeforeThemeSwitcher->prepend($itemsBeforeThemeSwitcher->pull('profile'), 'profile');
        }
    }

    $multiGroupAfterTheme = $this->hasMultipleUserMenuItemGroups();
    $afterThemeItemGroups = $multiGroupAfterTheme ? $this->getUserMenuItemGroupsAfterTheme() : [];

    $position ??= filament()->getUserMenuPosition();
    $dropdownPlacement = $position === UserMenuPosition::Topbar ? 'bottom-end' : 'top-end';
    $dropdownTeleport = $position === UserMenuPosition::Topbar;

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();

    // Signed out, a menu earns its place only when there is more than one destination in it.
    $hasSignedOutMenu = filament()->hasRegistration() || filament()->hasPasswordReset();
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

@if ($user)
    <x-filament::dropdown
        :placement="$dropdownPlacement"
        :teleport="$dropdownTeleport"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($attributes)
                ->class(['fi-user-menu'])
        "
    >
        <x-slot name="trigger">
            @if ($position === UserMenuPosition::Topbar)
                <button
                    aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                    type="button"
                    class="fi-user-menu-trigger"
                >
                    <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                    {{-- Avatar AND first name: an avatar alone says who you are only to whoever
                         already recognises the picture, and an unset one is just a grey circle. --}}
                    @if (filled($userFirstName))
                        <span class="fi-user-menu-trigger-text">{{ $userFirstName }}</span>
                    @endif
                </button>
            @else
                <button
                    aria-label="{{ filled($userName) ? $userName : __('filament-panels::layout.actions.open_user_menu.label') }}"
                    type="button"
                    class="fi-user-menu-trigger"
                >
                    <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                    <span
                        @if ($isSidebarCollapsibleOnDesktop)
                            x-show="$store.sidebar.isOpen"
                        @endif
                        class="fi-user-menu-trigger-text"
                    >
                        {{ $userName }}
                    </span>

                    {{
                        \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronUp, alias: \Filament\View\PanelsIconAlias::USER_MENU_TOGGLE_BUTTON, attributes: new \Filament\Support\View\ComponentAttributeBag([
                            'x-show' => $isSidebarCollapsibleOnDesktop ? '$store.sidebar.isOpen' : null,
                        ]))
                    }}
                </button>
            @endif
        </x-slot>

        @php
            // "Edit profile" replaces the name header — the name is already in the trigger. Drop
            // Filament's own 'profile' item if there is one so the menu does not repeat it, and
            // link to the edit-profile page when it is registered on this panel. The
            // filament-edit-profile plugin registers a normal page, so filament()->hasProfile()
            // never sees it — its own route does.
            unset($itemsBeforeThemeSwitcher['profile']);

            $editProfileUrl = \Illuminate\Support\Facades\Route::has(EditProfilePage::getRouteName())
                ? EditProfilePage::getUrl()
                : null;
        @endphp

        @if (filled($editProfileUrl))
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

            <x-filament::dropdown.header
                :href="$editProfileUrl"
                tag="a"
                class="cursor-pointer"
                icon="heroicon-m-user-circle"
            >
                {{ __('user.menu.edit_profile') }}
            </x-filament::dropdown.header>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
        @endif

        @if ($itemsBeforeThemeSwitcher->isNotEmpty())
            <x-filament::dropdown.list>
                @foreach ($itemsBeforeThemeSwitcher as $item)
                    {{ $item }}
                @endforeach
            </x-filament::dropdown.list>
        @endif

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()) && filament()->hasThemeSwitcher())
            <x-filament::dropdown.list>
                <x-filament-panels::theme-switcher />
            </x-filament::dropdown.list>
        @endif

        @if ($multiGroupAfterTheme && $afterThemeItemGroups !== [])
            @foreach ($afterThemeItemGroups as $afterThemeGroup)
                <x-filament::dropdown.list>
                    @foreach ($afterThemeGroup as $item)
                        {{ $item }}
                    @endforeach
                </x-filament::dropdown.list>
            @endforeach
        @elseif ($itemsAfterThemeSwitcher->isNotEmpty())
            <x-filament::dropdown.list>
                @foreach ($itemsAfterThemeSwitcher as $item)
                    {{ $item }}
                @endforeach
            </x-filament::dropdown.list>
        @endif
    </x-filament::dropdown>
@elseif (filament()->hasLogin() && ! $hasSignedOutMenu)
    {{-- Login is the only destination: the trigger is the login link, no menu to open. --}}
    <a
        href="{{ filament()->getLoginUrl() }}"
        aria-label="{{ __('filament-panels::auth/pages/login.title') }}"
        class="fi-user-menu-trigger"
    >
        <x-filament::icon icon="ri-login-circle-line" class="size-8" />
    </a>
@elseif (filament()->hasLogin())
    <x-filament::dropdown
        :placement="$dropdownPlacement"
        :teleport="$dropdownTeleport"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($attributes)
                ->class(['fi-user-menu'])
        "
    >
        <x-slot name="trigger">
            <button
                type="button"
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                class="fi-user-menu-trigger"
            >
                <x-filament::icon icon="ri-login-circle-line" class="size-8" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                :href="filament()->getLoginUrl()"
                tag="a"
                icon="heroicon-o-arrow-right-end-on-rectangle"
            >
                {{ __('filament-panels::auth/pages/login.title') }}
            </x-filament::dropdown.list.item>

            @if (filament()->hasRegistration())
                <x-filament::dropdown.list.item
                    :href="filament()->getRegistrationUrl()"
                    tag="a"
                    icon="heroicon-o-user-plus"
                >
                    {{ __('filament-panels::auth/pages/register.title') }}
                </x-filament::dropdown.list.item>
            @endif

            @if (filament()->hasPasswordReset())
                <x-filament::dropdown.list.item
                    :href="filament()->getRequestPasswordResetUrl()"
                    tag="a"
                    icon="heroicon-o-key"
                >
                    {{ __('filament-panels::auth/pages/password-reset/request-password-reset.title') }}
                </x-filament::dropdown.list.item>
            @endif
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
