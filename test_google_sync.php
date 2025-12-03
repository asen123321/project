#!/usr/bin/env php
<?php

/**
 * Google Calendar API Test Script
 *
 * This standalone script tests Google Calendar integration
 * to help diagnose sync issues.
 */

echo "==============================================\n";
echo "Google Calendar API Test Script\n";
echo "==============================================\n\n";

// Step 1: Load Composer autoloader
echo "Step 1: Loading Composer autoloader...\n";

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ ERROR: Composer autoloader not found!\n";
    echo "   Path checked: " . __DIR__ . "/vendor/autoload.php\n";
    echo "   Run: composer install\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

echo "   ✅ Composer autoloader loaded\n\n";

// Step 2: Check for credentials file
echo "Step 2: Checking for Google Calendar credentials...\n";

$credentialsPath = __DIR__ . '/google-calendar.json';

echo "   Looking for: $credentialsPath\n";

if (!file_exists($credentialsPath)) {
    echo "   ❌ ERROR: Credentials file not found!\n";
    echo "   \n";
    echo "   Please ensure google-calendar.json exists in:\n";
    echo "   " . __DIR__ . "/google-calendar.json\n";
    echo "   \n";
    echo "   To create this file:\n";
    echo "   1. Go to Google Cloud Console\n";
    echo "   2. Create a Service Account\n";
    echo "   3. Download the JSON key\n";
    echo "   4. Save it as google-calendar.json in the project root\n";
    exit(1);
}

echo "   ✅ Credentials file found\n";

// Step 3: Validate JSON
echo "\nStep 3: Validating credentials JSON...\n";

$credentialsContent = file_get_contents($credentialsPath);
$credentialsData = json_decode($credentialsContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "   ❌ ERROR: Invalid JSON in credentials file\n";
    echo "   Error: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "   ✅ JSON is valid\n";

// Step 4: Check required fields
echo "\nStep 4: Checking required fields in credentials...\n";

$requiredFields = [
    'type',
    'project_id',
    'private_key_id',
    'private_key',
    'client_email',
    'client_id'
];

$missingFields = [];
foreach ($requiredFields as $field) {
    if (!isset($credentialsData[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    echo "   ❌ ERROR: Missing required fields in credentials:\n";
    foreach ($missingFields as $field) {
        echo "      - $field\n";
    }
    exit(1);
}

echo "   ✅ All required fields present\n";
echo "   📧 Service Account Email: " . $credentialsData['client_email'] . "\n";
echo "   📁 Project ID: " . $credentialsData['project_id'] . "\n";

// Step 5: Initialize Google Client
echo "\nStep 5: Initializing Google Client...\n";

try {
    $client = new Google\Client();
    $client->setApplicationName('Hair Salon Booking System - Test');
    $client->setScopes([Google\Service\Calendar::CALENDAR]);
    $client->setAuthConfig($credentialsPath);

    echo "   ✅ Google Client initialized\n";
} catch (Exception $e) {
    echo "   ❌ ERROR: Failed to initialize Google Client\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Class: " . get_class($e) . "\n";
    exit(1);
}

// Step 6: Initialize Calendar Service
echo "\nStep 6: Initializing Calendar Service...\n";

try {
    $calendarService = new Google\Service\Calendar($client);
    echo "   ✅ Calendar Service initialized\n";
} catch (Exception $e) {
    echo "   ❌ ERROR: Failed to initialize Calendar Service\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 7: Determine target calendar
echo "\nStep 7: Determining target calendar...\n";

// Option 1: Use asem4o@gmail.com if it's shared with service account
// Option 2: Use service account's own calendar
// Option 3: Use 'primary' (service account's primary calendar)

$targetEmail = 'asem4o@gmail.com';
$calendarId = $targetEmail; // Try user's calendar first

echo "   Target user email: $targetEmail\n";
echo "   Trying calendar ID: $calendarId\n";

// Step 8: Test Calendar Access
echo "\nStep 8: Testing calendar access...\n";

try {
    echo "   Attempting to retrieve calendar...\n";
    $calendar = $calendarService->calendars->get($calendarId);
    echo "   ✅ Calendar found!\n";
    echo "   📅 Calendar Name: " . $calendar->getSummary() . "\n";
    echo "   🆔 Calendar ID: " . $calendar->getId() . "\n";
} catch (Google\Service\Exception $e) {
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();

    echo "   ⚠️  Could not access user calendar ($targetEmail)\n";
    echo "   Error Code: $errorCode\n";
    echo "   Error: $errorMessage\n";

    if ($errorCode == 404) {
        echo "\n   ℹ️  This is expected if the calendar hasn't been shared with the service account.\n";
    } elseif ($errorCode == 403) {
        echo "\n   ℹ️  Permission denied - calendar not shared with service account.\n";
    }

    echo "\n   Falling back to service account's own calendar...\n";
    $calendarId = 'primary'; // Use service account's calendar

    try {
        $calendar = $calendarService->calendars->get($calendarId);
        echo "   ✅ Using service account's primary calendar\n";
        echo "   📅 Calendar: " . $calendar->getSummary() . "\n";
    } catch (Exception $e2) {
        echo "   ❌ ERROR: Cannot access any calendar\n";
        echo "   Error: " . $e2->getMessage() . "\n";
        exit(1);
    }
}

// Step 9: Create Test Event
echo "\nStep 9: Creating test event...\n";

$testEventStart = new DateTime('+1 hour');
$testEventEnd = new DateTime('+2 hours');

echo "   Event Details:\n";
echo "   - Summary: [TEST] Hair Salon Booking System Test Event\n";
echo "   - Start: " . $testEventStart->format('Y-m-d H:i:s') . "\n";
echo "   - End: " . $testEventEnd->format('Y-m-d H:i:s') . "\n";
echo "   - Calendar: $calendarId\n";
echo "   - Note: Service accounts cannot invite attendees on personal Gmail\n";

$event = new Google\Service\Calendar\Event([
    'summary' => '[TEST] Hair Salon Booking System Test Event',
    'description' => "This is a test event created by the Google Calendar sync test script.\n\n" .
                     "Service Account: " . $credentialsData['client_email'] . "\n" .
                     "Created: " . date('Y-m-d H:i:s') . "\n" .
                     "Calendar Owner: $targetEmail\n\n" .
                     "If you can see this event, Google Calendar integration is working!",
    'start' => [
        'dateTime' => $testEventStart->format('c'),
        'timeZone' => date_default_timezone_get(),
    ],
    'end' => [
        'dateTime' => $testEventEnd->format('c'),
        'timeZone' => date_default_timezone_get(),
    ],
    'reminders' => [
        'useDefault' => false,
        'overrides' => [
            ['method' => 'email', 'minutes' => 60],
            ['method' => 'popup', 'minutes' => 30],
        ],
    ],
]);

try {
    echo "\n   Sending event to Google Calendar API...\n";
    $createdEvent = $calendarService->events->insert($calendarId, $event);

    echo "\n   ✅ SUCCESS! Test event created!\n";
    echo "\n";
    echo "   ==========================================\n";
    echo "   EVENT DETAILS\n";
    echo "   ==========================================\n";
    echo "   Event ID: " . $createdEvent->getId() . "\n";
    echo "   Summary: " . $createdEvent->getSummary() . "\n";
    echo "   Status: " . $createdEvent->getStatus() . "\n";
    echo "   Start: " . $createdEvent->getStart()->getDateTime() . "\n";
    echo "   End: " . $createdEvent->getEnd()->getDateTime() . "\n";
    echo "   Link: " . $createdEvent->getHtmlLink() . "\n";
    echo "   ==========================================\n";

} catch (Google\Service\Exception $e) {
    echo "\n   ❌ ERROR: Failed to create event\n";
    echo "   HTTP Code: " . $e->getCode() . "\n";
    echo "   Error Message: " . $e->getMessage() . "\n";
    echo "\n";
    echo "   Full Error Details:\n";
    echo "   " . str_repeat("-", 50) . "\n";

    $errors = $e->getErrors();
    if ($errors) {
        foreach ($errors as $error) {
            echo "   Domain: " . ($error['domain'] ?? 'N/A') . "\n";
            echo "   Reason: " . ($error['reason'] ?? 'N/A') . "\n";
            echo "   Message: " . ($error['message'] ?? 'N/A') . "\n";
            echo "\n";
        }
    }

    echo "   Raw Response:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   " . str_repeat("-", 50) . "\n";

    exit(1);
} catch (Exception $e) {
    echo "\n   ❌ ERROR: Unexpected exception\n";
    echo "   Class: " . get_class($e) . "\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    exit(1);
}

// Step 10: Verify Event Exists
echo "\nStep 10: Verifying event exists...\n";

try {
    $retrievedEvent = $calendarService->events->get($calendarId, $createdEvent->getId());
    echo "   ✅ Event successfully retrieved from calendar\n";
    echo "   Event ID matches: " . ($retrievedEvent->getId() === $createdEvent->getId() ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "   ⚠️  Warning: Could not retrieve event\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

// Step 11: List Recent Events
echo "\nStep 11: Listing recent events on calendar...\n";

try {
    $optParams = [
        'maxResults' => 5,
        'orderBy' => 'startTime',
        'singleEvents' => true,
        'timeMin' => date('c'),
    ];

    $results = $calendarService->events->listEvents($calendarId, $optParams);
    $events = $results->getItems();

    if (empty($events)) {
        echo "   No upcoming events found.\n";
    } else {
        echo "   Found " . count($events) . " upcoming event(s):\n";
        foreach ($events as $event) {
            $start = $event->getStart()->getDateTime();
            if (empty($start)) {
                $start = $event->getStart()->getDate();
            }
            echo "   - " . $event->getSummary() . " (" . $start . ")\n";
        }
    }
} catch (Exception $e) {
    echo "   ⚠️  Could not list events\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

// Step 12: Summary
echo "\n==============================================\n";
echo "TEST SUMMARY\n";
echo "==============================================\n\n";

echo "✅ Google Calendar API is working!\n\n";

echo "What this means:\n";
echo "- Google Calendar credentials are valid ✅\n";
echo "- API authentication is working ✅\n";
echo "- Events can be created successfully ✅\n";
echo "- Calendar: $calendarId ✅\n\n";

echo "Next Steps:\n";
echo "1. Check your Google Calendar at:\n";
echo "   https://calendar.google.com\n\n";

echo "2. You should see the test event:\n";
echo "   '[TEST] Hair Salon Booking System Test Event'\n\n";

echo "3. If using asem4o@gmail.com calendar:\n";
echo "   - The calendar must be shared with the service account:\n";
echo "   - " . $credentialsData['client_email'] . "\n";
echo "   - With 'Make changes to events' permission\n\n";

echo "4. Update your Symfony .env file:\n";
echo "   GOOGLE_CALENDAR_CREDENTIALS_PATH=" . $credentialsPath . "\n";
echo "   GOOGLE_CALENDAR_ID=$calendarId\n\n";

echo "5. If you want to delete the test event:\n";
echo "   Event ID: " . $createdEvent->getId() . "\n";
echo "   Or manually delete it from Google Calendar\n\n";

echo "==============================================\n";
echo "Test completed successfully! 🎉\n";
echo "==============================================\n";

exit(0);
