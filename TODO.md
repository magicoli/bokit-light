# TODO

Tasks, bugs and features moved to the ticket tracker on 2026-07-27 — see `/admin/tickets`, or
`GET /api/tickets`. Nothing actionable is left in this file.

What remains below is reference material: the rules the project must follow and the specs of two
sources. It belongs in DEVELOPERS.md and is tracked as such in the tracker; this file goes away
once that move is done.

## IMPORTANT RULES

- proceed step by step, make sure the current step is properly tested and validated by the user before proceeding to next step
- No data can be hardcoded (source names, unit names, ...). The app is for general use, not dedicated to the current setup. All specifics come from config and/or external sources data.
- all external sources data are handled by separate modules in modules/
- each module could be disabled or simply deleted from modules/ folder without affecting the general app behaviour. The core app is unaware of specific modules
- wp plugin modules like hbook or multipass can use wp-connector module for shared methods, and rely on wordpress/bokit-connector plugin on the wp site side
- the wp companion plugin wordpress/bokit-connector/ version must be bumped after each change, and it must be deployed to the wordpress website before any testing. Deployment process is detailed in AGENTS.md

## Multipass structure

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
