<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Message\BookingStatusChangeEmail;
use App\Repository\BookingRepository;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Public controller for one-click booking actions via signed URLs
 * No authentication required - security via signed URLs
 */
#[IsGranted('ROLE_ADMIN')]
class BookingQuickActionController extends AbstractController
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private EntityManagerInterface $em,
        private UriSigner $uriSigner,
        private MessageBusInterface $messageBus,
        private GoogleCalendarService $googleCalendarService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/booking/{id}/approve', name: 'booking_quick_approve', methods: ['GET'])]
    public function approve(int $id, Request $request): Response
    {
        // Verify signed URL
        if (!$this->uriSigner->check($request->getUri())) {
            $this->logger->warning('Invalid signature for booking approval', [
                'booking_id' => $id,
                'uri' => $request->getUri()
            ]);

            return $this->render('booking/quick_action_error.html.twig', [
                'action' => 'approve',
                'error' => 'Invalid or expired link. This link may have already been used or has expired for security reasons.'
            ]);
        }

        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->render('booking/quick_action_error.html.twig', [
                'action' => 'approve',
                'error' => 'Booking not found.'
            ]);
        }

        // Check if already approved
        if ($booking->getStatus() === Booking::STATUS_CONFIRMED) {
            return $this->render('booking/quick_action_success.html.twig', [
                'action' => 'approve',
                'booking' => $booking,
                'already_processed' => true,
                'message' => 'This booking was already approved.'
            ]);
        }

        $oldStatus = $booking->getStatus();

        // Update status to CONFIRMED
        $booking->setStatus(Booking::STATUS_CONFIRMED);

        // Sync to Google Calendar if not already synced
        if (!$booking->getGoogleCalendarEventId()) {
            try {
                if ($this->googleCalendarService->isConfigured()) {
                    $calendarEventId = $this->googleCalendarService->createEvent($booking);
                    if ($calendarEventId) {
                        $booking->setGoogleCalendarEventId($calendarEventId);
                        $this->logger->info('Booking synced to Google Calendar via quick approval', [
                            'booking_id' => $booking->getId(),
                            'event_id' => $calendarEventId
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to sync booking to Google Calendar during quick approval', [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->em->flush();

        $this->logger->info('Booking approved via one-click action', [
            'booking_id' => $booking->getId(),
            'old_status' => $oldStatus,
            'new_status' => Booking::STATUS_CONFIRMED,
            'client_email' => $booking->getUser()->getEmail()
        ]);

        // Send confirmation email to client
        $this->messageBus->dispatch(new BookingStatusChangeEmail(
            $booking->getId(),
            $oldStatus,
            Booking::STATUS_CONFIRMED
        ));

        return $this->render('booking/quick_action_success.html.twig', [
            'action' => 'approve',
            'booking' => $booking,
            'already_processed' => false,
            'message' => 'Booking has been approved! A confirmation email has been sent to the client.'
        ]);
    }

    #[Route('/booking/{id}/reject', name: 'booking_quick_reject', methods: ['GET'])]
    public function reject(int $id, Request $request): Response
    {
        // Verify signed URL
        if (!$this->uriSigner->check($request->getUri())) {
            $this->logger->warning('Invalid signature for booking rejection', [
                'booking_id' => $id,
                'uri' => $request->getUri()
            ]);

            return $this->render('booking/quick_action_error.html.twig', [
                'action' => 'reject',
                'error' => 'Invalid or expired link. This link may have already been used or has expired for security reasons.'
            ]);
        }

        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->render('booking/quick_action_error.html.twig', [
                'action' => 'reject',
                'error' => 'Booking not found.'
            ]);
        }

        // Check if already rejected
        if ($booking->getStatus() === Booking::STATUS_CANCELLED) {
            return $this->render('booking/quick_action_success.html.twig', [
                'action' => 'reject',
                'booking' => $booking,
                'already_processed' => true,
                'message' => 'This booking was already rejected.'
            ]);
        }

        $oldStatus = $booking->getStatus();

        // Update status to CANCELLED
        $booking->setStatus(Booking::STATUS_CANCELLED);

        // Remove from Google Calendar if it was synced
        if ($booking->getGoogleCalendarEventId()) {
            try {
                if ($this->googleCalendarService->isConfigured()) {
                    $this->googleCalendarService->deleteEvent($booking->getGoogleCalendarEventId());
                    $this->logger->info('Booking removed from Google Calendar via quick rejection', [
                        'booking_id' => $booking->getId(),
                        'event_id' => $booking->getGoogleCalendarEventId()
                    ]);
                    $booking->setGoogleCalendarEventId(null);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to remove booking from Google Calendar during quick rejection', [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->em->flush();

        $this->logger->info('Booking rejected via one-click action', [
            'booking_id' => $booking->getId(),
            'old_status' => $oldStatus,
            'new_status' => Booking::STATUS_CANCELLED,
            'client_email' => $booking->getUser()->getEmail()
        ]);

        // Send rejection email to client
        $this->messageBus->dispatch(new BookingStatusChangeEmail(
            $booking->getId(),
            $oldStatus,
            Booking::STATUS_CANCELLED
        ));

        return $this->render('booking/quick_action_success.html.twig', [
            'action' => 'reject',
            'booking' => $booking,
            'already_processed' => false,
            'message' => 'Booking has been rejected. A notification email has been sent to the client.'
        ]);
    }
}
