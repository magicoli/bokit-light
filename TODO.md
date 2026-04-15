# TODO

- [x] update hbook module
- [ ] update multipass module
- [ ] create ical module and migrate all ical-specific methods in this module
- [ ] improve beds24 module to handle de-duplication of bookings from other modules
- [ ] update sync methods (command-line and cron) to properly handle the new module architecture
- [ ] improve wp bokit-connector plugin (plugin update, plugin auto-update, dependency check for crash prevention...)

## IMPORTANT RULES

- proceed step by step, make sure the current step is properly tested and validated by the user before proceeding to next step
- No data can be hardcoded (source names, unit names, ...). The app is for general use, not dedicated to the current setup. All specifics come from config and/or external sources data.
- all external sources data are handled by separate modules in modules/
- each module could be disabled or simply deleted from modules/ folder without affecting the general app behaviour. The core app is unaware of specific modules
- wp plugin modules like hbook or multipass can use wp-connector module for shared methods, and rely on wordpress/bokit-connector plugin on the wp site side
- the wp companion plugin wordpress/bokit-connector/ version must be bumped after each change, and it must be deployed to the wordpress website before any testing. Deployment process is detailed in AGENTS.md

## multipass module

Adapt wp multipass import module using the same principles applied in HBook module.
- add mapping config in Unit Edit page. multipass type and option field exist, but option field must be populated with data collected from wp
- filter out external bookings (imported in multipass from  other sources)
- handle group reservations: multiple bookings in the same order, like a group booking multiple units at the matching dates, handle individual unit pricing and guests and group  total pricing and guests
- uid: use multipass uid, prefixed with "multipass:", as booking uid
- deduplication: make sure to treat as one item same booking collected from different sources (e.g. iCal which might or might not provide booking uid
- use hbook module as reference
- everything related to multipass is contained inside modules/multipass/, the main app only contains generic processes

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
