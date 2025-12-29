# Page Title Display - 3 Use Cases

## Implementation Strategy

Templates can control title display using `@section('title_display')`:

### Case 1: Standard (default)
**Use case:** Most pages  
**Behavior:** Layout displays title in header  
**Template:** Just set title
```blade
@section('title', 'Properties')
@section('subtitle', 'Manage your rental properties')
```

### Case 2: Custom Header
**Use case:** Calendar, special layouts  
**Behavior:** Template includes title in custom way, layout hides default header  
**Template:** Declare custom title display
```blade
@section('title', 'Calendar') {{-- For <head> only --}}
@section('title_display', 'custom')

@section('content')
    <div class="calendar-header">
        <h1 class="custom-title">December 2025</h1>
        ...
    </div>
@endsection
```

### Case 3: No Visual Title
**Use case:** Home page, promo pages, maps  
**Behavior:** Title only in `<head>`, not displayed anywhere  
**Template:** Declare no title display
```blade
@section('title', 'Welcome to Bokit') {{-- For <head> only --}}
@section('title_display', 'none')

@section('content')
    {{-- Visual content without title --}}
@endsection
```

## Layout Implementation

In `app.blade.php`, header becomes:

```blade
<header>
    @if(!View::hasSection('title_display') || View::getSection('title_display') === 'default')
        {{-- Case 1: Standard display --}}
        @hasSection('header')
            @yield('header')
        @else
            @hasSection('title')
                <h1>@yield('title')</h1>
            @endif
            @hasSection('subtitle')
                <p class="subtitle">@yield('subtitle')</p>
            @endif
        @endif
    @endif
    {{-- Cases 2 & 3: header is empty, will be hidden by :not(:has(*)) --}}
</header>
```

## CSS (Already Handles This)

```css
header {
    &:not(:has(*)) {
        @apply hidden;  /* Hides when empty (cases 2 & 3) */
    }
}
```

## Migration Examples

**Calendar page:**
```blade
@section('title', 'Calendar')
@section('title_display', 'custom')

@section('content')
    <div class="calendar-header">
        <h1>Your custom header</h1>
    </div>
@endsection
```

**Home page:**
```blade
@section('title', 'Bokit - Vacation Rental Management')
@section('title_display', 'none')

@section('content')
    <div class="hero">
        {{-- Hero section without title --}}
    </div>
@endsection
```

## Accessibility Notes

- `<title>` in `<head>` always present (SEO, screen readers)
- Visual `<h1>` placement controlled by template needs
- Empty `<header>` hidden automatically
- Custom headers maintain proper heading hierarchy

## Benefits

1. **Flexible:** Templates control their own title display
2. **Semantic:** Proper `<h1>` placement for accessibility
3. **Simple:** Default case (most pages) requires no changes
4. **Clean:** Empty headers hidden automatically by CSS
