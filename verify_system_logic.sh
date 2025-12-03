#!/bin/bash

echo "=============================================="
echo "System Logic Finalization Verification"
echo "=============================================="
echo ""

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

PASS=0
FAIL=0

# Test 1: Check LoginSuccessHandler has role-based redirect
echo "Test 1: Checking role-based login redirect..."
if grep -q "ROLE_ADMIN" src/Security/LoginSuccessHandler.php && \
   grep -q "admin_dashboard" src/Security/LoginSuccessHandler.php; then
    echo -e "${GREEN}✓ PASS${NC} - LoginSuccessHandler has ROLE_ADMIN redirect to admin_dashboard"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - LoginSuccessHandler missing role-based redirect"
    ((FAIL++))
fi

# Test 2: Check email handler has "Booking Refused" logic
echo ""
echo "Test 2: Checking 'Booking Refused' email logic..."
if grep -q "Booking Refused" src/MessageHandler/BookingStatusChangeEmailHandler.php && \
   grep -q "STATUS_PENDING" src/MessageHandler/BookingStatusChangeEmailHandler.php; then
    echo -e "${GREEN}✓ PASS${NC} - Email handler has 'Booking Refused' for pending→cancelled"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - Email handler missing 'Booking Refused' logic"
    ((FAIL++))
fi

# Test 3: Check BookingController sets status to PENDING
echo ""
echo "Test 3: Checking bookings start as PENDING..."
if grep -q "STATUS_PENDING" src/Controller/BookingController.php && \
   grep -q "awaiting admin confirmation" src/Controller/BookingController.php; then
    echo -e "${GREEN}✓ PASS${NC} - BookingController sets initial status to PENDING"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - BookingController not setting status to PENDING"
    ((FAIL++))
fi

# Test 4: Check AdminController has GoogleCalendarService injected
echo ""
echo "Test 4: Checking AdminController has GoogleCalendarService..."
if grep -q "GoogleCalendarService" src/Controller/AdminController.php; then
    echo -e "${GREEN}✓ PASS${NC} - AdminController has GoogleCalendarService injected"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - AdminController missing GoogleCalendarService"
    ((FAIL++))
fi

# Test 5: Check AdminController syncs to Google Calendar on confirmation
echo ""
echo "Test 5: Checking Google Calendar sync on confirmation..."
if grep -q "STATUS_CONFIRMED && !.*getGoogleCalendarEventId" src/Controller/AdminController.php && \
   grep -q "createEvent" src/Controller/AdminController.php; then
    echo -e "${GREEN}✓ PASS${NC} - AdminController syncs to Google Calendar on confirmation"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - AdminController missing Google Calendar sync logic"
    ((FAIL++))
fi

# Test 6: Check AdminController deletes from Google Calendar on cancellation
echo ""
echo "Test 6: Checking Google Calendar deletion on cancellation..."
if grep -q "STATUS_CANCELLED && .*getGoogleCalendarEventId" src/Controller/AdminController.php && \
   grep -q "deleteEvent" src/Controller/AdminController.php; then
    echo -e "${GREEN}✓ PASS${NC} - AdminController deletes from Google Calendar on cancellation"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - AdminController missing Google Calendar deletion logic"
    ((FAIL++))
fi

# Test 7: Verify documentation exists
echo ""
echo "Test 7: Checking documentation exists..."
if [ -f "SYSTEM_LOGIC_FINALIZATION.md" ]; then
    SIZE=$(ls -lh SYSTEM_LOGIC_FINALIZATION.md | awk '{print $5}')
    echo -e "${GREEN}✓ PASS${NC} - Documentation exists (${SIZE})"
    ((PASS++))
else
    echo -e "${RED}✗ FAIL${NC} - Documentation file not found"
    ((FAIL++))
fi

# Summary
echo ""
echo "=============================================="
if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}ALL TESTS PASSED! ($PASS/$((PASS+FAIL)))${NC}"
else
    echo -e "${RED}SOME TESTS FAILED ($FAIL failed, $PASS passed)${NC}"
fi
echo "=============================================="
echo ""

if [ $FAIL -eq 0 ]; then
    echo "Next Steps:"
    echo "1. Clear cache: php bin/console cache:clear"
    echo "2. Grant admin role: mysql -u user -p database < grant_admin_access.sql"
    echo "3. Test admin login → Should redirect to /admin/dashboard"
    echo "4. Test regular user login → Should redirect to /booking"
    echo "5. Create booking → Should be PENDING, not synced to calendar"
    echo "6. Admin confirms → Should sync to Google Calendar"
    echo "7. Admin cancels pending → Client receives 'Booking Refused' email"
    echo "8. Admin cancels confirmed → Event removed from calendar"
    echo ""
    echo "Documentation: SYSTEM_LOGIC_FINALIZATION.md"
    echo ""
fi

exit $FAIL
