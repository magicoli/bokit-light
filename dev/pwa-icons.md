# PWA Icons Update

## Overview

PWA icons have been updated to use the new logo with plain background (`bokit-logo-plain.png`).
The logo in the application navigation continues to use the transparent version.

## Generated Icons

All icons are generated from: `resources/images/bokit-logo-plain.png`

### PWA Icons (for web app installation)
Located in `public/images/icons/`:
- `icon-96x96.png` - Small Android icon
- `icon-144x144.png` - Medium Android icon
- `icon-192x192.png` - Standard Android icon (maskable)
- `icon-384x384.png` - Large Android icon
- `icon-512x512.png` - Extra large Android icon (maskable)
- `apple-touch-icon.png` (180x180) - iOS home screen icon

### Favicon Icons
Located in `public/`:
- `favicon.ico` (32x32) - Browser tab icon
- `favicon-16x16.png` - Small favicon
- `favicon-32x32.png` - Standard favicon

## Regeneration

To regenerate all icons from the source logo:

```bash
cd /path/to/bokit-light

# PWA icons
sips -z 96 96 resources/images/bokit-logo-plain.png --out public/images/icons/icon-96x96.png
sips -z 144 144 resources/images/bokit-logo-plain.png --out public/images/icons/icon-144x144.png
sips -z 192 192 resources/images/bokit-logo-plain.png --out public/images/icons/icon-192x192.png
sips -z 384 384 resources/images/bokit-logo-plain.png --out public/images/icons/icon-384x384.png
sips -z 512 512 resources/images/bokit-logo-plain.png --out public/images/icons/icon-512x512.png
sips -z 180 180 resources/images/bokit-logo-plain.png --out public/images/icons/apple-touch-icon.png

# Favicons
sips -z 16 16 resources/images/bokit-logo-plain.png --out public/favicon-16x16.png
sips -z 32 32 resources/images/bokit-logo-plain.png --out public/favicon-32x32.png
sips -z 32 32 resources/images/bokit-logo-plain.png --out public/favicon.ico
```

## Configuration Files

### manifest.json
Updated with all icon sizes for optimal PWA installation experience.

### app.blade.php
References:
- Apple touch icon: `/images/icons/apple-touch-icon.png`
- Web manifest: `/manifest.json`

### Logo Usage

- **Navigation logo** (transparent): `config('app.logo')` → `/images/logo.png`
  - Used in: `appLogoHtml()` helper
  
- **PWA icons** (plain background): From `bokit-logo-plain.png`
  - Used in: PWA installation, home screen, browser tabs

## Notes

- All icons use the plain background version for better visibility across different backgrounds
- The navigation logo uses the transparent version for seamless integration with the UI
- Icons are optimized for various display densities and platforms (Android, iOS, Desktop)
