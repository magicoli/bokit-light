# Timezone precision, dark mode, and settings pages

**Status: done**, except the two items under "Explicitly deferred" below. Every piece landed as
its own commit on `dev`: timezone cascade + Calendar displayTimezone, dark mode CSS, the
GeneralSettings page (+ the Options testing-isolation bugs it surfaced), EditTenantProfile +
PropertyForm fields + tenant logo, the user profile timezone field (+ the Livewire component
registration it needed).

Corrections from Oli, folded in before any code was written (not guessed at):

- Legacy code already distinguished **app-wide timezone vs property timezone** — never per-unit,
  never per-booking. Units/bookings always use their property's timezone, "par la force des
  choses" (a unit belongs to one property; a booking to one unit). No per-unit/per-booking
  override ever existed or should exist.
- `PropertyResource`'s `EditProperty` page already carries sync-critical fields (Beds24 invite
  code, injected via `PropertyForm::extend()`). `EditTenantProfile` must not duplicate or lose
  those — it consolidates **all** tenant preferences, reusing the *same* `PropertyForm` schema,
  not a parallel one.
- `options` (Property/Unit/User) stays a JSON array column for miscellaneous, module-injected
  settings — not columns for everything.
- Timezone and logo are the exception: they're first-class fields "at the same level as name or
  slug" — dedicated columns, not nested in `options`. This also matches what `TimezoneTrait`
  already expects (`$this->attributes['timezone']`, a real column check).

## What's actually in the database today (verified, not assumed)

`properties`, `units`, `bookings` have **no `timezone` column at all**. `users` has `locale` but
no `timezone` either. So `TimezoneTrait::timezone()` — which checks
`$this->attributes['timezone']` then falls back to `defaultTimezone()` (the site-wide
`Options::get('timezone', ...)`) — **always** resolves to the site default today, for every model.
The precise cascade the trait was written for was never actually wired up at the property level.
This means: adding `properties.timezone` doesn't risk regressing a working override — there isn't
one yet. What must be preserved is the *call-site logic* (bookings/units read through to their
property; other timestamps read the viewing user's timezone), not any currently-functioning
per-model value.

`Booking::toDetailPayload()`/the calendar already delegate check-in/out formatting to
`$this->unit->shiftAndFormat(...)` (`app/Models/Booking.php:161,173`) — so fixing `Unit::timezone()`
alone (delegate to `$this->property`) is enough to make booking dates start reflecting the
property's timezone, no change needed in `Booking.php` itself for that path.

`resources/views/filament/pages/calendar.blade.php` already has dormant UI for this: it compares
`$unit->timezone() !== $property->timezone()` and `$property->timezone() !== $displayTimezone` to
show a timezone badge. Today those are always equal (everything is site default), so the badges
never fire. Once `Unit::timezone()` delegates to `Property`, the unit/property comparison becomes
structurally always-equal (correct — no per-unit override exists), and the badge is harmless dead
code, left alone. The property/displayTimezone comparison becomes *meaningful* once
`Calendar::mount()`'s `$this->displayTimezone` is changed from the blanket site default to
`Filament::getTenant()->timezone()` — exactly what makes those pre-built badges start doing their
job (flagging a property whose own timezone differs from the tenant currently being viewed).

## Storage decisions

- `properties.timezone` (nullable string), `properties.logo` (nullable string, path) — new
  columns, same tier as `name`/`slug`.
- `users.timezone` (nullable string) — new column, same tier as `locale`.
- App-wide default timezone: **left on the existing `App\Support\Options` JSON-file cascade**
  (`Options::get('timezone', config('app.timezone'))`), unchanged. Oli flagged the option of
  rewriting `options()`/`Options` onto native Eloquent storage as something we *could* do
  ("on peut"), not a requirement for this pass — touching it now would also touch
  `install.complete`'s current file-based persistence (memory: delicate in the test env). Deferred,
  documented, not done here.

## Timezone resolution (final shape)

- `Property::timezone()` — trait default, now functional once the column exists: own column →
  site default.
- `Unit::timezone()` — **always** `$this->property->timezone()`, no attribute check of its own.
- `Booking::timezone()` — **always** `$this->property->timezone()` (direct, not via `unit`, since
  `Booking` already has its own `property()` relationship).
- `User::timezone()` — trait default, now functional: own column → site default.
- Booking-related dates (check_in/check_out, and anything about the stay itself) → property
  timezone, always, per the above.
- Everything else time-stamped (created_at, updated_at, "last modified") → **the viewing user's**
  timezone, explicit at each call site — never the record's own `timezone()`.

## Calendar dark mode

`resources/css/calendar.css` has zero dark-mode handling (confirmed: 0 occurrences of `dark:` or
`.dark`, 13 hardcoded `bg-white`/`bg-light`/`text-dark` utilities) — a real bug now that the panel
follows Filament's dark mode. `resources/css/glass.css` and `legacy.css` already establish the
convention for this codebase: plain `.dark <selector> { }` override blocks (Filament toggles a
literal `.dark` class; Tailwind v4's own `dark:` utility variant defaults to
`prefers-color-scheme` unless a `@custom-variant` is declared, which this project never declares
for its own build — so hand-written `.dark` ancestor rules, not `dark:` utilities, is the only
approach that actually tracks Filament's manual light/dark/system toggle here). Followed the same
pattern for calendar.css.

## Settings pages

- **App-wide** (`/app/{tenant}/settings` or similar, admin-only, not tenant-scoped): a small
  Filament page, starts with just the display timezone field (Oli: "d'autres réglages vont
  s'ajouter plus tard" — built to grow, not over-built now). Backed by the existing
  `Options`/`options()` helper, unchanged storage.
- **Per-tenant** (`EditTenantProfile`, Filament's native tenant-profile page): reuses
  `PropertyForm::configure($schema)` directly, so every module-injected field (Beds24, WP
  connector...) is present automatically — no duplication, nothing lost relative to today's
  `EditProperty`. Adds `timezone` and `logo` fields to `PropertyForm` itself (shared by both
  `EditTenantProfile` and the existing `PropertyResource` edit page — one schema, two entry
  points). `canView()` overridden to use the app's own `hasAccessTo()` (matches
  `canAccessTenant()`), not Laravel's generic `authorize()`/Policy system that this app doesn't use
  for `Property` — no `PropertyPolicy` exists, and none is being introduced.
- **Per-user**: `joaopaulolndev/filament-edit-profile` already handles name/email/locale/avatar.
  Its own `customProfileComponents()` plugin hook lets us register an additional Livewire form
  section for `timezone`, following the same pattern as its own `EditProfileForm.php` bundled
  component — not routed through its `custom_fields` config array (that serializes into one JSON
  blob column, the wrong shape for a first-class column like `timezone`).

## Tenant logo

`AppPanelProvider`'s `->brandLogo(...)` currently reads `config('app.logo')` only. Changes to:
current tenant's `logo` column when set, else the app-wide default — same precedence as timezone.

## Placeholders show the resolved default, not a generic prompt

Every "inherit unless overridden" select (property timezone, app-wide timezone) gets a
placeholder that names the value actually used when left empty — `"Default (America/Guadeloupe)"`,
never `"Select a timezone"`.

## Explicitly deferred, not done in this pass

- Rewriting `App\Support\Options`/the global `options()` helper onto native Eloquent storage —
  optional per Oli ("on peut"), touches `install.complete`'s current persistence, needs its own
  pass.
- Full app-wide audit of every raw (non-`translatedFormat`) date `->format()` call site outside
  what this pass touches (`DataList.php`'s generic list rendering already goes through
  `TimezoneTrait::formatDate()`, so it's fine as long as each model's own `timezone()` is correct
  — not separately audited here).
- `Unit::get(string $key, ...)` (`app/Models/Unit.php`) calls `$this->property->options($key,
  $default)` — `Property` has no such method (confirmed by reading the model), so this call would
  throw if it ever ran. No caller found anywhere in the app — looks like dead/pre-Filament-era
  code. Left alone, flagged here rather than fixed blind.
