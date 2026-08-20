# Bookings module — lessons learned in personal-assistant-mcp

Extracted from every booking-related commit since `8e1a55f` (the module's first commit) through
the base-agent/migration fixes that followed it, for reference when rebuilding this in
bokit-light — see `project_assistant_mcp_engine_split.md` for the extraction plan this feeds
into. Organized by theme, not chronology; commit hashes given so the actual diff/message is one
`git show` away.

## 1. Property/Unit, not connector-owns-unit — the load-bearing model change

Started simplest: a single `unit` string field directly on `BookingConnector` (`f0087af`). Broke
down for Beds24, where one `refresh_token` covers several physical units — no single value to
put in that field, and every workaround (hiding it, computing `Booking.name` from the booking's
own unit instead) patched around the mismatch rather than fixing it.

Replaced with real `Property`/`Unit` models (`9cf08dc`): a property owns a name/description, a
unit is one bookable room within it. **A connector attaches at either level** — property-level
(one feed, several units, disambiguated by `external_unit_id`) or unit-level (one feed, one
unit — a dedicated iCal URL). `Unit::effectiveConnector()` resolves either shape the same way, so
every caller (sync, booking creation, display) asks one question regardless of which level the
real feed is attached at.

Migration was staged and data-preserving: existing connectors/bookings backfilled into
properties/units before the old columns were dropped, verified against the real dataset (65
bookings backfilled, 5 existing units correctly linked by name, 2 more discovered).

**For bokit**: this is exactly the shape question a channel-manager-agnostic sync layer has to
answer — a listing on Hostex/Channex is much closer to "unit," the property groups them. Worth
checking bokit's existing `SourceConnector`/`SyncEngine` already models this distinction before
assuming it needs building from scratch.

## 2. Group bookings are real, and only one field says so

Beds24's `masterId` denormalized onto `Booking.group_id` (`b8b7f81`, `9cf08dc`): several rows
sharing a non-null `group_id` are one reservation across several units. **Only `group_id` links
them — never guest name, dates, or any other coincidence.** A group's actual price/invoiceItems
data was found to live on only ONE row of the group (usually not intuitively "the first"),
confirmed against real synced data.

The agent-facing tool used to just say "check them all" in its description (`bd36421`) and trust
the model to remember. It didn't, reliably, across providers (Mistral, OpenRouter). Fixed by
moving the aggregation server-side instead (`889b140`, see §4) — a lesson worth generalizing:
**don't lean on prompt diligence for something the tool can just compute once.**

## 3. Raw payload preservation, deliberately not full column mapping

`Booking.raw` / `Property.raw` / `Unit.raw` carry the untouched platform record alongside the
mapped columns (`a9ff986`, `12bdce8`) — explicitly **not** a wider column-mapping effort. When a
platform exposes a field nobody bothered to map (Beds24's deposit, rate breakdown, amenities),
it's still reachable via raw instead of silently lost. Refreshed on every sync/discovery run.

Debug-mode viewer (assistant-level toggle) shows it pretty-printed in the Filament UI when
needed, gated off by default — this stays internal tooling, not something surfaced to an end
user.

## 4. Price is a real, recurring reliability problem — solved by computing it in the tool

Three separate rounds on this, each one narrowing the actual failure:

1. **`50258ad`** — Beds24's own `price` field is frequently `0` even on a fully quoted booking;
   the real accommodation/tax breakdown lives in `invoiceItems`, only returned with
   `includeInvoiceItems=true` on the same request (no extra API call). Confirmed live: a 0-price
   group booking actually had a 5400€ charge + 264.50€ taxe de séjour.
2. Tool description told the agent to fetch `raw` and check `invoiceItems` itself, including
   across every row of a group. Worked in principle, not reliably in practice.
3. **`889b140`** — moved the computation into the tool itself: `charges_total` (sum of `charge`
   type invoiceItems), `amount_paid` (sum of `payment` type invoiceItems — Beds24 genuinely
   exposes real payment records, not just charges, once you look), `balance_due`
   (`charges_total - amount_paid`, so the model never subtracts floats itself), and
   `deposit_required` (explicitly named to NOT imply payment — a platform's "deposit" field is
   the required/configured amount, not evidence anything was paid). All aggregated across every
   row of a group automatically, from one extra indexed query per batch (not N+1).

**The generalizable lesson**: when a value needs cross-referencing several rows or several
fields to compute correctly, and getting it wrong has real consequences (quoting a client the
wrong price), compute it once in the tool/service layer. Don't rely on an LLM remembering a
multi-step manual procedure every single time, regardless of how clearly it's documented in the
tool description.

## 5. Sync architecture

- `BookingConnectorInterface::listBookings(bool $force = false)` — cached 10 minutes
  (`CachesConnectorBookings`, shared by every connector type), `force` bypasses it. Credential
  validation stays outside the cache closure, so a broken connector still throws on every call
  regardless of cache state (`ac127b4`).
- `SyncBookingConnector` upserts by `[unit_id, external_id]` (was
  `[booking_connector_id, external_id]` before Property/Unit existed), tags a fallback unit name
  when the platform doesn't supply one. A UID-less feed entry is inserted fresh rather than one
  row silently absorbing every other such entry from the same connector (`5f060c8`).
- The read tool syncs itself before reading, rather than requiring a separate sync step
  (`57d04c2`) — see §6, this was a direct fix to an agent orchestration failure, not just
  convenience.
- `bookings:sync` artisan command stays, for ops/cron use, entirely separate from what the agent
  can call.

## 6. MCP tool design — what actually changed agent behavior

- **Ungated by default.** No `depends()` — works with zero connectors configured, a manually
  entered booking is a normal one to list (same precedent as `create_memory`/`create_skill`).
- **Draft-then-execute for anything that writes externally.** `create_booking` mirrors
  `send_mail_message`'s `confirmed=false`/`true` pattern: always creates locally; only pushes
  upstream when an explicit `booking_connector_id`/`unit_id` is given (never guessed at) and
  that connector actually supports writes.
- **One tool per concern, not one-tool-per-step.** A separate `sync_bookings` tool existed
  briefly; live testing showed the agent calling it AND `list_bookings` separately, redundantly,
  for one lookup (`57d04c2`). Removed — the read tool syncs itself. **If a tool's only job is a
  prerequisite step for another tool, the agent will often call both when it only needed to call
  the one that mattered — fold the prerequisite in rather than exposing it separately.**
- **Filters need real separation, verified with adversarial data.** `property` and `guest_name`
  were briefly merged into one fuzzy match — broke the moment a property was named after a
  person ("Moon", "Stéphanie") and collided with a guest search for the same name
  (`c1ccb8c`→`bd36421`). Kept separate.
- **Typo tolerance matters for name search.** `guest_name` matched the whole string at first — one
  wrong word in a caller's search (human or model) zeroed the entire match, even against a
  correctly-prefixed real name like "Atmosphère - Johan Lolot" (`39951c9`). Switched to
  any-word-matches.
- **When a narrow search comes up empty, the tool should say to broaden it, not just fail.**
  Description explicitly tells the agent it can call with no filter at all and read/reason over
  the full list, rather than only trying progressively narrower filters and giving up
  (`bd36421`). This became a base-agent-level instruction later (§7) — the tool-level echo
  reinforces it right where the decision gets made.
- **Trim tool descriptions to operative rules only.** State the capability and the one
  non-obvious rule per point; drop justification, examples, and "don't do this" explanations —
  they cost tokens on every single call and didn't change behavior (`1d0dbfe`).

## 7. Generic tool discipline belongs in the base system, not a business skill

The single biggest behavioral fix (`889b140`), prompted directly by watching the agent fail on
things that were technically documented somewhere, just not somewhere it reliably read:

> Once a tool call has already returned a fact, use it — don't ask the person to repeat it. If a
> specific search comes back empty, broaden it before reporting nothing was found. When
> composing/sending an email, always call the real send tool and show its own returned draft
> verbatim — never hand-write an approximation. Before drafting from scratch, check for a
> template location. Read monetary fields carefully — a "deposit" is usually what's due, not
> proof it was paid.

This lives in the **always-present** system prompt (unconditional, no skill/user/assistant
gating), not in the Gîtes Mosaïques business skill it was originally half-documented in. The
generalizable takeaway: **a behavior that should apply to any user of the product, regardless of
which business-specific configuration they've set up, belongs in the engine's own base
instructions — a skill is for domain facts (pricing formulas, house tone), not for "use your
tools properly."** Exactly the kind of thing that has to survive the engine/app split cleanly.

## 8. Credential-handling patterns worth reusing

- **A single-use invite code is not a durable credential — exchange it once, store the result.**
  Beds24's "API v2 Invite code" looked like a refresh token but wasn't; `exchangeInviteCode()`
  does the one-time exchange server-side, the form field for the code itself is write-only
  (never hydrated back), and Test Connection is hidden until a real token exists — testing would
  otherwise burn the code without saving (`8f78775`).
- **A hidden/inactive credential field can still pollute saved data.** A dot-notation
  `credentials.*` field left invisible for the other provider still dehydrated as `null` and got
  saved into the credentials array anyway — fixed by explicit pruning in
  `mutateFormDataBeforeCreate/Save` rather than trusting `dehydrated()` alone (`bc0bf52`).

## 9. Infrastructure gotchas that cost real debugging time

- **Filament's automatic tenant scoping doesn't reliably apply outside a full HTTP request
  cycle** (`c4d5de1`) — confirmed via a failing test, not assumption. Every tenant-owned resource
  here scopes explicitly (`$isScopedToTenant = false` + `getEloquentQuery()` +
  `mutateFormDataBeforeCreate()`), verified with a real cross-tenant test rather than trusting
  the automatic mechanism.
- **A generic HTTP client's default User-Agent can get silently blocked as a fingerprint, not a
  rate limit.** Guzzle's default `GuzzleHttp/7` UA was getting 429'd fetching iCal feeds — not a
  real rate limit, a client fingerprint block. Fixed with an honest custom UA (`f44090d`).
- **MariaDB migration behavior genuinely differs from SQLite for drop order.** Two real deploy
  failures, same migration, same root class of bug: (a) a column already gone (real DB drift
  between what the migrations table claims and actual schema) makes a plain `dropColumn` throw
  instead of no-op (`fb8351d`); (b) MariaDB has no separate plain index for a foreign key when a
  compatible composite unique index already covers it — refuses to drop that index while the
  constraint still depends on it, order-sensitive in a way SQLite never enforces (`f626851`).
  Both fixed by checking actual current state (`hasColumn`/`hasIndex`/`hasForeignKey`) before
  acting, verified against a real local MariaDB instance, not just SQLite. **Any migration
  touching a real, already-deployed database should assume it might be retried after a partial
  failure and be safe to re-run — this isn't paranoia, it happened twice in a row on the same
  file.**

## 10. Filament UI conventions (lower priority to port, but consistent and cheap)

- Computed display `name` = label + unit combined, collapsed to just one when they're equal —
  reused pattern across `BookingConnector`/`Booking`.
- Connector display label appends its provider type ("Gîtes Mosaïques (Beds24)") everywhere a
  connector is shown or picked.
- "Discover units" is additive-only — links/creates, never overwrites a hand-written
  description.
- Bulk activate/deactivate alongside the existing per-record toggle.
- Inline connector creation from the Property/Unit form's own connector picker
  (`createOptionForm`), not only through a separate top-level list.
- Global table defaults (pagination, filter placement/labeling) registered once via
  `Table::configureUsing()` in `AppServiceProvider`, not repeated per resource.
- An empty/no-connector field stays genuinely blank — no "Manual" placeholder standing in for
  nothing.
