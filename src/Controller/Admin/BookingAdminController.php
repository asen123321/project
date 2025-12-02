<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\ServiceRepository;
use App\Repository\StylistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/bookings')]
#[IsGranted('ROLE_ADMIN')]
class BookingAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookingRepository $bookingRepository,
        private StylistRepository $stylistRepository,
        private ServiceRepository $serviceRepository
    ) {
    }

    #[Route('/', name: 'admin_booking_index')]
    public function index(): Response
    {
        $today = new \DateTime();
        $weekFromNow = (clone $today)->modify('+7 days');

        $upcomingBookings = $this->bookingRepository->findByDateRange($today, $weekFromNow);
        $stylists = $this->stylistRepository->findAll();
        $services = $this->serviceRepository->findAll();

        return $this->render('admin/booking/index.html.twig', [
            'bookings' => $upcomingBookings,
            'stylists' => $stylists,
            'services' => $services,
        ]);
    }

    #[Route('/calendar', name: 'admin_booking_calendar', methods: ['GET'])]
    public function calendar(Request $request): JsonResponse
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        try {
            $startDate = new \DateTime($start);
            $endDate = new \DateTime($end);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        $bookings = $this->bookingRepository->findByDateRange($startDate, $endDate);

        $events = [];
        foreach ($bookings as $booking) {
            $events[] = [
                'id' => $booking->getId(),
                'title' => sprintf(
                    '%s - %s (%s)',
                    $booking->getUser()->getFirstName() ?: $booking->getUser()->getUsername(),
                    $booking->getService()->getName(),
                    $booking->getStylist()->getName()
                ),
                'start' => $booking->getBookingDate()->format('Y-m-d\TH:i:s'),
                'end' => $booking->getEndTime()->format('Y-m-d\TH:i:s'),
                'color' => $this->getStatusColor($booking->getStatus()),
                'extendedProps' => [
                    'customerName' => $booking->getUser()->getFirstName() . ' ' . $booking->getUser()->getLastName(),
                    'customerEmail' => $booking->getUser()->getEmail(),
                    'stylist' => $booking->getStylist()->getName(),
                    'service' => $booking->getService()->getName(),
                    'price' => $booking->getService()->getPrice(),
                    'status' => $booking->getStatus(),
                    'notes' => $booking->getNotes(),
                ],
            ];
        }

        return $this->json($events);
    }

    #[Route('/{id}/status', name: 'admin_booking_update_status', methods: ['POST'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->json(['error' => 'Booking not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;

        if (!in_array($status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $booking->setStatus($status);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    }

    #[Route('/statistics', name: 'admin_booking_statistics')]
    public function statistics(): Response
    {
        $today = new \DateTime();
        $monthStart = (clone $today)->modify('first day of this month')->setTime(0, 0);
        $monthEnd = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);

        $monthlyBookings = $this->bookingRepository->findByDateRange($monthStart, $monthEnd);

        $stats = [
            'total_bookings' => count($monthlyBookings),
            'total_revenue' => 0,
            'by_status' => [
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ],
        ];

        foreach ($monthlyBookings as $booking) {
            $stats['total_revenue'] += (float) $booking->getService()->getPrice();
            $stats['by_status'][$booking->getStatus()]++;
        }

        return $this->render('admin/booking/statistics.html.twig', [
            'stats' => $stats,
            'month' => $today->format('F Y'),
        ]);
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PENDING => '#FFA500',
            Booking::STATUS_CONFIRMED => '#28a745',
            Booking::STATUS_CANCELLED => '#dc3545',
            Booking::STATUS_COMPLETED => '#6c757d',
            default => '#007bff',
        };
    }
}
