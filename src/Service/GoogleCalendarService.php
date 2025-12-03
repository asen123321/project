<?php

namespace App\Service;

use App\Entity\Booking;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Psr\Log\LoggerInterface;

class GoogleCalendarService
{
    private ?GoogleClient $client = null;
    private ?Calendar $calendarService = null;

    public function __construct(
        private LoggerInterface $logger,
        private string $credentialsPath,
        private string $calendarId = 'primary'
    ) {
    }

    /**
     * Initialize Google Calendar client
     * Credentials path should point to the service account JSON file
     */
    private function initializeClient(): void
    {
        if ($this->client !== null) {
            return;
        }

        try {
            // Check if credentials file exists
            if (!file_exists($this->credentialsPath)) {
                $this->logger->error('Google Calendar credentials file not found', [
                    'path' => $this->credentialsPath
                ]);
                throw new \RuntimeException('Google Calendar credentials not configured. Please see documentation.');
            }

            $this->client = new GoogleClient();
            $this->client->setApplicationName('Hair Salon Booking System');
            $this->client->setScopes([Calendar::CALENDAR]);
            $this->client->setAuthConfig($this->credentialsPath);

            $this->calendarService = new Calendar($this->client);

            $this->logger->info('Google Calendar client initialized successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Google Calendar client', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a calendar event from a booking
     */
    public function createEvent(Booking $booking): ?string
    {
        try {
            $this->initializeClient();

            // Format date and time
            $appointmentDateTime = $booking->getAppointmentDate();
            $startDateTime = $appointmentDateTime->format('c'); // ISO 8601 format

            // Calculate end time based on service duration
            $endDateTime = clone $appointmentDateTime;
            $endDateTime->modify('+' . $booking->getService()->getDurationMinutes() . ' minutes');
            $endDateTimeStr = $endDateTime->format('c');

            // Create event
            // Note: Service accounts cannot invite attendees on personal Gmail calendars
            // Event is created directly on the calendar using Write permissions
            $event = new Event([
                'summary' => 'Hair Appointment: ' . $booking->getService()->getName(),
                'description' => $this->buildEventDescription($booking),
                'start' => new EventDateTime([
                    'dateTime' => $startDateTime,
                    'timeZone' => date_default_timezone_get(),
                ]),
                'end' => new EventDateTime([
                    'dateTime' => $endDateTimeStr,
                    'timeZone' => date_default_timezone_get(),
                ]),
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60], // 24 hours before
                        ['method' => 'popup', 'minutes' => 60], // 1 hour before
                    ],
                ],
            ]);

            // Insert event into calendar
            $createdEvent = $this->calendarService->events->insert($this->calendarId, $event);

            $this->logger->info('Google Calendar event created', [
                'event_id' => $createdEvent->getId(),
                'booking_id' => $booking->getId(),
                'client' => $booking->getClientEmail()
            ]);

            return $createdEvent->getId();

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Google Calendar event', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't throw - we don't want to fail the booking if calendar sync fails
            return null;
        }
    }

    /**
     * Update an existing calendar event
     */
    public function updateEvent(string $eventId, Booking $booking): bool
    {
        try {
            $this->initializeClient();

            // Get existing event
            $event = $this->calendarService->events->get($this->calendarId, $eventId);

            // Update event details
            $appointmentDateTime = $booking->getAppointmentDate();
            $startDateTime = $appointmentDateTime->format('c');

            $endDateTime = clone $appointmentDateTime;
            $endDateTime->modify('+' . $booking->getService()->getDurationMinutes() . ' minutes');
            $endDateTimeStr = $endDateTime->format('c');

            $event->setSummary('Hair Appointment: ' . $booking->getService()->getName());
            $event->setDescription($this->buildEventDescription($booking));
            $event->setStart(new EventDateTime([
                'dateTime' => $startDateTime,
                'timeZone' => date_default_timezone_get(),
            ]));
            $event->setEnd(new EventDateTime([
                'dateTime' => $endDateTimeStr,
                'timeZone' => date_default_timezone_get(),
            ]));

            // Update the event
            $this->calendarService->events->update($this->calendarId, $eventId, $event);

            $this->logger->info('Google Calendar event updated', [
                'event_id' => $eventId,
                'booking_id' => $booking->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update Google Calendar event', [
                'event_id' => $eventId,
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Delete a calendar event
     */
    public function deleteEvent(string $eventId): bool
    {
        try {
            $this->initializeClient();

            $this->calendarService->events->delete($this->calendarId, $eventId);

            $this->logger->info('Google Calendar event deleted', [
                'event_id' => $eventId
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete Google Calendar event', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Build event description with booking details
     */
    private function buildEventDescription(Booking $booking): string
    {
        return sprintf(
            "Client: %s\nEmail: %s\nPhone: %s\nService: %s\nDuration: %d minutes\nPrice: $%s\nStylist: %s\n\nNotes: %s",
            $booking->getClientName(),
            $booking->getClientEmail(),
            $booking->getClientPhone() ?? 'N/A',
            $booking->getService()->getName(),
            $booking->getService()->getDurationMinutes(),
            $booking->getService()->getPrice(),
            $booking->getStylist()->getName(),
            $booking->getNotes() ?? 'None'
        );
    }

    /**
     * Check if Google Calendar is properly configured
     */
    public function isConfigured(): bool
    {
        return file_exists($this->credentialsPath);
    }
}
