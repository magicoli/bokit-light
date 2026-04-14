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

## Data sources and their roles

| Source | Command | Type | Canal |
|--------|---------|------|-------|
| **Beds24 API** | `beds24:sync` | Permanent sync (cron) | Airbnb / Booking.com / Direct |
| **HBook** (WordPress) | `hbook:import` | One-shot historical import | Direct only (OTAs excluded by WP endpoint) |
| **Multipass** (WordPress) | `multipass:import` | One-shot historical import | Airbnb / Booking.com / Direct (via `origin` field) |

**Beds24 is the authoritative source for ALL Airbnb and Booking.com bookings.**
iCal sync is obsolete — only the Beds24 API provides prices, commission, guest counts, notes, full contact details.

## Architecture — sources et doublons

Multipass et Beds24 sont **conçus pour se synchroniser entre eux**. Cela signifie que presque toutes les réservations existent dans LES DEUX sources. L'objectif est : **une réservation physique = une seule ligne en base**.

```
bokit ↔ API beds24 ↔ API Airbnb         → source_name='beds24', apiSource='46'
bokit ↔ API beds24 ↔ API Booking.com    → source_name='beds24', apiSource='19'
bokit ↔ API beds24 ↔ résa directe Beds24 → source_name='beds24', apiSource='0'
bokit ← WP plugin ← hbook              → source_name='hbook'  (résa directes site WP uniquement)
bokit ← WP plugin ← multipass          → source_name='multipass'
```

**Sources iCal — toutes des doublons, à ignorer :**
- `api.beds24.com` (iCal Beds24) : doublon de la résa beds24 API correspondante
- `www.airbnb.fr`  (iCal Airbnb) : doublon de la résa beds24/apiSource=46 correspondante
- iCal ne transporte ni prix, ni commission, ni données guest complètes
- beds24:sync réassigne leur uid (beds24-{bookId}) et leur source_name → les "absorbe"
- Les entrées iCal résiduelles (`www.airbnb.fr` "Reserved") sont exclues du CSV

## Déduplication (ordre de priorité dans beds24:sync)

1. `uid = 'beds24-{bookId}'` (correspondance exacte)
2. `group_id = bookId` (résa iCal Beds24 importée avant l'API)
3. `unit_id + check_in + check_out exacts` (résa multipass/hbook pour la même résa Beds24)

Règle : si une correspondance est trouvée → mettre à jour (uid, prix, commission, source_name).
Ne pas écraser un nom d'invité réel avec le placeholder "Guest".

## Fuseaux horaires par source

- **hbook** : le plugin WP renvoie des dates locales `Y-m-d` → `shiftAndFormat()` applique le TZ
- **multipass** : idem — dates locales `Y-m-d` → `shiftAndFormat()`
- **beds24 API** : renvoie `firstNight`/`lastNight` en dates locales `Y-m-d`, checkout = lastNight+1
  → passé à `Booking::create()` qui appelle le mutateur → `shiftAndFormat()` automatique
- **iCal** : dates en UTC/naive — ne pas s'y fier pour les horaires

## Canal OTA (Airbnb / Booking.com / Direct)

Multipass fait la différence entre la **source de synchro** (comment la résa est arrivée dans multipass) et la **source de réservation** (l'OTA réelle). Le champ `origin` peut ne pas exister — dériver du `contact_email` dans ce cas.

```
beds24 / apiSource='46' → Airbnb
beds24 / apiSource='19' → Booking.com
beds24 / apiSource='0'  → Direct

hbook → Direct (toujours — OTA exclus par le plugin WP)

multipass → metadata.origin si présent, sinon dériver du contact_email :
    email contient '@airbnb.com' ou '.airbnb.com' → Airbnb
    email contient '.booking.com' ou '@guest.booking.com' → Booking.com
    sinon → Direct

airbnb / www.airbnb.fr → Airbnb (iCal placeholder — exclure si guest="Reserved*")
```

Codes Beds24 `apiSource` : `0`=Direct, `19`=Booking.com, `28`=iCal générique, `29`=Airbnb iCal, `46`=Airbnb API.

## Beds24 room_id → unités (Gîtes Mosaïques)

| beds24_room_id | Unité   |
|----------------|---------|
| 552312         | Zetoil  |
| 552313         | Moon    |
| 552314         | Violeta |
| 552315         | Zandoli |
| 552316         | Sun     |
| 553491         | inconnu (1 résa Combat Ouvrier Jul 2026) — à configurer |

## Déploiement et séquence d'import complète

```bash
# 1. Déployer le plugin WordPress (toujours après modification du plugin)
rsync --delete -Wavz wordpress/bokit-connector/ magic@gites-mosaiques.com:/home/mosaiques/domains/gites-mosaiques.com/www/wp-content/plugins/bokit-connector/

# 2. Reset et réimport complet (si nécessaire)
echo "delete from bookings" | sqlite3 storage/database/default.sqlite
php artisan hbook:import
php artisan multipass:import
php artisan beds24:sync --from=2025-01-01   # IMPORTANT: --from=2025-01-01 pour récupérer l'historique
                                             # Sans --from, seules les réservations futures sont récupérées

# 3. Export rapports CSV
php artisan bookings:export-csv --year=2025
php artisan bookings:export-csv --year=2026
```

## Résultats attendus 2025 (référence de validation)

- Airbnb : 5 réservations (Pierrot Perrin, Aurelien Gueno, Françoise Laurent, Armelle Giraud, Fabienne De Broeck)
- Booking.com : 12 lignes CSV (Sophie Nayrolles compte pour 3 car 3 gîtes = 3 lignes, 9 résas distinctes)
- Pierrot Perrin (Zetoil Jan28) : origin=airbnb stocké directement dans wp_postmeta (post_id=16962)
  car multipass n'a pas d'email pour cette résa — la donnée ne peut pas être dérivée automatiquement

## Multipass origin field

Plugin `bokit-connector` v0.6.0 : dérive l'`origin` depuis `contact_email`/`customer_email`/`attendee_email`
quand le champ `origin` n'est pas renseigné dans WordPress :
- `*@guest.booking.com` → bookingcom
- `*@airbnb.com` / `*@reply.airbnb.com` → airbnb

## CSV export

Command: `php artisan bookings:export-csv --year=2025`
Output columns: Arrivée; Départ; Gîte; Nuits; Locataire; Canal; Prix (€); Commission (€); Adultes; Enfants
Only confirmed bookings are exported.

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

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.7
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

## Livewire

- Use the `search-docs` tool to find exact version-specific documentation for how to write Livewire and Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` Artisan command to create new components.
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend; they're like regular HTTP requests. Always validate form data and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle Hook Examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>

## Testing Livewire

<code-snippet name="Example Livewire Component Test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>

<code-snippet name="Testing Livewire Component Exists on Page" lang="php">
    $this->get('/posts/create')
    ->assertSeeLivewire(CreatePost::class);
</code-snippet>

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests that have a lot of duplicated data. This is often the case when testing validation rules, so consider this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |

=== filament/filament rules ===

## Filament

- Filament is used by this application. Follow existing conventions for how and where it's implemented.
- Filament is a Server-Driven UI (SDUI) framework for Laravel that lets you define user interfaces in PHP using structured configuration objects. Built on Livewire, Alpine.js, and Tailwind CSS.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices.

### Artisan

- Use Filament-specific Artisan commands to create files. Find them with `list-artisan-commands` or `php artisan --help`.
- Inspect required options and always pass `--no-interaction`.

### Patterns

Use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),
</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),
</code-snippet>

Actions encapsulate a button with optional modal form and logic:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->form([
        TextInput::make('email')->email()->required(),
    ])
    ->action(fn (array $data, User $record): void => $record->update($data)),
</code-snippet>

### Testing

Authenticate before testing panel functionality. Filament uses Livewire, so use `livewire()` or `Livewire::test()`:

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Test',
            'email' => 'test@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Test',
        'email' => 'test@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Validation" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => null,
            'email' => 'invalid-email',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'email',
        ])
        ->assertNotNotified();
</code-snippet>

<code-snippet name="Calling Actions" lang="php">
    use Filament\Actions\DeleteAction;
    use Filament\Actions\Testing\TestAction;

    livewire(EditUser::class, ['record' => $user->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    livewire(ListUsers::class)
        ->callAction(TestAction::make('promote')->table($user), [
            'role' => 'admin',
        ])
        ->assertNotified();
</code-snippet>

### Common Mistakes

**Commonly Incorrect Namespaces:**
- Form fields (TextInput, Select, etc.): `Filament\Forms\Components\`
- Infolist entries (for read-only views) (TextEntry, IconEntry, etc.): `Filament\Forms\Components\`
- Layout components (Grid, Section, Fieldset, Tabs, Wizard, etc.): `Filament\Schemas\Components\`
- Schema utilities (Get, Set, etc.): `Filament\Schemas\Components\Utilities\`
- Actions: `Filament\Actions\` (no `Filament\Tables\Actions\` etc.)
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

**Recent breaking changes to Filament:**
- File visibility is `private` by default. Use `->visibility('public')` for public access.
- `Grid`, `Section`, and `Fieldset` no longer span all columns by default.
</laravel-boost-guidelines>
