# Each tenant as its own independent sub-site

Follow-up to dev/project-app-panel-tenancy.md and
dev/project-timezone-and-tenant-settings.md, now that real tenancy is in place: Oli's own
observation, "chaque tenant doit être un sous-site indépendant" — branding and language should
follow the tenant being viewed, not the app as a whole.

## Branding (done — see dev/project-app-panel-tenancy.md's later commits)

- Brand logo/name/home-link all follow `Filament::getTenant()` in the App panel, no app-wide
  fallback for the logo (empty means empty, not the app's own logo).
- Login screen: branding follows the tenant a guest was actually trying to reach
  (`session('url.intended')`, since Filament's real tenant resolution only runs post-auth) —
  scoped strictly to the login route so a stale session value never leaks onto another page.

## Language settings (this pass)

- App-wide available locales (`config('app.locales')`): expand to the requested set — en, es, de,
  fr, pt, it, nl (Latin scripts) + ru (Cyrillic), ja (CJK), ar (RTL) — specifically so writing-system
  rendering can be checked later (Oli's own words: "pour plus tard de vérifier que l'UI s'adapte
  correctement à tous les systèmes d'écriture"). No translation files needed for this pass — only
  `en`/`fr` exist, Laravel falls back to `fallback_locale` gracefully for the rest; this is about
  making the languages *selectable*, not translating the app into all ten right now.
- Per-tenant: two new Property fields, same tier as timezone/logo (dedicated columns, not nested
  in `options`):
  - `locale` — this tenant's own default language. Null = inherit the app-wide default
    (`config('app.locale')`).
  - `locales` — the subset of the app-wide list this tenant actually offers visitors (Oli's
    example: Gîtes Mosaïques enables only en/fr/de out of all ten). Null/empty = every app-wide
    locale is available (least-friction default — a tenant that hasn't configured this yet loses
    nothing).
- `BezhanSalleh\LanguageSwitch::locales()` already accepts a Closure (confirmed reading
  `HasLocales.php`, not assumed) — wired to `Filament::getTenant()?->availableLocales() ??
  config('app.locales')`, so the switcher itself only ever offers what the current tenant allows.
- The package's own `userPreferredLocale()` hook already sits in `getPreferredLocale()`'s cascade
  right after session/query-string and before browser Accept-Language — extended from
  `auth()->user()?->locale` to fall through to `Filament::getTenant()?->locale` next, so an
  anonymous first-time visitor to a tenant's own URL lands on that tenant's default language
  instead of whatever their browser happens to prefer. No deeper cascade rewrite than that one
  existing extension point.
