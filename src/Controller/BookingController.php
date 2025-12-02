<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\ServiceItem; // <--- ВЕЧЕ ПОЛЗВАМЕ SERVICE ITEM
use App\Message\AdminBookingNotification;
use App\Message\BookingConfirmationEmail;
use App\Repository\BookingRepository;
use App\Repository\ServiceItemRepository; // <--- НОВОТО РЕПОЗИТОРИ
use App\Repository\StylistRepository;
use App\Service\GoogleCalendarService;
use App\Service\ReCaptchaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/booking')]
class BookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookingRepository $bookingRepository,
        private StylistRepository $stylistRepository,
        private ServiceItemRepository $serviceItemRepository, // <--- Сменено
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private GoogleCalendarService $googleCalendarService,
        private ReCaptchaService $recaptchaService
    ) {
    }

    #[Route('/', name: 'booking_index')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $stylists = $this->stylistRepository->findAllActive();
        // Взимаме всички активни услуги от новата таблица
        $services = $this->serviceItemRepository->findBy(['isActive' => true]);
        $userBookings = $this->bookingRepository->findUpcomingByUser($this->getUser());

        return $this->render('booking/index.html.twig', [
            'stylists' => $stylists,
            'services' => $services,
            'userBookings' => $userBookings,
            'recaptcha_site_key' => $this->recaptchaService->getSiteKey(),
            'recaptcha_enabled' => $this->recaptchaService->isEnabled(),
        ]);
    }

    #[Route('/api/bookings', name: 'api_bookings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAllBookings(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        if (!$startDate || !$endDate) {
            return $this->json(['error' => 'Missing start or end date'], 400);
        }

        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.stylist', 's')
            ->leftJoin('b.service', 'srv') // Това сочи към ServiceItem вече
            ->addSelect('s', 'srv')
            ->where('b.bookingDate BETWEEN :start AND :end')
            ->andWhere('b.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', Booking::STATUS_CONFIRMED)
            ->orderBy('b.bookingDate', 'ASC')
            ->getQuery()
            ->getResult();

        $events = [];
        foreach ($bookings as $booking) {
            try {
                $events[] = [
                    'id' => (string) $booking->getId(),
                    'title' => 'Busy',
                    'start' => $booking->getBookingDate()->format('Y-m-d\TH:i:s'),
                    'end' => $booking->getEndTime()->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => '#6c757d',
                    'borderColor' => '#5a6268',
                    'display' => 'background',
                    'editable' => false,
                    'extendedProps' => [
                        'type' => 'busy',
                        'stylistId' => $booking->getStylist() ? $booking->getStylist()->getId() : null
                    ]
                ];
            } catch (\Exception $e) {
                // Log error
            }
        }

        return $this->json($events);
    }

    #[Route('/api/available-slots', name: 'booking_available_slots', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function availableSlots(Request $request): JsonResponse
    {
        $stylistId = $request->query->get('stylist_id');
        $date = $request->query->get('date');
        $serviceId = $request->query->get('service_id');

        if (!$stylistId || !$date || !$serviceId) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        $stylist = $this->stylistRepository->find($stylistId);
        // Търсим в ServiceItemRepository
        $service = $this->serviceItemRepository->find($serviceId);

        if (!$stylist || !$service) {
            return $this->json(['error' => 'Invalid stylist or service'], 404);
        }

        try {
            $selectedDate = new \DateTime($date);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        $slots = [];
        $startHour = 9;
        $endHour = 18;

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $slotTime = (clone $selectedDate)->setTime($hour, $minute);

                if ($slotTime <= new \DateTime()) {
                    continue;
                }

                $slotEnd = (clone $slotTime)->modify('+' . $service->getDurationMinutes() . ' minutes');
                if ($slotEnd->format('H') >= $endHour) {
                    continue;
                }

                $hasConflict = $this->bookingRepository->hasConflictingBooking(
                    $stylist,
                    $slotTime,
                    $service->getDurationMinutes()
                );

                if (!$hasConflict) {
                    $slots[] = [
                        'time' => $slotTime->format('H:i'),
                        'display' => $slotTime->format('g:i A'),
                    ];
                }
            }
        }

        return $this->json(['slots' => $slots]);
    }

    #[Route('/create', name: 'booking_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        // --- reCAPTCHA проверка ---
        if ($this->recaptchaService->isEnabled()) {
            $recaptchaToken = $data['recaptcha_token'] ?? null;
            if (!$recaptchaToken) {
                return $this->json(['error' => 'Security verification is required.'], Response::HTTP_FORBIDDEN);
            }
            $verification = $this->recaptchaService->verify($recaptchaToken, 'booking_create', $request->getClientIp());
            if (!$verification['success']) {
                return $this->json(['error' => 'Suspicious activity detected.', 'code' => 'RECAPTCHA_FAILED'], Response::HTTP_FORBIDDEN);
            }
        }
        // --------------------------

        $stylistId = $data['stylist_id'] ?? null;
        $serviceId = $data['service_id'] ?? null;
        $bookingDate = $data['booking_date'] ?? null;
        $bookingTime = $data['booking_time'] ?? null;
        $notes = $data['notes'] ?? null;
        $clientPhone = $data['client_phone'] ?? null;

        if (!$stylistId || !$serviceId || !$bookingDate || !$bookingTime) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        $stylist = $this->stylistRepository->find($stylistId);
        // Търсим в ServiceItemRepository
        $service = $this->serviceItemRepository->find($serviceId);

        if (!$stylist || !$service) {
            return $this->json(['error' => 'Invalid stylist or service'], 404);
        }

        try {
            $bookingDateTime = new \DateTime($bookingDate . ' ' . $bookingTime);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date/time format'], 400);
        }

        if ($bookingDateTime <= new \DateTime()) {
            return $this->json(['error' => 'Cannot book appointments in the past'], 400);
        }

        // CRITICAL: Transaction & Locking
        $this->em->beginTransaction();

        try {
            $hasConflict = $this->bookingRepository->hasConflictingBooking(
                $stylist,
                $bookingDateTime,
                $service->getDurationMinutes(),
                null,
                true // LOCKING ON
            );

            if ($hasConflict) {
                $this->em->rollback();
                return $this->json([
                    'error' => 'SLOT UNAVAILABLE: This time slot is already booked.',
                    'details' => [
                        'requested_time' => $bookingDateTime->format('Y-m-d H:i'),
                        'stylist' => $stylist->getName(),
                        'service' => $service->getName() // Използваме getName() alias-а от ServiceItem
                    ]
                ], 409);
            }

            $booking = new Booking();
            $booking->setUser($this->getUser());
            $booking->setStylist($stylist);
            $booking->setService($service); // Свързваме със ServiceItem
            $booking->setBookingDate($bookingDateTime);
            $booking->setNotes($notes);
            $booking->setStatus(Booking::STATUS_PENDING);

            $user = $this->getUser();
            $booking->setClientName($user->getFirstName() . ' ' . $user->getLastName());
            $booking->setClientEmail($user->getEmail());
            $booking->setClientPhone($clientPhone);

            $this->em->persist($booking);
            $this->em->flush();
            $this->em->commit();

            // Изпращане на имейли (Async)
            $this->messageBus->dispatch(new BookingConfirmationEmail($booking->getId()));
            $this->messageBus->dispatch(new AdminBookingNotification($booking->getId()));

            return $this->json([
                'success' => true,
                'message' => 'Booking created successfully!',
                'booking' => [
                    'id' => $booking->getId(),
                    'date' => $booking->getBookingDate()->format('Y-m-d'),
                    'time' => $booking->getBookingDate()->format('H:i'),
                    'stylist' => $stylist->getName(),
                    'service' => $service->getName(),
                    'price' => $service->getPrice(),
                ],
            ]);

        } catch (\Throwable $e) {
            $this->em->rollback();
            $this->logger->error('Booking creation failed', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/cancel/{id}', name: 'booking_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(int $id): JsonResponse
    {
        $booking = $this->bookingRepository->findOneBy([
            'id' => $id,
            'user' => $this->getUser()
        ]);

        if (!$booking) {
            return $this->json(['error' => 'Booking not found'], 404);
        }

        if ($booking->getStatus() === Booking::STATUS_CANCELLED) {
            return $this->json(['error' => 'Booking already cancelled'], 400);
        }

        $booking->setStatus(Booking::STATUS_CANCELLED);

        if ($booking->getGoogleCalendarEventId()) {
            try {
                $this->googleCalendarService->deleteEvent($booking->getGoogleCalendarEventId());
            } catch (\Exception $e) {
                $this->logger->error('Failed to delete Google Calendar event');
            }
        }

        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Booking cancelled successfully']);
    }
}