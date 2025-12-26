# BOKIT Roadmap

## Vision

Bokit is a standalone vacation rental calendar management solution designed to be lightweight, fast, and platform-agnostic. The priority is to quickly deliver a production-ready calendar containing essential booking information (price, availability, guest details, notes) with flexible integration options.

**Core Philosophy**:
- Standalone-first architecture
- Platform integrations as optional modules (WordPress, Drupal, etc.)
- iCal sync included (free self-host, low-cost SaaS)
- Advanced API integrations as premium modules (Beds24, Lodgify, etc.)
- Modular design for flexible deployment and monetization

**Target Users**:
- Indie property managers tired of WordPress bloat
- Small vacation rental businesses (1-20 properties)
- Property management companies needing white-label solutions
- Developers seeking embeddable calendar solutions

## Phase 1: Production MVP (URGENT)

These features provide a viable calendar management solution that can be deployed standalone or integrated into existing websites.

### 1. Rate Calculation System ⏳ IN PROGRESS
**Goal**: Calculate accurate prices for bookings based on flexible rate rules

**Current Status**:
- ✅ Rate model with parent_rate architecture
- ✅ Formula-based calculation engine
- ✅ Unit capacity filtering (bedrooms, max_guests)
- ✅ Rate calculator widget with container queries
- ✅ DataList grouping by property
- ✅ Responsive display (mobile/desktop)

**Remaining Work**:
- 🔧 Display message when no units available for the request
- 🔧 Fix parent_rate calculation bug
- 🔧 Implement unit combinations (multi-unit bookings for large groups)
- 🔧 Implement rate variations system (seasonal rates, promotions)
  - Apply formula to any "base" rate regardless of scope
  - Avoid duplicating variations for each rate
- 🔧 Property access control in calculator
  - Respect user permissions (admin sees all, restricted users see authorized properties only)
  - Currently shows all properties regardless of user access

**Technical Details**:
- Parent rate synchronization with model events
- Variable substitution: `base`, `nights`, `guests`, `adults`, `children`
- Priority system: unit > unit_type > property
- Price per night calculation

---

### 2. iCal Export
**Goal**: Export property calendars in iCal format for channel manager synchronization and calendar app subscription.

**Requirements**:
- Generate iCal feeds per property/unit
- Include booking dates, status, and basic info
- Public URL access for channel managers
- Automatic updates when bookings change (through cache invalidation)

**Technical Approach**:
- `/api/calendar/{property_id}/export.ics` endpoint
- Standard iCal format (RFC 5545)
- Cache invalidation on booking updates
- Rate limiting for public access

---

### 3. Booking Management
**Goal**: Create and edit bookings directly in the system

**Requirements**:
- Add new bookings with all required fields
- Edit existing bookings (dates, guests, price, notes)
- Validate availability conflicts
- Manual price override capability (theoric price still displayed to allow comparison)
- Link bookings to source (channel/direct)

**UI Components**:
- Booking form (modal or dedicated page)
- Inline calendar editing (drag to resize, click to create)
- Bulk operations (multi-booking actions)

**Data Fields**:
- Dates (check_in, check_out)
- Guest information (name, count, adults, children)
- Price (calculated + manual override)
- Status (confirmed, pending, cancelled)
- Notes (internal + guest-visible)
- Arrival time
- Source/channel reference (including source edit url link)

---

### 4. Beds24 API Integration
**Goal**: Import complete booking data from Beds24 PMS

**Why**: iCal sync only provides basic data. Beds24 API provides:
- Applied price (may differ from rate rules due to manual adjustments)
- Detailed guest information
- Arrival time
- Booking notes (internal + guest)
- Payment status
- Channel source
- All available data are imported and stored, including those not yet handled

**Implementation**:
- Beds24 API client service
- Automatic sync command (hourly cron)
- Webhook support for real-time updates
- Merge strategy (API data overwrites iCal data)
- Error handling and retry logic
- use Multipass WordPress plugin as initial reference for API usage

**API Endpoints Needed**:
- GET bookings by date range
- GET booking details by ID
- Webhook receiver for booking updates

---

### 5. Embeddable Calendar Widget
**Goal**: Display calendar on external websites (only for admin usage, same as current calendar but widget only and limited with rules, essentially limit to one property or limit to one user's rights through personal API key/Secret)

**Requirements**:
- JavaScript snippet for embedding
- Responsive display (mobile/desktop) (already covered by current layout)
- ~~No Customizable styling (at this stage)~~
- Exact same display and rules as internal calendar, to avoid developing specific views for this fast solution

**Technical Approach**:
- prefer direct html over or js generated content iframe or shadow DOM for style isolation
- Public API endpoint for calendar data
- CORS configuration
- CDN-friendly (cacheable assets)

**Customization Options**:
- NONE. Phase one only provides the calendar as-is.

---

## Phase 2: Enhanced Features

These features improve usability and scalability but are not blocking for initial production use.

### Unit Combinations
- Calculate rates for multi-unit bookings
- Example: 5 units (4×5 guests + 1×12 guests) = up to 32 guests
- Combination optimization (cheapest, preferred units)
- Group booking workflow

### Availability Management Integration
- [x] Import unavailable dates from sources (already covered for iCal)
- Option to include unavailable units in results
- Public widget: only search within available units (no hide/show system: the data for unavailable units must not exist at all in the rendered html)
- Admin panel: show with visual indicator for rates control

### Advanced Rate Variations
- Time-based modifiers (early bird, last minute)
- Stay length discounts (weekly, monthly)
- Occupancy-based rates
- Multiple promotions stacking rules

### Payment Integration
- Online payment processing (stripe, paypal)
- Deposit calculation and processing (deposit paid = booking confirmed)
- Balance payment processing

## Phase 3:

### Payment Integration
- Payment plan management
- Refund handling

### Invoicing
- Basic quotes / Invoices management
- Integration with external invoicing service (Invoice Ninja) for legal rules fullfilment

## Bi-directional Beds24 API synchronisation
- proper handling of what can and cannot be changed locally
- avoid overriding of local values in sync (e.g. allow changing diplay name or notes for local display)
- update allowed changes on OTA/Channel Manager
- options for private/shared data for locally generated or other sources bookings

## Arrival and departures only calendar
- user level
- create a calendar containing only arrival and/or departures
- select which properties to include
- allow calendar alerts

## User dashboard
- Upcoming arrivals
- Recent bookings
- Current week calendar
- Customizable

---

## Next phases: Advanced Features (Future)

Lower priority features for later consideration:

### Implement other features in "Multipass" WordPress plugin
- Booking list
- Clients list
- Annual reports
- ... More to be defined after current plugin analysis

### Implement Bokit WordPress plugin
- To be defined, either as a bridge or allow full app to be installed as plugin with a compatibility layer

### Dynamic Permissions System
- Page/menu declaration with access rules
- Automatic menu generation based on user permissions
- View-level authorization checks
- Replace hardcoded menus in layout
- Consistent access control across all pages
- Separate admin menu available for inclusion (E.g. in sidebar, with similar hamburger mobile variant)

### Improved Layout System
- [x] Dynamic sidebar visibility (already covered)
- Adjust layout according to sidebars (1 and 3 columns already covered, 2 columns to implement when only 1 sidebar has content)
- Currently shows 2 sidebars or none (wastes space when only 1 has content)
- Responsive sidebar collapsing

### Revenue Management
- Demand-based dynamic pricing
- Competitive rate analysis
- Seasonal optimization
- Occupancy forecasting

### Channel Manager Integration
- Direct connection to other OTAs (Lodgify)
- OTA integration as module, that could easily enabled/disabled or imported
- Automated rate distribution
- Booking synchronization

### Reporting & Analytics
- Metrics for app optimization
    - requests times, errors and failures, cpu usage, disk usage...
- Metrics for business strategy
    - Revenue reports
    - Occupancy statistics
    - Rate performance analysis
    - Guest demographics

---

## Technical Debt & Maintenance

### Code Quality
- Comprehensive test coverage
- Performance optimization
- Security audit
- Documentation updates

### Infrastructure
- Database optimization
- Cache strategy
- Backup automation
- Monitoring and alerts

---

## Success Metrics

### Phase 1 Completion Criteria
- ✅ Rate calculator produces accurate prices
- ✅ All units/properties accessible to authorized users
- ✅ iCal export working with channel managers
- ✅ Bookings can be created/edited through UI
- ✅ Beds24 data syncing reliably
- ✅ Widget embedded on production website
- ✅ WordPress plugin fully replaced

### Phase 2 Completion Criteria
- Permission system prevents unauthorized access
- Layout adapts correctly to sidebar content
- Rate variations work across all scopes
- Unit combinations calculate correctly

---

## Notes

- All development follows project conventions (DataList, CSS architecture, i18n)
- Database changes via migrations (no manual SQL)
- All UI text must be localizable
- Code, documentation and log messages in English
- Focus on shipping functional features incrementally
