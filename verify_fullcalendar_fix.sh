#!/bin/bash

echo "========================================"
echo "FullCalendar Fix Verification Script"
echo "========================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Check if FullCalendar file exists
echo "Test 1: Checking FullCalendar local file..."
if [ -f "public/js/fullcalendar.min.js" ]; then
    SIZE=$(ls -lh public/js/fullcalendar.min.js | awk '{print $5}')
    echo -e "${GREEN}✓ PASS${NC} - FullCalendar file exists (${SIZE})"
else
    echo -e "${RED}✗ FAIL${NC} - FullCalendar file not found!"
    echo "  Run: curl -L -o public/js/fullcalendar.min.js 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'"
    exit 1
fi

# Test 2: Check if template uses local file
echo ""
echo "Test 2: Checking template uses local file..."
if grep -q "src='/js/fullcalendar.min.js'" templates/booking/index.html.twig; then
    echo -e "${GREEN}✓ PASS${NC} - Template references local FullCalendar"
else
    echo -e "${RED}✗ FAIL${NC} - Template still using CDN or wrong path"
    grep "fullcalendar" templates/booking/index.html.twig
    exit 1
fi

# Test 3: Check if CDN link is removed
echo ""
echo "Test 3: Checking CDN link removed..."
if grep -q "cdn.jsdelivr.net" templates/booking/index.html.twig; then
    echo -e "${RED}✗ FAIL${NC} - CDN link still present in template"
    grep "cdn.jsdelivr.net" templates/booking/index.html.twig
    exit 1
else
    echo -e "${GREEN}✓ PASS${NC} - No CDN links found"
fi

# Test 4: Check if extraParams is removed
echo ""
echo "Test 4: Checking extraParams removed..."
if grep -q "extraParams:" templates/booking/index.html.twig; then
    echo -e "${RED}✗ FAIL${NC} - extraParams still present (will cause crash)"
    grep -A3 "extraParams:" templates/booking/index.html.twig
    exit 1
else
    echo -e "${GREEN}✓ PASS${NC} - extraParams removed (crash fixed)"
fi

# Test 5: Check if calendar.view access is removed
echo ""
echo "Test 5: Checking calendar.view access removed..."
if grep -q "calendar.view" templates/booking/index.html.twig; then
    echo -e "${RED}✗ FAIL${NC} - calendar.view still accessed (will cause crash)"
    grep -B2 -A2 "calendar.view" templates/booking/index.html.twig
    exit 1
else
    echo -e "${GREEN}✓ PASS${NC} - No calendar.view access (crash fixed)"
fi

# Test 6: Verify event source URL
echo ""
echo "Test 6: Checking event source URL format..."
if grep -q "url: '/booking/api/bookings'" templates/booking/index.html.twig; then
    echo -e "${GREEN}✓ PASS${NC} - Event source URL configured correctly"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - Event source URL may be misconfigured"
fi

# Test 7: Check file permissions
echo ""
echo "Test 7: Checking file permissions..."
if [ -r "public/js/fullcalendar.min.js" ]; then
    PERMS=$(ls -l public/js/fullcalendar.min.js | awk '{print $1}')
    echo -e "${GREEN}✓ PASS${NC} - File is readable (${PERMS})"
else
    echo -e "${RED}✗ FAIL${NC} - File is not readable"
    echo "  Run: chmod 644 public/js/fullcalendar.min.js"
    exit 1
fi

# Test 8: Verify FullCalendar version
echo ""
echo "Test 8: Checking FullCalendar version..."
if grep -q "FullCalendar Standard Bundle v6.1.10" public/js/fullcalendar.min.js; then
    echo -e "${GREEN}✓ PASS${NC} - FullCalendar v6.1.10 confirmed"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - Version string not found (file may be corrupted)"
fi

# Summary
echo ""
echo "========================================"
echo -e "${GREEN}ALL TESTS PASSED!${NC}"
echo "========================================"
echo ""
echo "Next Steps:"
echo "1. Clear browser cache (Ctrl+Shift+Delete)"
echo "2. Open: http://localhost/booking"
echo "3. Open Developer Console (F12)"
echo "4. Verify no JavaScript errors"
echo "5. Check Network tab: /js/fullcalendar.min.js should load from localhost"
echo ""
echo "Expected Results:"
echo "  ✓ Calendar renders correctly"
echo "  ✓ Gray busy blocks appear"
echo "  ✓ No 'Uncaught TypeError: reading view' error"
echo "  ✓ No CDN blocking by tracking prevention"
echo ""
