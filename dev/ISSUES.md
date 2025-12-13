# Bokit - Issues & Future Features

## Current Issues

### Known Bugs
None currently reported.

### Technical Debt
None currently identified.

## Planned Features

### High Priority

#### 1. Blocked Dates Management
**Status:** Partially implemented (filter only)  
**Documentation:** `dev/UNAVAILABLE.md`

Currently, iCal events with `SUMMARY:Unavailable` are filtered out and not displayed. Full implementation would include:

- [ ] Display blocked dates in calendar (grayed out or with pattern)
- [ ] Store blocked dates in database (extend `bookings` table with `type` field)
- [ ] Manual block creation UI for property managers
- [ ] Support for multiple provider patterns (Airbnb, Booking.com, VRBO, etc.)
- [ ] Business rules (minimum stay gaps, cleaning buffers, etc.)
- [ ] Prevent manual bookings on blocked dates

**Implementation Notes:**
- Extend `bookings` table with `type VARCHAR(20)` field ('booking', 'blocked', 'maintenance')
- Update `BookingSyncIcal` to detect and categorize blocking patterns
- Calendar view should show blocked dates with visual distinction
- See `dev/UNAVAILABLE.md` for detailed implementation plan

### Medium Priority

#### 2. Multi-user Access Control
**Status:** Not started

- [ ] Role-based permissions (admin, manager, viewer)
- [ ] Property-specific access (user can manage only their properties)
- [ ] Audit log for changes
- [ ] User invitation system

#### 3. Booking Management Interface
**Status:** Basic view-only implemented

- [x] View booking details in modal
- [ ] Create manual bookings
- [ ] Edit booking details (guest name, dates, notes)
- [ ] Delete bookings
- [ ] Mark bookings as confirmed/pending
- [ ] Add pricing information

#### 4. Reporting & Analytics
**Status:** Not started

- [ ] Occupancy rate by unit/property
- [ ] Revenue tracking (requires pricing data)
- [ ] Export to CSV/Excel
- [ ] Calendar heatmap view
- [ ] Booking source statistics

### Low Priority

#### 5. Installation Flow Enhancement
**Status:** Basic 5-step wizard implemented

Currently the Enter key doesn't submit forms in the installation wizard. Investigation needed.

- [ ] Fix Enter key form submission in installation
- [ ] Add validation feedback
- [ ] Progress indicators between steps
- [ ] Installation test mode

#### 6. Mobile App
**Status:** Not started

- [ ] Native iOS app
- [ ] Native Android app
- [ ] Push notifications for new bookings
- [ ] Offline mode

#### 7. Email Notifications
**Status:** Not started

- [ ] New booking alerts
- [ ] Checkout reminders
- [ ] Sync error notifications
- [ ] Configurable email templates

## Feature Requests

Add user-requested features here.

## Won't Implement

Features that have been considered but decided against:

- **Real-time sync:** WordPress-style periodic sync (on page load) is sufficient and doesn't require external processes
- **Redis/Queue workers:** Using `dispatchAfterResponse()` keeps deployment simple

## Documentation Needed

- [ ] User guide for property managers
- [ ] API documentation (if API is exposed)
- [ ] Backup and restore procedures
- [ ] Deployment guide for production environments

## Performance Optimization

- [ ] Database indexing review
- [ ] Query optimization for large datasets
- [ ] Caching strategy for calendar views
- [ ] Image/asset optimization

## Security

- [ ] Security audit
- [ ] Rate limiting on sync operations
- [ ] Input validation review
- [ ] CSRF protection verification
- [ ] SQL injection testing

## Testing

- [ ] Unit tests for critical components
- [ ] Integration tests for sync operations
- [ ] Browser compatibility testing
- [ ] Mobile responsiveness testing
- [ ] Load testing with multiple properties/units

---

Last updated: 2025-12-11
