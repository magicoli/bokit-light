# Progressive Web App (PWA) Implementation

## Overview

Bokit is now a Progressive Web App, allowing users to install it as a native application on any platform (Windows, Mac, Linux, Android, iOS).

## Features

### Current Implementation (Basic PWA)

✅ **Installable**: Users can install the app from their browser
✅ **Offline Assets**: Static assets (CSS, JS, images, CDN) are cached for offline use
✅ **Native-like Experience**: App runs in standalone window without browser UI
✅ **Cross-platform**: Works on desktop and mobile devices

### Not Yet Implemented

❌ **Offline Data Sync**: Calendar and booking data not available offline
❌ **Background Sync**: Changes made offline are not queued for sync
❌ **Push Notifications**: No notification support yet

## Files

- `public/manifest.json`: App metadata (name, icons, colors, display mode)
- `public/sw.js`: Service Worker for caching strategy
- `public/images/icons/`: App icons in various sizes
- `resources/views/layouts/app.blade.php`: PWA meta tags and SW registration

## Caching Strategy

### Static Assets (cache-first)
- Images, fonts, CSS, JS from CDN
- Served from cache when available
- Updates cached in background

### Dynamic Content (network-first)
- HTML pages, API responses
- Always fetches from network
- Falls back to cache if offline

## Installation

### Desktop (Chrome, Edge, Safari)
1. Visit the app in your browser
2. Look for the install icon in the address bar (⊕ or install prompt)
3. Click "Install" or "Add to Dock/Desktop"

### Mobile (Android/iOS)
1. Visit the app in Safari (iOS) or Chrome (Android)
2. Tap the share button
3. Select "Add to Home Screen"

## Cache Management

The Service Worker uses versioned caching (`bokit-v1`). When you update the SW:
1. Increment the cache version (e.g., `bokit-v2`)
2. Old caches are automatically deleted on activation

## Updating the PWA

When you deploy updates:
1. Users will continue using cached version
2. SW updates in background
3. On next visit, new version is active
4. Hard refresh (Cmd+Shift+R / Ctrl+Shift+F5) forces update

## Theme Color

The app uses `#71b6ad` (turquoise from the logo) as the theme color for:
- Browser address bar tinting
- Splash screen background
- Task switcher color (mobile)

## Future Enhancements

When ready for offline data support, we'll need to:
1. Create API endpoints for data sync
2. Implement IndexedDB for local storage
3. Add conflict resolution logic
4. Queue mutations for background sync
5. Add online/offline UI indicators

## Debugging

- Chrome DevTools > Application > Service Workers
- Chrome DevTools > Application > Manifest
- Chrome DevTools > Application > Cache Storage
- Safari > Develop > Service Workers

## Browser Support

- ✅ Chrome/Edge (full support)
- ✅ Safari (macOS Sonoma+, iOS 16.4+)
- ✅ Firefox (basic support, no install prompt)
- ⚠️ Safari (older versions): limited PWA support
