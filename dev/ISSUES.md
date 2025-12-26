# Bokit - Technical Issues & Debt

This file tracks concrete technical issues, bugs, and technical debt. For planned features and roadmap, see [ROADMAP.md](../ROADMAP.md).

## Known Bugs

None currently reported.

## Technical Debt

### Code Consistency Issues

#### Migrate all forms to use Form class
**Priority:** Medium  
**Status:** Not started

Currently forms use mixed approaches (raw HTML, Blade components, Form class). This creates inconsistency and maintenance overhead.

**Goals:**
- [ ] Audit all forms in the application
- [ ] Migrate to standardized Form class usage
- [ ] Remove duplicate form code
- [ ] Ensure consistent validation and error handling
- [ ] Document Form class patterns in DEVELOPERS.md

**Benefits:**
- Consistent form styling and behavior
- Single source of truth for form rendering
- Easier maintenance and debugging
- Better accessibility (ARIA attributes, labels)
- Automatic CSRF protection

**Files to audit:**
- Installation wizard forms
- Login/auth forms
- Property/Unit management forms
- Rate configuration forms
- Booking forms (when implemented)
- User management forms

---

#### Migrate all tables to use DataList
**Priority:** Medium  
**Status:** Partially complete

Some views still use hardcoded HTML tables instead of DataList component. This violates the "single source of truth" principle.

**Goals:**
- [ ] Audit all table usages in the application
- [ ] Convert remaining hardcoded tables to DataList
- [ ] Remove duplicate table markup
- [ ] Ensure consistent sorting, filtering, pagination
- [ ] Document DataList patterns in DEVELOPERS.md

**Benefits:**
- Consistent table styling across app
- Single place to modify table behavior
- Built-in responsive design
- Automatic grouping support
- Column visibility control (container queries)

**Known remaining tables:**
- None currently identified (rate calculator uses DataList ✅)

---

### Migration Naming Inconsistency

**Priority:** Low  
**Status:** Documented, no action needed

Historical migrations use 4 different naming formats:
- Laravel default: `0001_01_01_000000_create_users_table.php`
- Date only (variant 1): `2024_01_01_000002_add_user_roles_fields.php`
- Date only (variant 2): `2024_01_01_200000_purpose.php`
- Date + timestamp (correct): `2025_12_13_130643_create_source_events_table.php`

**Resolution:**
- All new migrations MUST use `php artisan make:migration` (Laravel standard)
- This is enforced in AGENTS.md
- Historical migrations remain as-is (working, don't touch)

---

## Performance Considerations

### Items to investigate (when needed)

- **Database indexing:** Review foreign keys and frequently queried columns
- **N+1 queries:** Audit for missing eager loading
- **Cache strategy:** Implement for heavy queries (properties, rates)
- **Asset optimization:** Consider CDN for production deployments

These are not urgent but should be addressed before significant scale.

---

## Security Notes

### Items to verify before production

- Rate limiting on public endpoints (iCal export, embeddable widget)
- Input validation completeness audit
- CSRF protection on all forms
- API authentication for Beds24/Lodgify integrations
- Secure storage of API keys (encrypted in database)

---

## Testing Gaps

### Areas needing test coverage

- Rate calculation logic (formulas, parent rates)
- iCal sync edge cases
- Calendar date handling (DST, timezones)
- User permissions and access control
- Booking conflict detection

Tests should be added incrementally as features stabilize.

---

## Documentation Gaps

### Missing or incomplete docs

- [ ] End-user guide for property managers
- [ ] API documentation (when public API is exposed)
- [ ] Deployment guide for production
- [ ] Backup and restore procedures
- [ ] Troubleshooting common issues

---

## Won't Fix / Won't Implement

**Real-time sync:** App-centric periodic sync (on page load) is sufficient. External cron/queue workers add deployment complexity without meaningful benefit for our use case.

**Redis/Queue workers:** Using `dispatchAfterResponse()` keeps deployment simple. Queue workers are overkill for current scale.

---

Last updated: 2025-12-26
