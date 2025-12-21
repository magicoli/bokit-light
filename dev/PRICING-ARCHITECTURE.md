# Pricing System Architecture

## Overview
This document outlines the pricing system architecture for Bokit Light, designed to handle complex pricing scenarios while maintaining flexibility for future enhancements.

## Current State Analysis
- **Booking Model**: Already has `price` and `commission` fields (decimal:2)
- **Unit Model**: Has `settings` JSON field for configuration
- **Property Model**: Has `settings` JSON field for configuration
- **Database**: Ready for pricing extensions

## Proposed Architecture

### 1. Core Pricing Models

#### Rate
- Base pricing entity
- Can be attached to Unit or Property
- Supports different calculation methods

#### RateRule  
- Conditional pricing rules
- Flexible condition system
- Priority-based evaluation

#### PricingComponent
- Modular pricing elements (base rate, taxes, fees, discounts)
- Composable calculation pipeline

#### PricingCalculation
- Stores calculation results
- Audit trail for pricing decisions

### 2. Calculation Engine

#### PricingCalculator
- Main calculation service
- Orchestrates component calculations
- Caching and optimization

#### CalculationMethod
- Strategy pattern for different calculation types:
  - Per night
  - Per night per person  
  - Per night per adult
  - Flat fee
  - Custom formulas

### 3. Condition System

#### ConditionEvaluator
- Rule-based condition checking
- Supports complex logic (AND/OR/NOT)
- Extensible condition types

#### Condition Types
- Date ranges (stay dates, booking dates)
- Time-based conditions (advance booking, duration)
- Guest-based (number of persons, adults vs children)
- Source-based (booking channel, property type)
- Custom conditions

### 4. Tax and Fee System

#### Tax
- Configurable tax rules
- Different calculation methods (percentage, fixed amount)
- Inclusive vs exclusive taxes

#### Fee
- Additional fees (cleaning, service, etc.)
- Conditional application
- Per-unit or per-property fees

### 5. Payment and Status Integration

#### PaymentPlan
- Deposit calculation
- Installment planning
- Payment scheduling

#### BookingStatus
- Status transitions based on payments
- Automated status updates
- Payment tracking

## Database Schema

### Core Tables
```sql
rates (id, unit_id, property_id, name, base_amount, calculation_method, is_active, priority, settings)
rate_rules (id, rate_id, name, conditions, calculation_adjustment, priority, is_active)
pricing_components (id, booking_id, component_type, amount, calculation_details, created_at)
taxes (id, name, rate, calculation_method, is_inclusive, applies_to, conditions)
fees (id, name, amount, calculation_method, applies_to, conditions)
pricing_calculations (id, booking_id, total_amount, base_amount, tax_amount, fee_amount, calculation_snapshot)
```

### Supporting Tables
```sql
payment_plans (id, booking_id, due_amount, due_date, status, payment_method)
booking_payments (id, booking_id, amount, payment_date, method, status)
```

## Implementation Phases

### Phase 1: Base Pricing (Current Sprint)
1. **Rate Model**: Basic rate configuration
2. **PricingCalculator**: Simple per-night calculation
3. **Booking Integration**: Price calculation on booking save
4. **Basic UI**: Rate management interface

### Phase 2: Advanced Rules
1. **RateRule Model**: Conditional pricing
2. **ConditionEvaluator**: Rule processing
3. **CalculationMethods**: Multiple calculation strategies
4. **Rule UI**: Visual rule builder

### Phase 3: Taxes and Fees
1. **Tax/Fee Models**: Comprehensive tax system
2. **Component Pipeline**: Modular calculation
3. **Tax Reporting**: Tax calculation reports
4. **Fee Management**: Fee configuration interface

### Phase 4: Payment Integration
1. **PaymentPlan Model**: Deposit and installment calculation
2. **Payment Tracking**: Payment status management
3. **Status Automation**: Automatic booking status updates
4. **Payment UI**: Payment plan interface

## Key Design Principles

### 1. Flexibility
- Strategy pattern for calculation methods
- Rule-based condition system
- Composable pricing components

### 2. Performance
- Calculation caching
- Optimized database queries
- Lazy loading for complex rules

### 3. Auditability
- Complete calculation history
- Rule application tracking
- Price change logging

### 4. Extensibility
- Plugin-ready architecture
- Custom calculation methods
- Third-party integration points

## API Design

### Pricing Endpoints
```
GET /api/units/{id}/rates
POST /api/units/{id}/rates
PUT /api/rates/{id}
DELETE /api/rates/{id}

GET /api/bookings/{id}/pricing
POST /api/bookings/{id}/recalculate
GET /api/bookings/{id}/pricing-breakdown
```

### Calculation Endpoints
```
POST /api/pricing/calculate
GET /api/pricing/preview
```

## Testing Strategy

### Unit Tests
- Individual calculation methods
- Condition evaluation
- Component calculations

### Integration Tests  
- End-to-end pricing scenarios
- Database interactions
- API endpoints

### Performance Tests
- Large dataset calculations
- Concurrent pricing requests
- Cache efficiency

## Migration Strategy

### Data Migration
- Existing `price` field migration to new system
- Backward compatibility during transition
- Data validation and cleanup

### Feature Flags
- Gradual feature rollout
- A/B testing for new calculations
- Quick rollback capability

## Future Considerations

### Advanced Features
- Dynamic pricing (demand-based)
- Seasonal pricing automation
- Competitive pricing analysis
- Machine learning optimization

### Integration Points
- Channel manager pricing sync
- Accounting system integration
- Revenue management tools
- Third-party pricing engines

## Conclusion

This architecture provides a solid foundation for current pricing needs while being flexible enough to accommodate future requirements. The modular design allows for incremental development and testing, reducing risk and ensuring faster delivery of functional features.

The phased approach ensures we can deliver value quickly while building toward a more comprehensive pricing system.