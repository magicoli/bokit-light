# Bokit Connector

WordPress authentication bridge for Bokit calendar application.

## Installation

1. Upload the `bokit-connector` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress

## Usage

The plugin provides a REST API endpoint for authentication:

**Endpoint:** `POST /wp-json/bokit/v1/auth`

**Parameters:**
- `username` (string, required) - WordPress username or email
- `password` (string, required) - WordPress password

**Success Response (200):**
```json
{
  "id": 123,
  "username": "magic",
  "name": "Olivier van Helden",
  "email": "olivier@van-helden.net",
  "roles": ["administrator", "bokit_manager"]
}
```

**Error Response (401):**
```json
{
  "code": "invalid_credentials",
  "message": "Invalid username or password",
  "data": {"status": 401}
}
```

## Future Development

This connector will evolve into a comprehensive companion plugin with:
- Booking management
- Calendar sync
- Property management
- Integration with external booking platforms

## Version

0.1.0 - Initial release (authentication endpoint only)
