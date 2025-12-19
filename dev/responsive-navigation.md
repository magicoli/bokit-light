# Responsive Navigation - Breakpoints

## Breakpoint Strategy

### Mobile (<768px)
- **Nav bar**: Logo + Badge LOCAL + Date courte (jeu 19/12/24) + ☰
- **Main menu**: Hidden
- **Admin/User menus**: Hidden
- **Locale switcher**: Hidden
- **Hamburger**: Contains ALL menus

### Tablet (768px - 1023px)
- **Nav bar**: Logo + Badge LOCAL + Main menu (About/Calendar/Properties) + Date courte + Locale + ☰
- **Main menu**: Visible horizontally
- **Admin/User menus**: Hidden → in hamburger
- **Locale switcher**: Visible in nav bar
- **Hamburger**: Contains only Admin/User sections

### Desktop (≥1024px)
- **Nav bar**: Logo + Badge LOCAL + Main menu + Admin + User + Date complète + Locale
- **Everything visible**: No hamburger needed
- **Full navigation**: All menus displayed horizontally

## Date Formats

- **Mobile/Tablet**: `ddd D/M/YY` → "jeu 19/12/24"
- **Desktop**: `dddd LL` → "jeudi 19 décembre 2024"

## Hamburger Menu Structure

### On Mobile (<768px)
```
☰ Menu
├─ Main Navigation (About, Calendar, Properties)
├─ [User Name]
│  ├─ My Settings
│  └─ Logout
├─ Admin (if admin)
│  └─ Admin Settings
└─ Language Selector (EN | FR)
```

### On Tablet (768px - 1023px)
```
☰ Menu
├─ [User Name]
│  ├─ My Settings
│  └─ Logout
├─ Admin (if admin)
│  └─ Admin Settings
└─ Language Selector (EN | FR)
```

## CSS Classes Used

- `.hamburger-button` - Order last (always right), visible until 1024px
- `.main-menu` - Hidden on mobile, visible from 768px
- `.mobile-menu` - Overlay menu, visible until 1024px
  - `.menu-section.main-nav` - Hidden on tablet (768-1023px)
- `.nav-actions` - Contains date + hamburger + desktop menus
- `.dropdown` - Admin/User dropdowns, hidden until 1024px
- `.locale-switcher` - Hidden on mobile, visible from 768px
- `.nav-date` - Format changes based on screen size

## Implementation Notes

1. Hamburger button uses `order-last` to stay rightmost
2. Alpine.js `x-data="{ mobileMenuOpen: false }"` controls menu
3. No "Language" title in hamburger - it's implicit
4. Badge LOCAL is more compact on mobile
5. Main navigation doesn't appear in tablet hamburger (already in nav bar)
