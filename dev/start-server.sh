#!/bin/bash

echo "🏖️  Starting Bokit development server..."
echo ""

# Get local IP address
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    LOCAL_IP=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "localhost")
else
    # Linux
    LOCAL_IP=$(hostname -I | awk '{print $1}')
fi

echo "Server will be available at:"
echo "  Local:   http://localhost:8000"
echo "  Network: http://${LOCAL_IP}:8000"
echo ""
echo "Use the Network URL to access from your phone/tablet"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

which symfony >/dev/null \
&& symfony serve --allow-all-ip \
|| php artisan serve --host=0.0.0.0
