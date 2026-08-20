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

## Open design question, not decided here

`App\Filament\Pages\Calendar` currently shows **every property the user has access to**, in one
combined view (`Property::where(...)->forUser()->get()`, several properties grouped). Strict
per-property tenancy means the calendar's natural home becomes "the current tenant's own units,"
one property at a time via the tenant switcher — a real feature change (loses the combined
multi-property overview an admin has today), not just plumbing. Flagging before touching the
Calendar page, not deciding unilaterally: does the combined view still matter for `is_admin`
(a cross-tenant "all properties" mode), or is losing it acceptable now that each property is its
own workspace?

## Sequence

1. This plan.
2. Schema correction: `assistants.property_id` (replaces `properties.assistant_id`) —
   `Assistant belongsTo Property`, not the other way around.
3. Wire `Panel::tenant()` on `AppPanelProvider`, `User implements HasTenants`.
4. Migrate `BookingResource`/`UnitResource` off manual `forUser()` scoping onto Filament's own
   tenant scope; audit `getUrl()` call sites for the new `tenant:` requirement.
5. `PropertyResource`/`UserResource`/`RateResource` — each has its own shape question above,
   resolved on its own rather than forced into the same pattern as Booking/Unit.
6. Re-point the MCP server (`routes/mcp.php`, `BookingServer`, both tools) at `Property` instead
   of `Assistant` — simpler than the current build, since a tenant now *is* one property (no more
   "sibling property within the same tenant" scoping layer needed).
7. Calendar page — resolved once the open question above has an answer.
