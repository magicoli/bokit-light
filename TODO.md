# TODO

- [x] update hbook module
- [ ] update multipass module
- [ ] create ical module and migrate all ical-specific methods in this module
- [ ] improve beds24 module to handle de-duplication of bookings from other modules
- [x] update sync methods (command-line and cron) to properly handle the new module architecture — **SyncRegistry done, see below**
- [ ] improve wp bokit-connector plugin (plugin update, plugin auto-update, dependency check for crash prevention...)

## SyncRegistry architecture ✅ (in progress)

`bokit:sync` must not know about specific modules. Each module registers its own sync handler.

- [x] Design: `app/Contracts/SyncHandler.php` interface + `app/Services/SyncRegistry.php`
- [ ] Implement `SyncHandler` interface and `SyncRegistry` service in core
- [ ] Refactor `SyncIcalFeeds` (bokit:sync) to iterate `SyncRegistry::all()`
- [ ] Extract current iCal logic into `IcalSyncHandler` (registered in AppServiceProvider or future ical module)
- [ ] Create `Beds24SyncHandler` in `modules/beds24/` (registered in `Beds24ServiceProvider`)

## Beds24 sync — corrections from taxesejour-bridge review

Reference: `../taxesejour-bridge/beds24.py` (verified, production-grade implementation).

- [ ] **Guest email field**: verify API returns `guestEmail` (not `email`) — update deduplication query accordingly
- [ ] **lastNight → checkout**: confirm `check_out = lastNight + 1 day` is applied correctly in current sync
- [ ] **Amount fallback**: when `acc_ttc <= 0` AND invoice lines exist with negative balance → use `payment_total` (type-200 lines) for standalone bookings; when no invoice at all AND is group master → keep `acc_ttc = 0` (amounts live on sub-bookings, don't use price_field)
- [ ] **iCal Beds24 sources**: add per-unit toggle in Filament unit edit page to disable Beds24 iCal when Beds24 API is the active source (currently only configurable in legacy admin)

## Beds24 group bookings — known limitation

Beds24 group invoices assign status=3 to sub-bookings (unit placeholders under a confirmed master).
The taxesejour-bridge handles this with a 2-pass approach:
- Pass 1: collect all confirmed master bookIds (`group` field present + `masterId == bookId`)
- Pass 2: include status=3 sub-bookings whose `masterId` is in the confirmed masters set

**Bokit-light data model** is ready (unit_id=null for group summary row, `is_group_member` in metadata for sub-bookings) but the **sync does not yet create group structures**.

- [ ] Implement 2-pass group detection in Beds24SyncHandler
- [ ] Create group master booking row (unit_id=null) when `is_group_master=true`
- [ ] Link sub-bookings to master via metadata (`group_master_id`, `is_group_member=true`)
- [ ] Price logic: group master acc_ttc stays 0 (amounts are on sub-bookings)

## IMPORTANT RULES

- proceed step by step, make sure the current step is properly tested and validated by the user before proceeding to next step
- No data can be hardcoded (source names, unit names, ...). The app is for general use, not dedicated to the current setup. All specifics come from config and/or external sources data.
- all external sources data are handled by separate modules in modules/
- each module could be disabled or simply deleted from modules/ folder without affecting the general app behaviour. The core app is unaware of specific modules
- wp plugin modules like hbook or multipass can use wp-connector module for shared methods, and rely on wordpress/bokit-connector plugin on the wp site side
- the wp companion plugin wordpress/bokit-connector/ version must be bumped after each change, and it must be deployed to the wordpress website before any testing. Deployment process is detailed in AGENTS.md

## multipass module

Adapt wp multipass import module using the same principles applied in HBook module.
- [x] add mapping config in Unit Edit page. multipass type and option field exist, but option field must be populated with data collected from wp
- [ ] adjust multipass:import to collect data according to the new mapping structure
- [ ] filter out external bookings (imported in multipass from  other sources)
- [ ] handle group reservations: multiple bookings in the same order, like a group booking multiple units at the matching dates, handle individual unit pricing and guests and group  total pricing and guests
- [ ] uid: use multipass uid, prefixed with "multipass:", as booking uid
- [ ] deduplication: make sure to treat as one item same booking collected from different sources (e.g. iCal which might or might not provide booking uid

Use hbook module as base reference.
Everything related to multipass is contained inside modules/multipass/, the main app only contains generic processes.

### Multipass structure:

- mltp_prestation: are the actual orders, might contain one or more bookings or service, treated as groups if more than one booking
- mltp_detail: all the items of the order, including not only bookings but also additional services or costs. Items matching a configured unit  sources are treated as bookings, other items are treated as options. All are imported but processed differently.
- Only prestations with more than a single booking have a separate group entry in bookings table. Prestations containing only a single booking matching a configured unit have only one entry in bookings table with all the collected data.

**THIS IS VERY IMPORTANT FOR DEDUPLICATION AND UPDATES**: the booking UID (unique id specific to the source) does not seem to be stored as is. We must analyse the metas starting with 'source%' or 'origin%' or ending with '%id' or '%url' to understand which meta should be use to reconstruct a proper uid according to the actual booking source.

## bokit:sync

- discard old behaviour processing different sync methods globally (mainly ical)
- process imports on a property>unit basis, applying only sources configured for each unit, in the configured order
- data might be collected globally to reduce the number of external API calls, but the changes must be applied per unit according to the units sources
- only import bookings from configured source in each unit. Do not import bookings that don't match any unit configured source
- sync applies to both php artisan bokig:sync and to cron task
- individual modules import commands can still be called individually, mainly to populate initial data after adding a source or for development purpose

## bokit-connector wp plugin

- add a deployment/update procedure in Bokit UI, next to WP credentials in Properties edit page.
- add dependency check to make sure the website does not crash if related plugin (hbook, multipass...) is inactive or not installed
