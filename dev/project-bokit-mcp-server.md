# Bokit's own MCP server

Goal: bokit-light gets its own MCP server exposing booking tools, built on `assistant-mcp-engine`
(step 3-5 of `dev/project-channel-manager-strategy.md`), usable standalone (a bare MCP client
pointed at bokit) or alongside `personal-assistant-mcp` (an assistant configured to also reach
bokit's tools). Booking tools read bokit's own already-synced local records via the existing
`SourceConnector`/`SyncEngine` — no direct channel-manager API calls from the tool layer, that's
what the sync engine is already for (see `bookings-lessons-learned.md` §1, §5).

## What `assistant-mcp-engine` actually provides

Read directly from the package source (`/Users/magic/Projects/assistant-mcp-engine`), not assumed:

- `Laravel\Mcp\Server`/`Tool` (Laravel's own official MCP package, a transitive dependency) — the
  actual protocol plumbing.
- `Concerns\HasDependency` — generic `depends()`/`isMet()`/`isActive()`/`isEnabled()` gating,
  domain-agnostic.
- `Mcp\Concerns\HasToolAvailability` — the one MCP-specific layer on top: `shouldRegister()` (what
  Laravel MCP's `Primitive::eligibleForRegistration()` looks for) + `group(): ToolGroup`.
- `Mcp\ToolRegistry` — a fluent builder (`tools()->user($u)->assistant($a)->server($s)->get()`)
  that resolves a **hardcoded, package-internal** list of PAM's own tools (mail, skills, memory,
  server/connection info) by reflecting each tool's constructor and injecting whichever of
  `AssistantUser`/`Assistant`/`Server` it asks for. **Not an extension point** — there's no hook
  for a consumer to append tool classes to that list from outside the package.
- `Contracts\AssistantUser` — what a consumer's own `User` model implements so engine code never
  needs a concrete `User` class: `option()`, `isAdmin()`, `canAccessTenant(Model $tenant)`,
  `mailAccounts(): HasMany`.
- `Models\Assistant` — PAM's multi-tenant unit (one AI assistant/bot instance = one tenant). Mail
  accounts, skills, memories, and the whole Passport/OAuth dynamic-client-registration dance
  (`Mcp::oauthRoutes()`, `routes/ai.php`) all exist to let an arbitrary external MCP client
  self-register against one `{assistant}` slug in a multi-tenant SaaS.

## The decision this plan turns on

Bokit is **not** multi-tenant in PAM's sense — one property-management install, several staff
users scoped by `Property`/`Unit` access (`User::hasAccessTo()`, already in place), no concept of
"which AI assistant is this." Two ways to use the engine:

**A. Full PAM shape** — implement `AssistantUser` on `App\Models\User`, adopt `Models\Assistant`
as a real tenant bokit users belong to, go through `ToolRegistry` and inherit PAM's mail/skill/
memory tools and Passport/Reverb/dynamic-OAuth-client apparatus even though bokit doesn't have
mail accounts, skills, or memories of its own (yet).

**B. Minimal shape (recommended)** — depend on the package for its genuinely generic pieces
(`Laravel\Mcp\Server`/`Tool`, `HasDependency`, `HasToolAvailability`, `ToolGroup`-style
convention) and bokit's own `Server` subclass builds its own tool list directly — no
`ToolRegistry::tools()` call (that list is PAM's own tools, not bokit's), no `AssistantUser`
implementation (bokit's tool constructors type-hint `?App\Models\User` directly, exactly the
model bokit already has), no `Assistant`/tenant model, no Passport/Reverb. Auth via bokit's
**existing Sanctum setup** (`config/sanctum.php` and the `personal_access_tokens` migration are
already present — just unused: `User` lacks `HasApiTokens` and `bootstrap/app.php`'s
`withRouting()` has no `api:` entry). A bokit admin issues themselves a personal access token
(same shape as `two-way-ticket`'s bearer-token API), points an MCP client at bokit's endpoint.

**Why B**: pulling in mail/skill/memory/Passport/Reverb/multi-tenancy for an app that has none of
those concepts is exactly the kind of premature abstraction the project's own conventions warn
against, and it would drag Filament resources/migrations/config into bokit that don't apply to
it. "Combination with personal-assistant-mcp" (per the brief) reads as **MCP-protocol
composability** — an assistant in PAM gets bokit's server added as one of its own connected MCP
tools — not shared PHP tenancy between the two apps. If a real future need appears (bokit wants
its own skills/memories), that's an argument for widening the engine's extension points then, not
for adopting apparatus bokit doesn't use today.

**Still needs your sign-off before the tool-building work starts** — this changes the shape of
every tool bokit writes from here on.

## Status

Steps 1-6 done (one commit each, see git log). Proceeded with the Minimal shape (B) — no sign-off
came back before the tool-building work started, and everything through step 6 confirmed the
reasoning held (no engine apparatus needed, no test had to reach for it). Easy to revisit if
wrong: nothing here depends on AssistantUser/Assistant existing.

**Stopped here, deliberately** — the two read tools (`list_bookings`, `get_booking`) are done,
tested, verified live end to end. `create_booking`/`update_booking` are a different kind of
decision: real external-write scope (draft-then-execute per §6, which connectors actually accept
a push, what "confirmed" should require) — not something to guess through alone while you're
away. Everything up to here is safe to review and merge independently of that decision.

**Found and fixed in step 4, worth knowing about**: `routes/web.php`'s catch-all
`POST '/{property:slug}/{unit:slug}'` matched `/mcp/bookings` (property=mcp, unit=bookings)
*before* the real MCP route did, because `routes/mcp.php` loaded after `web.php` in
`bootstrap/app.php` and Laravel resolves ambiguous routes in registration order — same class of
bug the `admin.php`-before-`web.php` comment already there was guarding against, just not
extended to the new route file. Silent failure mode: the catch-all's own 'web' middleware threw a
419 CSRF mismatch, which reads nothing like "wrong route matched." Fixed by loading `mcp.php`
before `web.php`. `tests/Feature/BookingMcpServerTest.php` exercises the real HTTP route
specifically because of this — a test against the `BookingServer` class in isolation would never
have caught it.

## Sequence (one commit per completed step)

1. ~~This plan.~~
2. ~~Add `magicoli/assistant-mcp-engine` as a path-repo dev dependency (`@dev`, `../assistant-mcp-engine`
   — same pattern as `personal-assistant-mcp`'s own `composer.json`).

   **Found while doing this, not assumed**: `AssistantMcpEngineServiceProvider::configurePackage()`
   calls `->runsMigrations()->hasMigrations([...])` with a hardcoded list of 27 migrations,
   including `0001_01_01_000000_create_users_table` and
   `2026_07_31_071816_create_personal_access_tokens_table` — both of which bokit **already has**,
   under different filenames. Laravel's migrator tracks by filename, so it wouldn't recognize
   these as already-run; a plain `composer require` + `php artisan migrate` would try to
   `Schema::create('users', ...)` a second time and fatal, taking the whole migration batch down
   with it. `spatie/laravel-package-tools` (confirmed by reading
   `Concerns/Package/HasMigrations.php`) has no per-consumer exclusion mechanism — the migration
   list is baked into the package's own provider, nothing a consumer's `composer.json` can filter.

   PAM never hit this because PAM's own `users`/`personal_access_tokens` tables were extracted
   *into* the engine in the first place — bokit is the first consumer with pre-existing schema of
   its own, so this is a genuinely new problem, not something already solved upstream.

   **Mitigation, verified safe**: `composer.json`'s existing `extra.laravel.dont-discover` array
   (already present, empty) can name `magicoli/assistant-mcp-engine` to stop Laravel from
   auto-registering `AssistantMcpEngineServiceProvider` at all — no migrations, no config, no
   routes, no Filament resources from the engine load. This matches the Minimal-shape decision
   (B) exactly: bokit only wants specific plain-PHP pieces (`Laravel\Mcp\Server`/`Tool` — already
   reachable directly since `laravel/mcp` is a transitive dependency; `Concerns\HasDependency`;
   optionally `Mcp\Concerns\HasToolAvailability`), and confirmed by reading both — neither has any
   container/config coupling to the engine's own provider, so referencing them by class name
   works with the provider fully disabled.
   Confirm after installing: `php artisan migrate:status` shows nothing new pending from the
   engine before ever running an actual migration.~~
3. ~~Wire up Sanctum properly: `HasApiTokens` on `App\Models\User`, `api:` entry in
   `bootstrap/app.php`'s `withRouting()`, a way for an admin to issue a token
   (`bokit:issue-api-token {email}` — a Filament "API tokens" UI section can come later if
   self-service turns out to matter).~~
4. ~~`App\Mcp\Servers\BookingServer` + `routes/mcp.php`
   (`Mcp::web('/mcp/bookings', ...)->middleware('auth:sanctum')` for HTTP,
   `Mcp::local('bokit-bookings', ...)` for a client-spawned subprocess), empty tool list to start
   — verified live end-to-end (real token, real curl through nginx, correct `initialize`
   response).~~
5. ~~First real tool: `list_bookings` — `Booking::forUser($user)`, property/guest_name kept as
   separate filters, guest_name any-word matching, group reservations collapsed to one entry with
   price summed server-side (§2, §4, §6). Tested through the real MCP HTTP route.~~
6. `get_booking` (single record, same aggregation as `list_bookings` but full detail — paid,
   deposit, balance, source/origin links, matching `CalendarController::bookingPayload()`'s
   shape), then evaluate what's actually useful next (create/update — draft-then-execute per
   §6 — is real scope, not a given for a first pass).
7. Revisit sync-layer optimizations (step 5 of the channel-manager strategy doc) once real tool
   usage surfaces what's actually missing, rather than speculatively now.

## Open questions for review

- Confirm the Minimal-shape decision (A vs. B above) — proceeded with B since nothing forced a
  choice before it was needed; still cheap to unwind if wrong (see Status above).
- Token issuance UX: artisan command good enough for now, or worth a Filament UI section?
- Which tools beyond list/get are actually wanted for v1 — the brief says "its own MCP server for
  booking functions," not a specific tool list. `create_booking`/`update_booking` are real scope
  decisions (draft-then-execute, which connectors actually support writes) worth your input
  before they're built, not after.
