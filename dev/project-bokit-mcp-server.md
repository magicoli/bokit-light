# Bokit's own MCP server

Goal: bokit-light gets its own MCP server exposing booking tools, built on `assistant-mcp-engine`
(step 3-5 of `dev/project-channel-manager-strategy.md`), usable standalone (a bare MCP client
pointed at bokit) or alongside `personal-assistant-mcp` (an assistant configured to also reach
bokit's tools). Booking tools read bokit's own already-synced local records via the existing
`SourceConnector`/`SyncEngine` — no direct channel-manager API calls from the tool layer, that's
what the sync engine is already for (see `bookings-lessons-learned.md` §1, §5).

**Bokit is multi-tenant by design** — one owner account (a business/property-management
operator) has several properties; a future public site is scoped per tenant the same way. This
is the whole reason the app depends on `assistant-mcp-engine` rather than plain `laravel/mcp`:
the engine's `Assistant`/tenancy apparatus is exactly the thing this app reuses instead of
building its own from scratch. (An earlier pass through this plan proposed skipping tenancy
entirely — wrong, corrected below; kept in git history as a record of the reasoning that led
here, not repeated in this file.)

## What `assistant-mcp-engine` actually provides

Read directly from the package source (`/Users/magic/Projects/assistant-mcp-engine`), not assumed:

- `Laravel\Mcp\Server`/`Tool` (Laravel's own official MCP package, a transitive dependency) — the
  actual protocol plumbing.
- `Models\Assistant` — the tenant unit: `name`, `slug`, `owner_id` (a `User`), `options`. Bokit
  maps this directly onto its own model: `Property belongsTo Assistant` (one owner account, many
  properties), `property_user` pivot unchanged for per-property staff roles *within* a tenant.
- `Contracts\AssistantUser` — what a consumer's own `User` implements so engine code never needs
  a concrete `User` class: `option()`, `isAdmin()`, `canAccessTenant(Model $tenant)`,
  `mailAccounts(): HasMany`. Implemented on `App\Models\User`.
- `Mcp\ToolRegistry` — a fluent builder resolving a **hardcoded, package-internal** list of PAM's
  own tools (mail, skills, memory, server/connection info). **Not used here** — that list is
  PAM's own tools, not bokit's; `BookingServer` builds its own tool list directly instead,
  mirroring `PersonalAssistantServer`'s *shape* (resolve `{assistant}`, authorize, inject into
  tool instances) without going through the registry meant for a different set of tools.
- Passport/Reverb/mail/skill/memory/OAuth-dynamic-client-registration — PAM's own features, none
  of which bokit uses. `dont-discover`'d (`composer.json`) so none of it loads; see below for why
  that was actually necessary (a real migration collision), not just tidiness.

## Adopted: Assistant as bokit's tenant

`Property belongsTo Assistant` (nullable `assistant_id` FK). `User::canAccessTenant()`: **site-wide
`is_admin` bypasses every tenant on purpose** — it's this bokit *install's* own platform-operator
flag (Oli's role, managing the whole install), not a per-tenant role; a tenant's own "propriétaire"
is scoped by `owner_id` match or `property_user` attachment under that tenant, unrelated to
`isAdmin()`. `routes/mcp.php` is `/mcp/{assistant}/bookings`; `BookingServer::boot()` resolves the
slug, authorizes, and both tools take `(?Assistant $assistant, ?User $user)` — a tool with no
resolved assistant refuses to run at all rather than querying unscoped.

**Found live, worth knowing about**:
- `AssistantMcpEngineServiceProvider` bundles 27 migrations including `create_users_table` and
  `create_personal_access_tokens_table` under different filenames than bokit's own — Laravel's
  migrator wouldn't recognize them as already-run and would fatal trying to recreate them.
  `spatie/laravel-package-tools` has no per-consumer exclusion, so the whole provider is
  `dont-discover`'d; the `assistants` table is a bokit-local migration matching `Assistant`'s
  schema by hand instead.
- `Assistant` declares its fillable fields via a `#[Fillable(...)]` PHP attribute that this app's
  installed Laravel version doesn't read at all (the attribute class isn't even shipped in
  `vendor/laravel/framework` here) — `Assistant::create([...])` always throws
  `MassAssignmentException`. Worked around with `forceCreate()`/`forceFill()` in app code, raw
  `DB::table()` in the migration (good practice there regardless of this bug).
- `Property`'s own `$fillable` was missing `assistant_id` entirely after adding the column —
  `Property::create(['assistant_id' => ...])` silently dropped it. Not caught by the migration
  (which uses `DB::table()`, unaffected); caught by the MCP tool tests once they actually queried
  tenant-scoped data and got nothing back.
- `routes/web.php`'s catch-all `POST '/{property:slug}/{unit:slug}'` matched `/mcp/{assistant}/
  bookings` (two path segments) before the real MCP route did, since `routes/mcp.php` loaded
  after `web.php` and Laravel resolves ambiguous routes in registration order — same class of bug
  the `admin.php`-before-`web.php` comment already there was guarding against, just not extended
  to the new route file. Fixed by loading `mcp.php` before `web.php`.
  `tests/Feature/BookingMcpServerTest.php` exercises the real HTTP route specifically because of
  this — a test against `BookingServer` in isolation would never have caught it.
- **Not yet reconciled**: `routes/web.php`'s own catch-alls (property/unit public pages) still
  aren't tenant-scoped — the public site staying single-namespace for now is deliberate (that's
  explicitly future work per the brief, not silently expanded into here), but it means bokit
  currently has two different URL shapes for "which tenant": `/mcp/{assistant}/...` for the API,
  nothing yet for the public site. Revisit together when the public site actually goes
  multi-tenant, rather than guessing at the shape now.

## Status

Steps 1-6 done, tenancy corrected in place (see git log — the tenant-scoping commits land after
the two tool commits, since the correction arrived mid-stream rather than being planned from the
start). Token issuance moved to the UI (`FilamentEditProfilePlugin::shouldShowSanctumTokens()`,
already shipped by an already-installed package) — the artisan command is a debug fallback only,
nothing depends on it.

**Stopped here, deliberately** — the two read tools (`list_bookings`, `get_booking`) are done,
tenant-scoped, tested, verified live end to end (before the tenancy correction; re-verify live
after it). `create_booking`/`update_booking` are confirmed in scope for v1 — draft-then-execute
per §6, real work still ahead, not built yet.

## Sequence (one commit per completed step)

1. ~~This plan.~~
2. ~~`magicoli/assistant-mcp-engine` as a path dependency, `dont-discover`'d (migration collision
   — see above).~~
3. ~~Sanctum wired up: `HasApiTokens` on `User`, `api:` routing, token issuance (now UI-first, see
   Status).~~
4. ~~`App\Mcp\Servers\BookingServer` + `routes/mcp.php`, verified live end-to-end.~~
5. ~~`list_bookings`.~~
6. ~~`get_booking`, sharing `Booking::toDetailPayload()` with the calendar's own modal.~~
7. ~~Tenant-scope everything (Assistant model, `{assistant}` route, `canAccessTenant()`, both
   tools) — the correction this file now reflects throughout.~~
8. `create_booking`/`update_booking` — real scope: draft-then-execute (mirrors PAM's
   `create_booking`/`send_mail_message` pattern: always creates locally, only pushes upstream
   when an explicit connector is given and actually supports writes), which connectors accept a
   push at all today (check the existing `PushableConnector` contract — Beds24 push already
   exists per this project's own memory), what "confirmed" requires.
9. Revisit sync-layer optimizations (step 5 of the channel-manager strategy doc) once real tool
   usage surfaces what's actually missing, rather than speculatively now.

## Open questions for review

- `create_booking`/`update_booking`'s exact shape (which fields, which connectors, confirmation
  flow) — real product scope, not something to finalize alone.
- Whether `routes/web.php`'s public-facing catch-alls should gain tenant scoping now or wait for
  the public-site work proper (leaning wait, per the brief's own phrasing — flagging, not
  deciding).
