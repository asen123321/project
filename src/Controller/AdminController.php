<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Message\BookingStatusChangeEmail;
use App\Repository\BookingRepository;
use App\Repository\ServiceRepository;
use App\Repository\StylistRepository;
use App\Repository\UserRepository;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookingRepository $bookingRepository,
        private UserRepository $userRepository,
        private StylistRepository $stylistRepository,
        private ServiceRepository $serviceRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private GoogleCalendarService $googleCalendarService
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        // Get statistics
        $totalBookings = $this->bookingRepository->count([]);
        $confirmedBookings = $this->bookingRepository->count(['status' => Booking::STATUS_CONFIRMED]);
        $pendingBookings = $this->bookingRepository->count(['status' => Booking::STATUS_PENDING]);
        $cancelledBookings = $this->bookingRepository->count(['status' => Booking::STATUS_CANCELLED]);

        // Get recent bookings (last 50)
        $recentBookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.user', 'u')
            ->leftJoin('b.stylist', 's')
            ->leftJoin('b.service', 'srv')
            ->addSelect('u', 's', 'srv')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        // Get upcoming bookings
        $upcomingBookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.user', 'u')
            ->leftJoin('b.stylist', 's')
            ->leftJoin('b.service', 'srv')
            ->addSelect('u', 's', 'srv')
            ->where('b.bookingDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING])
            ->orderBy('b.bookingDate', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('admin/dashboard.html.twig', [
            'total_bookings' => $totalBookings,
            'confirmed_bookings' => $confirmedBookings,
            'pending_bookings' => $pendingBookings,
            'cancelled_bookings' => $cancelledBookings,
            'recent_bookings' => $recentBookings,
            'upcoming_bookings' => $upcomingBookings,
        ]);
    }

    #[Route('/bookings', name: 'admin_bookings')]
    public function bookings(Request $request): Response
    {
        $status = $request->query->get('status');
        $date = $request->query->get('date');

        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.user', 'u')
            ->leftJoin('b.stylist', 's')
            ->leftJoin('b.service', 'srv')
            ->addSelect('u', 's', 'srv')
            ->orderBy('b.bookingDate', 'DESC');

        if ($status) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $status);
        }

        if ($date) {
            $qb->andWhere('DATE(b.bookingDate) = :date')
               ->setParameter('date', $date);
        }

        $bookings = $qb->getQuery()->getResult();

        return $this->render('admin/bookings.html.twig', [
            'bookings' => $bookings,
            'filter_status' => $status,
            'filter_date' => $date,
        ]);
    }

    #[Route('/booking/{id}', name: 'admin_booking_detail')]
    public function bookingDetail(int $id): Response
    {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            throw $this->createNotFoundException('Booking not found');
        }

        return $this->render('admin/booking_detail.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/booking/{id}/status', name: 'admin_booking_change_status', methods: ['POST'])]
    public function changeBookingStatus(int $id, Request $request): JsonResponse
    {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->json(['error' => 'Booking not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            return $this->json(['error' => 'Status is required'], 400);
        }

        // Validate status
        $validStatuses = [
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_COMPLETED
        ];

        if (!in_array($newStatus, $validStatuses)) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $oldStatus = $booking->getStatus();

        // Prevent unnecessary updates
        if ($oldStatus === $newStatus) {
            return $this->json([
                'success' => true,
                'message' => 'Status is already ' . $newStatus,
                'booking' => [
                    'id' => $booking->getId(),
                    'status' => $booking->getStatus()
                ]
            ]);
        }

        // Update status
        $booking->setStatus($newStatus);

        // Handle Google Calendar synchronization based on status change
        if ($newStatus === Booking::STATUS_CONFIRMED && !$booking->getGoogleCalendarEventId()) {
            // Sync to Google Calendar only for CONFIRMED bookings
            try {
                if ($this->googleCalendarService->isConfigured()) {
                    $calendarEventId = $this->googleCalendarService->createEvent($booking);
                    if ($calendarEventId) {
                        $booking->setGoogleCalendarEventId($calendarEventId);
                        $this->logger->info('Booking synced to Google Calendar on confirmation', [
                            'booking_id' => $booking->getId(),
                            'event_id' => $calendarEventId
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to sync booking to Google Calendar', [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        } elseif ($newStatus === Booking::STATUS_CANCELLED && $booking->getGoogleCalendarEventId()) {
            // Remove from Google Calendar when cancelled
            try {
                if ($this->googleCalendarService->isConfigured()) {
                    $this->googleCalendarService->deleteEvent($booking->getGoogleCalendarEventId());
                    $this->logger->info('Booking removed from Google Calendar on cancellation', [
                        'booking_id' => $booking->getId(),
                        'event_id' => $booking->getGoogleCalendarEventId()
                    ]);
                    $booking->setGoogleCalendarEventId(null);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to remove booking from Google Calendar', [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->em->flush();

        $this->logger->info('Admin changed booking status', [
            'booking_id' => $booking->getId(),
            'admin_email' => $this->getUser()->getEmail(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'client_email' => $booking->getUser()->getEmail()
        ]);

        // Queue email notification to client
        $this->messageBus->dispatch(new BookingStatusChangeEmail(
            $booking->getId(),
            $oldStatus,
            $newStatus
        ));

        return $this->json([
            'success' => true,
            'message' => 'Status updated successfully. Email notification sent to client.',
            'booking' => [
                'id' => $booking->getId(),
                'status' => $booking->getStatus(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]
        ]);
    }

    #[Route('/stats', name: 'admin_stats')]
    public function stats(): JsonResponse
    {
        // Get booking statistics
        $stats = [
            'total' => $this->bookingRepository->count([]),
            'confirmed' => $this->bookingRepository->count(['status' => Booking::STATUS_CONFIRMED]),
            'pending' => $this->bookingRepository->count(['status' => Booking::STATUS_PENDING]),
            'cancelled' => $this->bookingRepository->count(['status' => Booking::STATUS_CANCELLED]),
            'completed' => $this->bookingRepository->count(['status' => Booking::STATUS_COMPLETED]),
        ];

        // Get today's bookings
        $today = new \DateTime();
        $todayBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('DATE(b.bookingDate) = :today')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $stats['today'] = count($todayBookings);

        // Get this week's bookings
        $weekStart = (new \DateTime())->modify('monday this week');
        $weekEnd = (new \DateTime())->modify('sunday this week');

        $weekBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.bookingDate BETWEEN :start AND :end')
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->getQuery()
            ->getResult();

        $stats['this_week'] = count($weekBookings);

        // Get total revenue (confirmed bookings)
        $confirmedBookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.service', 's')
            ->addSelect('s')
            ->where('b.status = :status')
            ->setParameter('status', Booking::STATUS_CONFIRMED)
            ->getQuery()
            ->getResult();

        $totalRevenue = 0;
        foreach ($confirmedBookings as $booking) {
            $totalRevenue += (float) $booking->getService()->getPrice();
        }

        $stats['total_revenue'] = number_format($totalRevenue, 2);

        return $this->json($stats);
    }
}
