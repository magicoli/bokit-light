# Theme Color Update

## Change Summary

Updated the PWA theme color to match the bottom edge of the Bokit logo.

## Colors

### Logo Gradient
- **Top edge** (lighter): `#FEF4DC` 
- **Bottom edge** (darker): `#FDD389` ✅ **Used for theme**

### Previous Theme
- Old theme color: `#71b6ad` (teal/green)

### New Theme
- New theme color: `#FDD389` (warm orange/yellow from logo bottom)

## Files Modified

1. **public/manifest.json**
   - Changed `theme_color` from `#71b6ad` to `#FDD389`
   - This affects the app color when installed as PWA

2. **resources/views/layouts/app.blade.php**
   - Changed `<meta name="theme-color">` from `#71b6ad` to `#FDD389`
   - This affects the browser toolbar/status bar color

## Platform Effects

### Android
- Browser address bar color
- Task switcher card color
- PWA splash screen color

### iOS
- Status bar tint (when using compatible mode)
- Safari toolbar color

### Desktop PWA
- Window title bar color (on supported platforms)

## Color Extraction

The color was extracted using ImageMagick from the bottom edge of the logo:

```bash
convert resources/images/bokit-logo-plain.png -crop 1x1+875+1700 -depth 8 txt:- | grep -o '#[0-9A-F]{6}'
# Result: #FDD389
```

This ensures perfect color matching with the branding.
