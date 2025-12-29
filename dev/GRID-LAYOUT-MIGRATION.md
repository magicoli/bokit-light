# Grid Layout Migration Guide

## ✅ What's Done

### Core Structure
- ✅ **New semantic HTML structure** in `app.blade.php`
- ✅ **Grid-based CSS** in `layout-grid.css` with `grid-template-areas`
- ✅ **Cleaned up** obsolete rules in `app.css`
- ✅ **Build passing** - no CSS errors

### New Structure
```html
<div class="page-layout">
    <nav>Navigation</nav>
    <header>
        <h1>Page Title</h1>
        <p class="subtitle">Optional subtitle</p>
    </header>
    <main>Actual page content</main>
    <aside id="sidebar-left">Left sidebar content</aside>
    <aside id="sidebar-right">Right sidebar content</aside>
    <footer>Footer</footer>
</div>
```

### Key Changes
1. **No more `#content-wrapper`** - direct grid children
2. **Semantic `<header>`** at page level (not inside main)
3. **IDs instead of classes** for sidebars (`#sidebar-left`, `#sidebar-right`)
4. **`<h1>` in header** - proper document outline
5. **Grid-template-areas** for responsive layout

---

## 🔧 What Needs Migration

### Template @yields
The layout now supports two patterns:

**Pattern 1: Simple (current)**
```blade
@section('title', 'My Page')
@section('subtitle', 'Optional description')
@section('content')
    <!-- page content -->
@endsection
```

**Pattern 2: Custom Header**
```blade
@section('header')
    <div class="breadcrumbs">Home > Products</div>
    <h1>Custom Title</h1>
    <div class="actions">
        <button>Action</button>
    </div>
@endsection
@section('content')
    <!-- page content -->
@endsection
```

### Templates to Migrate

Most templates will work as-is, but check for:

1. **Custom headers inside content**
   - OLD: `<header>` inside `@section('content')`
   - NEW: Move to `@section('header')` or use Pattern 1

2. **Sidebar references**
   - OLD: `.sidebar`, `.sidebar-left`, `.sidebar-right`
   - NEW: `#sidebar-left`, `#sidebar-right` (IDs)

3. **Content wrapper references**
   - OLD: `#content-wrapper`
   - NEW: Use `.page-layout` or specific grid areas

### Known Templates (Priority Order)

**High Priority** (core functionality):
- [ ] `dashboard.blade.php`
- [ ] `properties/index.blade.php`
- [ ] `properties/show.blade.php`
- [ ] `rates/index.blade.php`
- [ ] `rates/calculator.blade.php`
- [ ] `calendar/index.blade.php`

**Medium Priority**:
- [ ] `units/index.blade.php`
- [ ] `units/edit.blade.php`
- [ ] Any custom error pages

**Low Priority**:
- [ ] Auth pages (login, register, etc.)
- [ ] Settings pages

### CSS Class Cleanup

Search for and update:
- `.main-column` → Remove (obsolete)
- `.header` (when referring to page header) → Use `header` element
- `.title` → Use `h1` directly
- `.sidebar` class → Use `#sidebar-left` or `#sidebar-right` IDs

---

## 🎨 Grid Responsiveness

The new layout adapts automatically:

**Mobile (< 768px)**: Vertical stack
```
nav
header  
main
sidebar-left
sidebar-right
footer
```

**Tablet (768px - 1023px)**: Sidebars side-by-side
```
nav
header
main
sidebar-left | sidebar-right
footer
```

**Desktop (1024px+)**: 3 columns
```
nav
sidebar-left | main | sidebar-right
footer
```

**2XL (1536px+)**: 4 or 5 columns
- 4 columns when one sidebar
- 5 columns when both sidebars

---

## 🚀 Testing Checklist

Before merging to master:

- [ ] Dashboard loads and looks correct
- [ ] Properties page works (list + show)
- [ ] Rates page works
- [ ] Calendar page works (full width, no sidebars)
- [ ] Mobile responsive (test hamburger menu)
- [ ] Tablet view (sidebars stack correctly)
- [ ] Desktop view (3+ columns layout)
- [ ] Sidebar content displays properly
- [ ] Footer always at bottom
- [ ] No console errors

---

## 🔄 Rollback Plan

If issues arise, rollback is simple:

1. In `app.blade.php`, change:
   ```blade
   @vite('resources/css/layout-grid.css')
   ```
   to:
   ```blade
   @vite('resources/css/layout-flex.css')
   ```

2. Restore previous `app.blade.php` from git:
   ```bash
   git checkout master -- resources/views/layouts/app.blade.php
   ```

3. Rebuild:
   ```bash
   npm run build
   ```

---

## 📝 Next Steps

1. **Test in browser** - visit all main pages
2. **Fix any visual issues** - adjust grid or template
3. **Migrate remaining templates** as needed
4. **Clean up old layout-flex.css** once confident
5. **Update ROADMAP** - mark "Improved Layout System" as done

---

## 💡 Benefits of New Structure

1. **Semantic HTML** - proper document outline
2. **Accessibility** - screen readers can navigate better
3. **Grid simplicity** - no more `row-span-10` hacks
4. **Future-proof** - easier to add new layouts
5. **Clean separation** - header/main/sidebars are siblings

---

## 🐛 Known Issues

None yet - this is fresh code! Report any issues you find.

---

## 📚 Resources

- Grid template areas: https://developer.mozilla.org/en-US/docs/Web/CSS/grid-template-areas
- Semantic HTML5: https://developer.mozilla.org/en-US/docs/Glossary/Semantics#semantics_in_html
- Container queries: https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Container_Queries
