# App panel → real Filament tenancy

Corrects `dev/project-bokit-mcp-server.md`'s tenancy section, which got the tenant model wrong
twice before landing here — kept as project history in git, not repeated in that file.

**The tenant is `Property`, not `Assistant`.** "Chaque propriétaire ne peut voir que ses propres
propriétés" — a property/its owner is the tenant boundary; the future public site is scoped the
same way (`bokit.click/mosaiques`, or a subdomain/custom domain later). `Assistant`
(assistant-mcp-engine's AI-chat/MCP-connection feature) is just **one optional feature a property
can have**, not the tenant itself — `Assistant belongsTo Property`, the reverse of what
`8de43062`/`b416b6ec` built. `Property` already has a `slug` column — Filament tenancy's default
URL scheme uses exactly that, no new column needed.

Scope, confirmed directly: **the whole App panel** goes under real Filament tenancy
(`Panel::tenant()`, `User implements HasTenants`), not just the MCP server in isolation — the
MCP server ends up a *consumer* of the same tenant model, not a separate parallel one.

## Filament v5 tenancy API (read from `vendor/filament/filament/src/Panel/Concerns/HasTenancy.php`
and `Models/Contracts/HasTenants.php`, not assumed)

- `Panel::tenant(Property::class, slugAttribute: 'slug', ownershipRelationship: 'properties')` —
  `ownershipRelationship` names the relationship **on `User`** used to list a user's tenants
  (`getTenants()` still has to return them explicitly, this is a hint for other Filament
  internals). `slugAttribute: 'slug'` is why nothing new is needed on `Property`.
- `User implements HasTenants`: `getTenants(Panel $panel): array|Collection` (this install's
  `is_admin` → every `Property`; otherwise `$this->properties`, already the exact relationship
  that exists) and `canAccessTenant(Model $tenant): bool` (already have the right shape via
  `hasAccessTo(Property $property)` — reuse it, don't reimplement).
- Per-resource auto-scoping expects the resource's own **model** to have a relationship named
  `property()` to the tenant (Filament infers this from the tenant model's class basename,
  camelCased — `property`, singular — unless a resource overrides
  `getTenantOwnershipRelationshipName()`). Confirmed already present, no changes needed:
  `Booking::property()`, `Unit::property()`, `Rate::property()` (all `belongsTo(Property::class)`).

## Per-resource plan (`app/Filament/Resources/*`)

- **`BookingResource`**, **`UnitResource`** (relation: Units)((confirm)), **`RateResource`** (not
  yet built per this project's own roadmap notes — build tenant-scoped from the start): drop the
  manual `getEloquentQuery()->forUser()` override, let Filament's own tenant scoping take over.
  Re-verify `forUser()`'s finer-grained behavior (staff attached to only *some* properties) isn't
  lost — under tenancy that finer scoping is now "does this user's tenant list include the
  current tenant," already exactly what `getTenants()`/`canAccessTenant()` decide at the
  panel-switch level, so the resource-level scope collapses to "belongs to the current tenant,"
  full stop.
- **`PropertyResource`**: `Property` *is* the tenant, so it can't be tenant-scoped the normal way.
  Likely becomes an `is_admin`-only, non-tenant-scoped resource for managing every property /
  creating new tenants — or that job moves to Filament's own `tenantRegistration`/`tenantProfile`
  pages (self-service: a user without a property yet can create one; the current property's own
  settings are edited from "within" it). Decide which before touching this resource — genuine UX
  shape question, not pure plumbing.
- **`UserResource`**: already admin-only (`canAccess()`), not per-property scoped today and
  probably shouldn't become tenant-scoped the same way `Booking`/`Unit` do — a user can belong to
  *several* properties (the pivot this whole tenant model reads from), so "the users of the
  current tenant" is a different, narrower query (`whereHas('properties', fn ($q) =>
  $q->where('properties.id', $tenant->id))`), not a straight `belongsTo`.

## Every `::getUrl()` call site (breaking change: URLs gain a `{tenant}` segment)

- `Booking::toDetailPayload()` (`BookingResource::getUrl('view'/'edit', ..., panel: 'app')`) —
  needs an explicit `tenant:` parameter once outside a real tenant-resolved request (the MCP
  server, CalendarController's JSON endpoints) — Filament can't infer "current tenant" from a
  context that was never routed through the panel's own tenant resolution.
- Anywhere else `route('filament.app...')` or `*Resource::getUrl()` is called outside a live
  Filament-panel HTTP request needs the same explicit treatment. Audit before considering this
  done, not assumed complete after the obvious ones.

## Open design question — resolved minimally, real answer still pending

`App\Filament\Pages\Calendar` shows **every property the user has access to**, in one combined
view (`Property::where(...)->forUser()->get()`). Left unchanged on purpose: the page now lives at
`/app/{tenant}/calendar` (Filament requires the segment, since it's a page in a tenant-scoped
panel), but its own query still ignores which tenant is selected and keeps showing everything
`forUser()` resolves — same behaviour as before tenancy, only the URL grew a prefix. The real
question (should `is_admin` keep a cross-tenant "all properties" mode, or does each property become
its own calendar workspace via the tenant switcher) is **still open**, not decided here — this was
the smallest change that kept the existing feature and its tests intact.

## Sequence

1. ~~This plan.~~ Done.
2. ~~Schema correction: `assistants.property_id` (replaces `properties.assistant_id`) —
   `Assistant belongsTo Property`, not the other way around.~~ Done
   (`2026_08_20_235649_reverse_assistant_property_relationship.php`).
3. ~~Wire `Panel::tenant()` on `AppPanelProvider`, `User implements HasTenants`.~~ Done — no
   explicit `ownershipRelationship` in the end (see below, its default already matched).
4. ~~Migrate `BookingResource`/`UnitResource`/`RateResource` off manual `forUser()` scoping onto
   Filament's own tenant scope; audit `getUrl()` call sites for the new `tenant:` requirement.~~
   Done.
5. ~~`PropertyResource`/`UserResource` — each has its own shape question above, resolved on its
   own.~~ Done: `PropertyResource` stays `$isScopedToTenant = false` (Property can't scope to
   itself) but **keeps its pre-tenancy `forUser()` override** — owners still manage their own
   property/ies from here, not admin-only as first tried (that broke the pre-existing
   single-property-owner self-service flow, caught by `SingleRecordRedirectTest`).
   `UserResource` stays `$isScopedToTenant = false`, already admin-only.
6. ~~Re-point the MCP server (`routes/mcp.php`, `BookingServer`, both tools) at `Property` instead
   of `Assistant`.~~ Done.
7. Calendar page — minimally patched (see above), real redesign still open.

### Corrections made along the way, not in the original plan

- **No explicit `ownershipRelationship` on `Panel::tenant()`.** The constructor argument names the
  relationship every tenant-scoped *resource's own model* must have back to the tenant (confirmed
  live, contradicts the docblock's first reading) — its default (camelCase of the tenant model's
  basename, `property`) already matched `Booking::property()`/`Unit::property()`/`Rate::property()`
  exactly. Passing `ownershipRelationship: 'properties'` broke every tenant-scoped resource
  ("model does not have a relationship named [properties]").
- **`magicoli/two-way-ticket` needed a real package fix**, not a workaround here: `TicketResource`
  had no `$isScopedToTenant = false`, so attaching `TicketsPlugin` to the now tenant-scoped App
  panel broke ("model [Ticket] does not have a relationship named [property]") — Ticket is a
  genuinely global model (bug tracking, not property data) and should never be tenant-scoped on
  any panel. Fixed in the package itself (now a `@dev` path repo, like `assistant-mcp-engine`),
  not by dropping the plugin from the App panel.
- **Legacy catch-all route collision**: `routes/web.php`'s deprecated `/{property:slug}/{unit:slug}`
  route (already excluding `livewire-` as a property slug) also needed to exclude `app` — Filament's
  bare tenant dashboard route (`app/{tenant}`) is a two-segment path structurally identical to the
  legacy route's shape, and the legacy one was winning the match, 404ing on `Property::where(slug:
  'app')`.
- **Cross-panel nav shortcuts** (`MainPanelProvider`'s Calendar/Dashboard links, shown outside the
  App panel) needed an explicit `tenant:` param — added a `defaultTenant()` helper resolving the
  signed-in user's first accessible property, with the items hidden entirely when there is none.
- **`User::getTenants()`** filters to `is_active` properties (for both `is_admin` and regular
  users) — an inactive property was otherwise still selectable from the tenant switcher, the one
  place in the panel that wasn't already respecting the flag.
