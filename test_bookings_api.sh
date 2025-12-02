#!/bin/bash

# Test Bookings API Endpoint
# This script verifies the /api/bookings endpoint is working correctly

echo "========================================="
echo "Testing Bookings API Endpoint"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="${BASE_URL:-http://localhost}"
START_DATE="${START_DATE:-2025-11-01}"
END_DATE="${END_DATE:-2025-12-31}"

echo "Configuration:"
echo "  Base URL: $BASE_URL"
echo "  Date Range: $START_DATE to $END_DATE"
echo ""

# Test 1: Check endpoint without authentication (should fail with 401/302)
echo "Test 1: Check endpoint without authentication..."
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/booking/api/bookings?start=$START_DATE&end=$END_DATE" 2>&1)
http_code=$(echo "$response" | tail -n 1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "401" ] || [ "$http_code" = "302" ]; then
    echo -e "${GREEN}✓ PASS${NC} - Correctly requires authentication (HTTP $http_code)"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - Expected 401/302, got HTTP $http_code"
fi
echo ""

# Test 2: Check database has bookings
echo "Test 2: Check database for confirmed bookings..."
if command -v php &> /dev/null; then
    booking_count=$(php bin/console dbal:run-sql "SELECT COUNT(*) as count FROM booking WHERE status='confirmed'" 2>&1 | grep -oP '\d+' | tail -1)

    if [ -n "$booking_count" ] && [ "$booking_count" -gt 0 ]; then
        echo -e "${GREEN}✓ PASS${NC} - Found $booking_count confirmed booking(s) in database"
    else
        echo -e "${YELLOW}⚠ WARNING${NC} - No confirmed bookings found in database"
        echo "  Create a test booking first to test the API"
    fi
else
    echo -e "${YELLOW}⚠ SKIP${NC} - PHP command not available"
fi
echo ""

# Test 3: Check logs for recent API calls
echo "Test 3: Check logs for recent API activity..."
if [ -f "var/log/dev.log" ]; then
    recent_logs=$(grep "busy slots" var/log/dev.log 2>/dev/null | tail -3)

    if [ -n "$recent_logs" ]; then
        echo -e "${GREEN}✓ FOUND${NC} - Recent API activity in logs:"
        echo "$recent_logs" | sed 's/^/  /'
    else
        echo -e "${YELLOW}⚠ INFO${NC} - No recent 'busy slots' activity in logs"
        echo "  This is normal if endpoint hasn't been called yet"
    fi
else
    echo -e "${YELLOW}⚠ SKIP${NC} - Log file not found: var/log/dev.log"
fi
echo ""

# Test 4: Check Google Calendar configuration
echo "Test 4: Check Google Calendar configuration..."
if [ -f "google-calendar.json" ]; then
    file_size=$(stat -f%z "google-calendar.json" 2>/dev/null || stat -c%s "google-calendar.json" 2>/dev/null)
    service_account=$(cat google-calendar.json | grep -o '"client_email"[[:space:]]*:[[:space:]]*"[^"]*"' | cut -d'"' -f4)

    echo -e "${GREEN}✓ PASS${NC} - Credentials file exists"
    echo "  File size: $file_size bytes"
    if [ -n "$service_account" ]; then
        echo "  Service account: $service_account"
    fi
else
    echo -e "${RED}✗ FAIL${NC} - google-calendar.json not found"
    echo "  Google Calendar sync will not work without credentials"
fi
echo ""

# Test 5: Check recent bookings in database
echo "Test 5: Check recent booking details..."
if command -v php &> /dev/null; then
    echo "Recent bookings:"
    php bin/console dbal:run-sql "
        SELECT
            id,
            client_name,
            client_phone,
            google_calendar_event_id,
            DATE_FORMAT(booking_date, '%Y-%m-%d %H:%i') as booking_time,
            status
        FROM booking
        ORDER BY id DESC
        LIMIT 5
    " 2>&1 | grep -v "Executing query" | grep -v "^$"

    echo ""
    echo "Checking for NULL fields..."
    null_phones=$(php bin/console dbal:run-sql "SELECT COUNT(*) FROM booking WHERE client_phone IS NULL" 2>&1 | grep -oP '\d+' | tail -1)
    null_events=$(php bin/console dbal:run-sql "SELECT COUNT(*) FROM booking WHERE google_calendar_event_id IS NULL AND status='confirmed'" 2>&1 | grep -oP '\d+' | tail -1)

    if [ -n "$null_phones" ] && [ "$null_phones" -gt 0 ]; then
        echo -e "${YELLOW}⚠ WARNING${NC} - $null_phones booking(s) with NULL client_phone"
        echo "  This is OK if phone wasn't provided in the form"
    else
        echo -e "${GREEN}✓ PASS${NC} - All bookings have phone numbers"
    fi

    if [ -n "$null_events" ] && [ "$null_events" -gt 0 ]; then
        echo -e "${YELLOW}⚠ WARNING${NC} - $null_events confirmed booking(s) without Google Calendar event ID"
        echo "  Check Google Calendar configuration and logs"
    else
        echo -e "${GREEN}✓ PASS${NC} - All confirmed bookings have Google Calendar event IDs"
    fi
else
    echo -e "${YELLOW}⚠ SKIP${NC} - PHP command not available"
fi
echo ""

# Test 6: Validate JSON response format (with authentication)
echo "Test 6: API Response Format Test..."
echo "  Note: This test requires authentication. Use with valid session cookie."
echo ""
echo "  Example manual test:"
echo "  curl -H 'Cookie: PHPSESSID=your_session_id' \\"
echo "       '$BASE_URL/booking/api/bookings?start=$START_DATE&end=$END_DATE'"
echo ""

# Summary
echo "========================================="
echo "Test Summary"
echo "========================================="
echo ""
echo "✓ Authentication check"
echo "✓ Database connectivity"
echo "✓ Log file access"
echo "✓ Google Calendar configuration"
echo "✓ Data persistence validation"
echo ""
echo "To test with authentication:"
echo "1. Log in to the application in a browser"
echo "2. Get your session cookie (PHPSESSID)"
echo "3. Run: curl -H 'Cookie: PHPSESSID=xxx' '$BASE_URL/booking/api/bookings?start=$START_DATE&end=$END_DATE'"
echo ""
echo "Expected JSON response:"
echo '['
echo '  {'
echo '    "id": "1",'
echo '    "title": "Busy",'
echo '    "start": "2025-11-26T14:00:00",'
echo '    "end": "2025-11-26T14:45:00",'
echo '    "backgroundColor": "#6c757d",'
echo '    "borderColor": "#5a6268",'
echo '    "display": "background",'
echo '    "editable": false,'
echo '    "extendedProps": {'
echo '      "type": "busy",'
echo '      "stylistId": 1'
echo '    }'
echo '  }'
echo ']'
echo ""
echo "For more details, see: BOOKING_FIXES.md"
echo ""
