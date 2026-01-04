# Debug System Usage

## Overview

The debug system allows adding detailed debug information that appears in a dedicated section when `APP_DEBUG=true`. All debug info is added to the same Blade section `debug-info`.

## Usage from PHP

Use the `debug_info()` helper function to append debug content to the `debug-info` section:

```php
debug_info(
    string $title,      // Section title
    mixed $content,     // Content to display (will be dumped if not string)
    string $type = 'info'  // CSS class: 'info', 'warning', 'error'
);
```

### Examples

```php
// Simple string
debug_info('Current User', Auth::user()->name);

// Array/object will be var_export'ed
debug_info('Request Data', $request->all(), 'info');

// Error with full details
debug_info(
    'Database Query Failed',
    [
        'query' => $query,
        'bindings' => $bindings,
        'error' => $e->getMessage(),
    ],
    'error'
);
```

## Usage from Blade

Use the `@section('debug-info')` directive as usual:

```blade
@section('debug-info')
    <h4>Page Specific Debug</h4>
    <pre>{{ var_export($data, true) }}</pre>
@endsection
```

## Combining PHP and Blade

The beauty of this system is that both `debug_info()` and `@section()` append to the **same** `debug-info` section:

```php
// In controller or model
debug_info('Database Query', $query, 'info');
```

```blade
@section('debug-info')
    <h4>View Variables</h4>
    <pre>{{ var_export($myData, true) }}</pre>
@endsection
```

Both will appear together in the same debug section!

## Display

All debug info appears at the bottom of the page in `layouts/app.blade.php`:

```blade
@if(config('app.debug') == true)
    @hasSection('debug-info')
        <div class="debug-info">
            <h3>Debug Information</h3>
            @yield('debug-info')
        </div>
    @endif
@endif
```

## Implementation

### Helper Function

Located in `app/Support/helpers.php`:

```php
function debug_info(string $title, $content, string $type = 'info'): void
{
    if (!config('app.debug')) {
        return; // Do nothing if debug is off
    }

    // Format the debug content
    $html = '<div class="debug-section debug-' . $type . '">';
    $html .= '<h4>' . htmlspecialchars($title) . '</h4>';
    
    if (is_string($content)) {
        $html .= '<pre>' . htmlspecialchars($content) . '</pre>';
    } else {
        $html .= '<pre>' . htmlspecialchars(var_export($content, true)) . '</pre>';
    }
    
    $html .= '</div>';

    // Append to the debug-info Blade section
    View::appendSection('debug-info', $html);
}
```

The key is `View::appendSection('debug-info', $html)` which appends to the existing Blade section.

### Form Integration

Forms automatically add debug info on errors:

```php
// In Form::renderField() catch block
catch (\Throwable $e) {
    debug_info(
        "Field Rendering Error: {$fieldName}",
        [
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'field' => $field,
            'trace' => $e->getTraceAsString(),
        ],
        'error'
    );
    
    // User sees simple message
    return '<div class="field-error">Error rendering field</div>';
}
```

## Benefits

- **Single section**: Everything goes to `@yield('debug-info')` - one place to look
- **Fast debugging**: No need to parse log files
- **Context-aware**: Shows exactly what happened where
- **User-friendly**: Simple message for users, full details in debug section
- **Automatic cleanup**: Only appears when `APP_DEBUG=true`
- **Flexible**: Use `debug_info()` from PHP or `@section()` from Blade, or both!

## Where Debug Info Appears

Simply use `@yield('debug-info')` anywhere in your layout where you want debug info to appear. Currently it's at the bottom of `layouts/app.blade.php`, but you can move it anywhere.
