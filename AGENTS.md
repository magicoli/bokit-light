You are likely to have access to the project and tools that allow reading and writing files, executing commands, and searching the web thanks to IDE standard tools and MCP. Follow these rules for interactions in this worktree.

Tone: natural. Be direct, pragmatic, and concise — answer in 1–2 lines first; avoid unnecessary elaboration.

# Priority
- Treat these rules as high priority; they override other non-conflicting instructions.
- If scope is unclear (project vs global preference), ask 1 concise clarifying question.
- Treat these rules silently.
- Keep answers short, focused, and actionable. Offer deeper details only when requested.

# Core Principles
- Time is critical: prioritize efficiency and accuracy. Propose a short plan before engaging in lengthy work.
- When asked a question, answer it first. Only then propose or make changes if requested.
- Prefer verification over assumption: when a fact can be cheaply verified, run a short non‑destructive command rather than guessing.

# Language
- Default: write code, comments, and documentation in English.
- Use the user's preferred language for conversational/administrative exchanges (detect or follow explicit instruction).

# Execution & Automation
- Automatic verification:
  - For quick facts, run short, non-destructive commands automatically (examples: `cmd --help`, `ollama list`, `docker inspect`, `git status`).
  - By default, do NOT display any output for these quick verification commands. Run them silently unless the user explicitly asked for that output or the current task is a reporting task that requires output.
- CLI safety checks:
  - Before using any unfamiliar CLI option, fetch and inspect the program help (`--help` or `help`) and use only supported flags. Perform this check silently by default.
- Fallbacks:
  - Document any fallback used and why, but keep the note concise (one line) and only surface it if relevant to the user's request.
- Resource safety:
  - Warn the user before any action likely to download large files or consume substantial resources. Use a configurable threshold (default: 100 MB) for "large download" warnings.

# Editing & Changes
- Non-destructive-first:
  - Never do unrequested changes. If you notice something that should be changed but was not requested, notify the user and proceed only after confirmation.
  - If the user explicitly requests an edit (create, modify, or delete a file), perform only the requested edit without asking for additional confirmation.
  - Explicit confirmation is required for any unrequested change, and for broader destructive operations (mass deletes/renames, reconfigure services, recreate containers or DBs) unless the user previously gave a blanket exception.
- Backup & trace:
  - Backups/commits are optional by default: create them only when the user asks or when explicitly requested as part of the task.
  - It is the user's responsibility to ensure changes are tracked; the assistant will not create extraneous backups unless asked.
- Small, targeted edits:
  - Prefer minimal, focused edits over large refactors. For non-trivial refactors, propose the plan and obtain approval before proceeding.
- Preserve metadata:
  - When creating or overwriting files, preserve file permissions and ownership where applicable and feasible.

# Communication & Clarification
- If the request is ambiguous, ask one concise clarifying question before taking substantive action.
- If a request cannot be fulfilled (tooling missing, permissions, resource constraints), state that plainly and present one or two feasible alternatives.
- Do not force the user into copy/paste loops: if possible, execute commands and use the results rather than asking the user to paste outputs.

# Code & Design Guidance
- Prefer simple, well-tested constructs and avoid unnecessary complexity.
- Avoid global mutable state; prefer dependency injection and small pure functions where reasonable.
- Reuse standard libraries and known tools; do not reinvent the wheel.
- Favor readable, testable code and include small, focused unit tests where appropriate.

# Transparency & Auditing
- When you run commands for verification purposes, do so silently by default and do not display output unless the user asked for it or the task is explicitly a reporting task.
- If a command fails and the failure is relevant to the user's request, include up to 5 lines of stderr (only when the user asked for details or when reporting is required). Offer the full output on request.

# Confirmation & Consent
- Before any action that:
  - Deletes or renames large sets of files,
  - Pulls or installs large packages,
  - Reconfigures services,
  - Recreates containers or databases,
  ask for explicit confirmation. The confirmation must state the intended action and acknowledge potential impact.
- If the user explicitly requested a change, treat that request as the confirmation for that specific change.

# Database Safety (CRITICAL)
- **NEVER use `php artisan migrate:fresh` or `migrate:refresh`** - these commands destroy all data
- **NEVER use `php artisan migrate`** to run new migrations - the app executes migrations automatically, never interfere, only let the user verify migrations are executed automatically
- **ALWAYS use `php artisan make:migration` to create new migrations** - this is the Laravel standard and ensures proper timestamp formatting
- If database reset is truly needed, ask for explicit confirmation and warn about data loss
- This rule overrides any suggestions in DEVELOPERS.md which is written for human developers who understand the consequences

# Short interactive checklist (apply automatically)
1. If ambiguous → ask 1 short clarifying question.
2. Answer the question first (1–2 lines).
3. If an edit is explicitly requested → perform it (no extra confirmation) and report a one-line summary after applying.
4. Run short, non‑destructive verification commands silently as useful; show output only when the user requested it or when the task is reporting-oriented.
5. For large/costly operations → warn in one line and summarize impact.

# Read the room
- Follow the repository's existing style (formatting, naming, tests, CI expectations).
- Prefer patterns, libraries, test styles, and conventions already used in the project over introducing new ones.
- When unsure about style or conventions, run quick repo checks (lint, format, tests) silently and report only minimal discrepancies if relevant.

# Git & commit behaviors
- Never sign commits. Never use mentions like "Co-authored-by". You don't auth anything, you are only a coding tool
- See [DEVELOPERS.md](DEVELOPERS.md) for commit message format and conventions
- If the user asks to commit changes:
  - Stage the files and create a local commit with a concise message derived from the actual git diff
  - Do NOT push; do not suggest pushing. Pushing is the user's responsibility
- Before committing:
  - Run `git status` and `git diff --staged` (or `git diff` for unstaged) and draft the commit message from the real diff
  - Present the one-line subject to the user if they asked for review
- If git is unavailable or the repo is not a git repo, state that plainly and abort the commit action

# Notes
- These rules override general assistant defaults for interactions in this workspace
- If the user later requests stricter safeguards, follow the new instruction
- For project-specific conventions (code style, architecture, commit format, testing), see [DEVELOPERS.md](DEVELOPERS.md)

===

# Booking Pipeline — READ THIS FIRST

**NEVER make manual corrections to booking data.** The sync must be fully automatic and reliable.
**NEVER hardcode booking-specific cases** (specific guest names, prestation IDs, etc.) anywhere in the code.
When a booking appears wrong, identify and fix the root cause in the import/sync logic or the source data.

## Data sources and their roles

| Source | Command | Type | Canal |
|--------|---------|------|-------|
| **Beds24 API** | `beds24:sync` | Permanent sync (cron) | Airbnb / Booking.com / Direct |
| **HBook** (WordPress) | `hbook:import` | One-shot historical import | Direct only (OTAs excluded by WP endpoint) |
| **Multipass** (WordPress) | `multipass:import` | One-shot historical import | Airbnb / Booking.com / Direct (via `origin` field) |

**Beds24 is the authoritative source for ALL Airbnb and Booking.com bookings.**
Beds24 is a channel manager: every OTA booking flows through it. The sync command **MUST ALWAYS** include all historical and future data. Use `beds24:sync --from=YYYY-MM-DD --to=YYYY-MM-DD` to explicitly specify the date range, but the default behavior should cover all dates automatically.

## Source priority (authoritative → weakest)

The priority is **defined by the order in `/admin/units/{id}/edit`** (sortable sources repeater), not hardcoded.
Higher priority sources always win on field conflicts.

```
Priority is configurable per unit via the sources repeater (top = highest):
1. First source in the list (e.g., beds24 API)
2. Second source in the list (e.g., multipass)
3. Third source in the list (e.g., hbook)
4. Last source in the list (e.g., iCal) ← always lowest priority
```

Rules:
- A higher-priority source **always wins** on a field conflict.
- The sync command absorbs weaker sources by reassigning their uid to the higher-priority source's ID.
- iCal-only entries with no guest name and no price should NEVER overwrite confirmed bookings.
- **NEVER downgrade** a confirmed booking to pending just because a source entry has a lower status.

## Architecture — sources and deduplication

Multipass and Beds24 are designed to sync with each other — most real bookings exist in both sources.
Goal: **one physical booking = one DB row**.

```
bokit ↔ API beds24 ↔ API Airbnb         → source_name='airbnb',      apiSource='46'
bokit ↔ API beds24 ↔ API Booking.com    → source_name='booking.com',  apiSource='19'
bokit ↔ API beds24 ↔ direct Beds24      → source_name='beds24',       apiSource='0'
bokit ↔ API beds24 ↔ Airbnb iCal        → source_name='beds24',       apiSource='28'/'29', referrer='Airbnb'
bokit ← WP plugin ← hbook               → source_name='hbook'  (direct website bookings only)
bokit ← WP plugin ← multipass           → source_name='multipass'
```

**iCal sources — all duplicates, absorb don't delete:**
- `api.beds24.com` (Beds24 iCal): duplicate of the corresponding beds24 API entry
- `www.airbnb.fr`  (Airbnb iCal): duplicate of the beds24/apiSource=46 entry
- iCal carries no price, no commission, no full guest data
- beds24:sync reassigns their uid (`beds24-{bookId}`) and updates source_name — "absorbs" them
- Residual iCal entries (`www.airbnb.fr` "Reserved") are excluded from CSV export

## Deduplication order in beds24:sync

1. `uid = 'beds24-{bookId}'` (exact match)
2. `group_id = bookId` (Beds24 iCal booking imported before the API run)
3. `unit_id + check_in + check_out exact` (multipass/hbook entry for the same physical booking)

Rule: on match → update (uid, price, commission, source_name, referrer, guest fields).
Never overwrite a real guest name with the "Guest" placeholder.
Never overwrite a `beds24-*` uid with a multipass uid.

## Canal OTA resolution (Airbnb / Booking.com / Direct)

**Two concepts to distinguish** (especially in multipass):
- **Sync origin**: how the booking arrived in multipass (Beds24 feed, Lodgify, direct entry…)
- **Booking origin**: which OTA the guest actually used to book (Airbnb, Booking.com, direct)

The `origin` field in multipass may store the sync origin, not the booking origin. Always prefer
email-domain detection when the `origin` value is ambiguous.

### Canal resolution logic (BookingsExportCsvCommand::resolveCanal)

```
source_name='airbnb' / 'www.airbnb.fr'
  → guest_name starts with 'Reserved' → skip (iCal placeholder, no data)
  → otherwise → Airbnb

source_name='beds24' (or 'airbnb', 'booking.com' after mapSourceName)
  → apiSource='46'                         → Airbnb  (Beds24 Airbnb API, authoritative)
  → apiSource='19'                         → Booking.com
  → referrer contains 'airbnb'             → Airbnb  (iCal booking, referrer is key!)
  → referrer contains 'booking'            → Booking.com
  → otherwise                              → Direct

source_name='hbook'
  → always Direct (OTAs excluded by WP endpoint)

source_name='multipass'
  → metadata.origin contains 'airbnb'     → Airbnb
  → metadata.origin contains 'booking'    → Booking.com
  → metadata.email domain *.airbnb.com    → Airbnb   (fallback when origin absent)
  → metadata.email domain *.booking.com   → Booking.com
  → otherwise                              → Direct
```

Beds24 apiSource codes: `0`=Direct, `19`=Booking.com, `28`=generic iCal, `29`=Airbnb iCal, `46`=Airbnb API.

**Critical**: for iCal-synced Beds24 bookings (apiSource 28/29), `apiSource` alone is insufficient —
the `referrer` field (e.g. "Airbnb", "Booking.com") is the only reliable OTA indicator.
The `referrer` field is saved by `Beds24SyncCommand::buildMeta()` and must never be removed.

## Required fields per source

### beds24:sync — metadata fields saved

All fields come from the Beds24 API `getBookings` response:

| Metadata key    | Beds24 field  | Purpose |
|-----------------|---------------|---------|
| `beds24_book_id`| `bookId`      | Dedup key |
| `beds24_room_id`| `roomId`      | Room mapping |
| `email`         | `email`       | Guest email |
| `phone`         | `phone`       | Guest phone |
| `mobile`        | `mobile`      | Guest mobile |
| `address`       | `address`     | Guest address |
| `country`       | `country`     | Guest country |
| `api_source`    | `apiSource`   | Channel code (0/19/28/29/46) |
| `api_ref`       | `apiRef`      | OTA booking reference |
| `referrer`      | `referrer`    | Channel name — **CRITICAL for iCal canal detection** |
| `num_adult`     | `numAdult`    | Adults count |
| `num_child`     | `numChild`    | Children count |
| `num_baby`      | `numBaby`     | Babies count |
| `notes`         | `notes`       | Booking notes |
| `message`       | `message`     | Guest message |

Booking-level columns (not metadata): `price`, `commission`, `adults`, `children`.

### multipass:import — metadata fields saved

| Metadata key              | Source field        | Purpose |
|---------------------------|---------------------|---------|
| `multipass_prestation_id` | `id`                | Parent prestation |
| `multipass_detail_id`     | `detail_id`         | Per-unit detail |
| `email`                   | `contact_email`     | Guest email |
| `phone`                   | `contact_phone`     | Guest phone |
| `origin`                  | `origin` (resolved) | Booking OTA canal |
| `adults`                  | `adults`            | Adults count |
| `children`                | `children`          | Children count |
| `babies`                  | `babies`            | Babies count |
| `total`                   | `total`             | Total TTC (incl. tourist tax for OTA bookings) |
| `deposit`                 | `deposit`           | Deposit amount |
| `paid`                    | `paid`              | Amount paid |

Note: multipass `total` includes tourist tax for Airbnb/Booking bookings (collected by OTA).
The accommodation price net of tourist tax cannot be computed from multipass alone.

### hbook:import — metadata fields saved

| Metadata key | Source field    | Purpose |
|--------------|-----------------|---------|
| `hbook_id`   | `id`            | HBook booking ID |
| `email`      | `guest_email`   | Guest email |
| `phone`      | `guest_phone`   | Guest phone |
| `origin`     | `origin`        | Always 'website' (direct bookings only) |
| `deposit`    | `deposit`       | Deposit amount |
| `paid`       | `paid`          | Amount paid |

## CSV export — required columns

Command: `php artisan bookings:export-csv --year=YYYY`

**Current columns** (BookingsExportCsvCommand):
`Arrivée; Départ; Gîte; Nuits; Locataire; Email; Canal; Prix (€); Commission (€); Adultes; Enfants`

**Missing columns that must be added** (tracked as TODO):
- `Bébés` — babies count (available in metadata for all sources)
- `Total TTC` — full amount including tourist tax (multipass: `metadata.total`; beds24: sum of price components)
- `Total HT` — accommodation net of TVA (derived)
- `Taxe de séjour` — tourist tax (for Airbnb/Booking: multipass.total − accommodation price)
- `Téléphone` — guest phone (metadata.phone)
- `Pays` — guest country (metadata.country, beds24 only)
- `Notes` — booking notes (metadata.notes)

## Beds24 room_id → units (Gîtes Mosaïques)

| beds24_room_id | Unit    |
|----------------|---------|
| 552312         | Zetoil  |
| 552313         | Moon    |
| 552314         | Violeta |
| 552315         | Zandoli |
| 552316         | Sun     |
| 553491         | unknown (1 booking Combat Ouvrier Jul 2026) — to configure |

## Deployment and full import sequence

```bash
# 1. Deploy the WordPress plugin (always after modifying it)
source .env
rsync --delete -Wavz wordpress/bokit-connector/ $LIVE_HOST:$LIVE_DOCUMENT_ROOT/wp-content/plugins/bokit-connector/

# 2. Full reset and reimport (when needed)
echo "delete from bookings" | sqlite3 storage/database/default.sqlite
php artisan bokit:sync   # Always includes ALL data (past and future)

# 3. Export CSV reports
php artisan bookings:export-csv --year=2025
php artisan bookings:export-csv --year=2026
```

## Expected 2025 results (validation reference)

- **Airbnb**: 5 bookings — Pierrot Perrin, Aurelien Gueno, Françoise Laurent, Armelle Giraud, Fabienne De Broeck
- **Booking.com**: 12 CSV rows (Sophie Nayrolles = 3 rows because 3 units = 3 lines, 9 distinct bookings)
- Pierrot Perrin (Zetoil Jan 28): `origin=airbnb` stored directly in wp_postmeta (post_id=16962)
  because multipass has no email for this booking — the value cannot be derived automatically
- Aurelien Gueno, Françoise Laurent, Fabienne De Broeck: no `origin` in multipass, no Airbnb email.
  Must be identified via `beds24:sync --from=...` which provides `referrer='Airbnb'` from the Beds24 API.

## Known data quality issues

- **brigittefiorucci@aol.com** appears as `contact_email` on ~83 multipass bookings — this is a default
  admin/fallback email, not a guest email. These bookings are all Direct (no OTA detection possible).
- **Multipass total includes tourist tax** for Airbnb/Booking.com bookings. The accommodation price is
  `total / 1.05` approximately but this varies; beds24 data is more accurate for price breakdown.
- **Multi-unit prestations**: if one unit is cancelled after the fact, the remaining unit inherits the full
  prestation total, inflating its price. Subtotals per unit (`detail.subtotal`) are the correct prices
  when available.

## Planned: per-unit source configuration (not yet implemented)

Currently `beds24_room_id` is a single field in `unit.options`, added by the beds24 module via
`PropertyForm::extend()`. This must be replaced by a **sortable source list** per unit in Filament.

### Target UI: `/admin/units/{id}/edit`

Replace the fixed "Beds24 Room ID" field with a sortable repeater containing:

```
Priority 1 (top = highest authority):  [beds24]   Room ID: [552316]  [drag handle] [remove]
Priority 2:                             [multipass] (no config needed)  [drag handle] [remove]
Priority 3:                             [hbook]    (no config needed)  [drag handle] [remove]
Priority 4 (bottom = lowest):           [ical]     URL: [...]  Label: [...]  [drag handle] [remove]
                                        [+ Add source]
```

### Data model

Store in `unit.options['sources']` as ordered array:
```json
[
  {"type": "beds24",    "room_id": "552316", "priority": 1},
  {"type": "multipass", "priority": 2},
  {"type": "hbook",     "priority": 3},
  {"type": "ical",      "url": "https://...", "label": "Airbnb iCal", "priority": 4}
]
```

### Sync behavior with priority

- Higher priority sources overwrite lower priority fields.
- iCal is ALWAYS the lowest priority — never overwrites any confirmed data.
- beds24 (non-iCal) is ALWAYS the highest priority — always wins on conflict.
- The actual priority order can differ per property/unit.
- iCal preferences must be migrated from legacy admin (`/mosaiques/{unit}/edit`) to this new UI.

---

# Project-Specific Conventions

## Internationalization (i18n) — CRITICAL

- **All PHP code text must be in English.** Never hardcode French (or any other language) in PHP or Blade files.
- All user-facing strings must use `__()` translation keys.
- Translations live in `lang/en/` (source of truth) and `lang/fr/` (French translations).
- Translation files are named after the resource (singular): `booking.php`, `property.php`, `unit.php`, `user.php`. Exception: `rates.php` (plural).
- Common/shared labels go in `app.php`.

### Translation Key Patterns

| Pattern | Example | Result |
|---------|---------|--------|
| `{resource}.field.{column}` | `booking.field.check_in` | "Check-in" |
| `{resource}.status.{key}` | `booking.status.confirmed` | "Confirmed" |
| `{resource}.section.{name}` | `booking.section.guests` | "Guests" |
| `app.{noun}` | `app.booking` | "Booking" |

## Filament Admin Panel

The admin panel (`/admin`) uses Filament v5. Follow existing conventions in `app/Filament/Resources/`.

### Resource File Structure

Each resource has its own directory with separate files for Form, Infolist, Table, and Pages:
```
app/Filament/Resources/{Model}/
├── {Model}Resource.php
├── Forms/{Model}Form.php
├── Infolists/{Model}Infolist.php
├── Tables/{Models}Table.php    # Plural for table class
└── Pages/
```

### DynamicTable Helper

`app/Filament/Support/DynamicTable.php` generates table columns from model configuration (`$list_columns`, `$casts`, `$appends`). Table classes call:
```php
DynamicTable::columns(Model::class, $langPrefix, $overrides);
DynamicTable::recordActions(Model::class, $langPrefix);
```

The `$langPrefix` is the translation file prefix (e.g., `'booking'`, `'rates'`).

### Key Rules for Filament Code

- All field labels, section headings, placeholders, and select options must use `__()` with the resource's lang prefix
- Never hardcode display text in Filament form/table/infolist definitions
- Filament has its own CSS pipeline — custom Tailwind classes don't work in panel views; use Filament components or `fi-*` classes
