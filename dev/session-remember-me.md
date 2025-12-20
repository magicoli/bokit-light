# Session & Remember Me - Configuration

## Overview

The application uses a two-tier authentication persistence system designed for daily use while maintaining security.

## Session Duration

**Default session lifetime: 24 hours**
- Configured in `config/session.php`
- Session expires after 24 hours of inactivity
- Perfect for users who keep the application open during work hours

## "Remember Me" Feature

**Initial duration: 7 days**
**Auto-renewal: Yes**

### How it works:

1. **At Login** (`routes/web.php`):
   - User checks "Keep me logged in" / "Rester connecté"
   - System creates a remember me cookie valid for 7 days
   - Cookie contains: user ID + remember token + password hash

2. **On Each Visit** (`RenewRememberToken` middleware):
   - If user arrives via remember me cookie (not regular session)
   - System automatically renews the cookie for another 7 days
   - **Result: Users stay logged in indefinitely as long as they visit regularly**

3. **Security**:
   - If user doesn't visit for 7 days → automatically logged out
   - Much safer than a fixed 30-day or longer cookie
   - Balances convenience (daily users never logged out) with security (inactive sessions expire)

## Benefits

✅ **Daily users**: Never need to log in again (cookie renews automatically)
✅ **Security**: Inactive sessions expire after 7 days
✅ **Flexibility**: Users without "remember me" have 24h sessions
✅ **Clean**: Distracted users on public computers are logged out after 7 days of inactivity

## Implementation Files

- `config/session.php` - Session lifetime (24h)
- `routes/web.php` - Login logic with remember me (7 days initial)
- `app/Http/Middleware/RenewRememberToken.php` - Auto-renewal middleware
- `bootstrap/app.php` - Middleware registration
- `resources/views/auth/login.blade.php` - Login form with checkbox
- `lang/en/app.php` & `lang/fr/app.php` - Translations

## Customization

To change durations, edit:
- Session: `SESSION_LIFETIME` in `.env` (in minutes)
- Remember me: `$minutes` variable in `routes/web.php` (login) and `RenewRememberToken.php` (renewal)
