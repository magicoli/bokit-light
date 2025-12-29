# Grid Layout - Status & TODO

**Date:** 2025-12-28 late night
**Branch:** grid-layout
**Status:** ✅ Core structure implemented, ready for testing

---

## ✅ What I Did While You Slept

### 1. Core Grid Layout
- ✅ Created semantic HTML structure in `app.blade.php`
- ✅ Implemented grid-template-areas in `layout-grid.css`
- ✅ Cleaned obsolete rules from `app.css`
- ✅ Build passes with no errors

### 2. Structure Changes
```
OLD:                          NEW:
<nav>                         <nav>
<div#content-wrapper>    →    <header> (page-level)
  <main>                      <main> (content only)
    <header> (title)          <aside#sidebar-left>
    <content>                 <aside#sidebar-right>
  <aside.sidebar-left>        <footer>
  <aside.sidebar-right>
<footer>
```

### 3. Key Benefits
- Semantic HTML (proper h1 placement)
- Grid-template-areas (readable, maintainable)
- No more row-span-10 hack
- Better accessibility
- Future-proof

### 4. Documentation
- Created `dev/GRID-LAYOUT-MIGRATION.md` (complete guide)
- Includes testing checklist
- Rollback instructions included

---

## 🧪 IMMEDIATE TESTING NEEDED

**Before anything else, test these pages:**

1. **Dashboard** (`/`)
   - [x] Page loads
   - [x] (needed adjustments) Layout looks correct
   - [x] (needed adjustments) Sidebars display properly

>   Note: the :not(:empty) condition does not work, as the element has to be totally empty, not even spaces, which is tricky with tailwind and an IDE that automatically reorganizes code. I used :has(*) and :has(:not(*)) instead.

>   Note: the header was not placed correctly. It has to be in the main column above the main content. Which breaks the layout on 2 column views as header and sl are on the same row, followed by sr and main, so the header has the same height as the left sidebar.

>   Note: the initial 3, 4, 5 columns system was designed for flex, to allow larger and narrower columns. Grid does not need this, as it can handle different column widths natively. So there is only 
    - 1 column (mobile), everything stacked in original html order.
    - 2 columns (tablet, small screen): one sidebar on the left or 2 sidebars in a row below main
    - 3 columns (big screen): 2 sidebars

>   Note: the whole page should not scroll nor have vertical scrollbar. Only  the sidebars and the main column should be scrollable.

2. **Properties** (`/properties`)
   - [x] List view works
   - [-] (broken layout) Show view works
   - [-] No layout issues
   
>   Note: the show view seems to add additional sidebars, which is probably only a margin due to max width set somewhere.

3. **Rates** (`/rates`, `/rates/calculator`)
   - [x] Rates management works
   - [x] Calculator widget displays
   - [x] Form submission still works
   
>   Note: except for the header issue, everything works fine.

4. **Calendar** (`/calendar`)
   - [-] (not full width) Full-width layout (no sidebars)
   - [x] Calendar displays correctly
   
>   Note: no sidebars but not full width, and margins are not even the same width.

5. **Mobile/Responsive**
   - [x] Hamburger menu works
   - [x] (*) Sidebars stack on mobile
   - [x] No horizontal scroll
   
>   Note: oooh, we definitely need to add an anchor to the form action, it comes back to the top even when called from a form at the bottom of the page. This has to be done in Form class, it is a generic issue that could affect any future form.

---

## ⚠️ Potential Issues to Watch

### 1. Templates with Custom Headers
Some templates might have `<header>` tags inside content. These will render TWICE now (page header + content header). 

**Fix:** Remove content headers or use `@section('header')` override

>   Note: The occasionnal content header is needed for special titles like on the calendar page (not displayed the standard way for readability and layout  optimization). The template has to "declare" it shows its own title so the main layout can hide it, 
    - either with the older method but without adding <header> in the content, to use has(main #content h1.page-title) header{ @apply hidden }
    - either by declaring a variable from the template and check it from the layout (cleaner, but in this case, the header would be missing for accessibility)
    - anyway, we might need both. Some page won't include a title in the content, but won't either want the main title to be displayed (e.g. home page, a map page, a promo page...). They would have a technical title (for <head><title>) but it shouldn't be displayed.

TL;DR: 3 use cases:
    - Standard: template sets the title, layout displays it.
    - Calendar-like: templates includes the title in a non-standard way, sets the title for layout but layout only uses it for <head>
    - Home-like: neither template or layout show the title, it's set in template only for <head> and does not appear anywhere on the page

### 2. Sidebar References
Any CSS/JS using `.sidebar` class won't work. 

**Fix:** Change to `#sidebar-left` or `#sidebar-right`

>   I added back the .sidebar class in the layout, to allow simpler rules applying to any sidebar.

### 3. Content Wrapper
Any code checking for `#content-wrapper` will fail.

**Fix:** Update selectors to use `#page-layout` or main/header/aside directly

>   maybe `#page-layout` could be called `#wrapper` or `#page-wrapper`, isn't it more common?

---

## 🔄 Quick Rollback (If Needed)

If you find major issues:

```bash
# In app.blade.php, line 40, change:
@vite('resources/css/layout-grid.css')
# to:
@vite('resources/css/layout-flex.css')

# Then rebuild
npm run build
```

Or full rollback:
```bash
git checkout master -- resources/views/layouts/app.blade.php
npm run build
```

---

## 📋 Next Steps (Your Choice)

### Option A: Test & Refine (Recommended)
1. Test all pages listed above
2. Fix any visual issues
3. Document what works/breaks
4. Decide: merge or iterate

### Option B: Merge to Master
If testing looks good:
```bash
git checkout master
git merge grid-layout
npm run build
```

### Option C: Keep Iterating
Stay on grid-layout branch and refine:
- Migrate specific templates
- Add navigation improvements
- Clean up more CSS

### Option D: Park It for Later
```bash
git checkout master
# grid-layout branch stays for later
```

---

## 💡 My Recommendation

**Test first thing tomorrow:**
1. Load homepage - does it look OK?
2. Load properties - any weird layout?
3. Load rates calculator - widget still works?
4. Check mobile view

**If all 4 pass:** It's probably safe to merge or continue

**If issues found:** Check GRID-LAYOUT-MIGRATION.md for solutions

---

## 🎯 Long-Term Vision

This sets you up for:
- Clean admin menu in sidebar-left (collapsible)
- Better breadcrumbs in header
- Easier theme/layout switching
- Proper semantic structure for SEO

The foundation is solid. Now it's about refinement.

---

## 📞 Questions?

Check `dev/GRID-LAYOUT-MIGRATION.md` for:
- Full structure explanation
- Migration patterns
- CSS responsiveness details
- Troubleshooting guide

---

**Status:** Ready for your review ☕

**Commit:** `46b00b5 - refactor(layout): implement semantic grid-based layout`

**Have fun testing! 🚀**
