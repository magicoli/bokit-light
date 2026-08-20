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

## Sequence (one commit per completed step)

1. ~~This plan.~~
2. Add `magicoli/assistant-mcp-engine` as a path-repo dev dependency (`@dev`, `../assistant-mcp-engine`
   — same pattern as `personal-assistant-mcp`'s own `composer.json`), confirm it installs clean
   alongside bokit's existing deps (Filament v5, Sanctum, no Passport conflict expected since
   Passport is additive). Mechanical, low-risk, reversible — proceeding without waiting on the
   shape decision above.
3. Wire up Sanctum properly: `HasApiTokens` on `App\Models\User`, `api:` entry in
   `bootstrap/app.php`'s `withRouting()`, a way for an admin to issue a token (start minimal —
   `php artisan` command or tinker; a Filament "API tokens" UI section can come later, PAM's Edit
   Profile page is the reference if wanted).
4. `App\Mcp\Servers\BookingServer` (bokit's own `Server` subclass) + route registration
   (`Mcp::web('/mcp/bookings', BookingServer::class)->middleware('auth:sanctum')` in a new
   `routes/mcp.php`, or folded into an existing route file — decide when wiring it), empty tool
   list to start, so the endpoint itself is verifiable (server info / connection) before any
   booking logic exists.
5. First real tool: `list_bookings` — reads `Booking::forUser($user)` (existing scope) with the
   same filter separation lesson from PAM (§6: property vs. guest_name kept distinct, any-word
   name matching, empty-result tells the caller to broaden rather than just failing). Group
   aggregation (§2, §4) computed server-side from the start, not left to the model to remember —
   bokit already has this logic in `Booking::groupMembers()`/`CalendarController::bookingPayload()`,
   reuse rather than reimplement.
6. `get_booking` (single record, same aggregation), then evaluate what's actually useful next
   (create/update — draft-then-execute per §6 — is real scope, not a given for a first pass).
7. Revisit sync-layer optimizations (step 5 of the channel-manager strategy doc) once real tool
   usage surfaces what's actually missing, rather than speculatively now.

## Open questions for review

- Confirm the Minimal-shape decision (A vs. B above) before step 5 starts in earnest — steps 2-4
  are safe either way.
- Token issuance UX: artisan command good enough for now, or worth a Filament UI section from the
  start?
- Which tools beyond list/get are actually wanted for v1 — the brief says "its own MCP server for
  booking functions," not a specific tool list.
