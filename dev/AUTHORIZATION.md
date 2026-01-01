# Authorization System - Bokit Light

## Overview

Bokit Light uses a unified authorization system based on **roles** and **capabilities**. Super admins always have full access to everything.

## User Roles

Roles are stored in the `users.roles` field as a JSON array:

```php
['property_manager', 'manager', 'other_role']
```

### Built-in Roles

1. **super_admin** (via `is_admin` field)
    - Full access to everything
    - Can manage all resources
    - Can access all admin sections

2. **manager** (global)
    *Note: No practical use yet, don't waste time on this*
    - Can access all admin sections
    - Can manage ALL properties, bookings, units, etc.
    - Practically, only admins would have access if used in rules currently

2. **manage $class or $class_name** (scope)
    *Note: No practical use yet, don't waste time on this*
    **Not the same as manager, not the same as property_manager**
    - Can access admin sections for $class objects
    - Can manage all $class objects
    - Used in rules for logic clarity and future differentiation
    - Practically, only admins have access currently

3. **property_manager** (owner)
    - Can access only admin sections with "property_manager" capability
    - In those sections, can ONLY view and manage THEIR OWN properties and related resources (property_id check)
    - LIMITED by ownership checks and property_id relationship

3. **booking_manager** (owner)
    *Note: No practical use yet, don't waste time on this*
    - Can access only admin sections with "booking_manager" capability
    - Can ONLY manage bookings for authorized properties
    - LIMITED by property rights and property_id relationship

## Summary

- **One function:** `user_can()` for all permission checks
- **Super admins:** Always have access - covered by user_can("super_admin") or user_can(anything)
- **Gates:** Define which models each role can manage - follows the roles rules in priority
- **Middleware:** Enforce permissions at route level
- **Menus:** Auto-hide based on `capability` value
- **Pages:** Accessible based on `capability` value (403 if not authorized)
