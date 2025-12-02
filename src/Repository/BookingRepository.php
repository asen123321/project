<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Stylist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\LockMode; // <--- 1. Не забравяйте да импортнете това най-горе
/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Check if a stylist has a conflicting booking at the given time
     * Returns true if there's a conflict (slot is NOT available)
     */
    // Добавяме параметър $lock = false по подразбиране
    public function hasConflictingBooking(Stylist $stylist, \DateTimeInterface $startTime, int $durationMinutes, ?int $excludeBookingId = null, bool $lock = false): bool
    {
        $endTime = (clone $startTime)->modify('+' . $durationMinutes . ' minutes');

        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.service', 's')
            ->where('b.stylist = :stylist')
            ->andWhere('b.status != :cancelled')
            ->andWhere(
                '(b.bookingDate < :endTime AND ' .
                'DATE_ADD(b.bookingDate, s.durationMinutes, \'MINUTE\') > :startTime)'
            )
            ->setParameter('stylist', $stylist)
            ->setParameter('cancelled', Booking::STATUS_CANCELLED)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeBookingId !== null) {
            $qb->andWhere('b.id != :excludeId')
                ->setParameter('excludeId', $excludeBookingId);
        }

        // Взимаме Query обекта
        $query = $qb->getQuery();

        // ⚠️ ПОПРАВКА: Заключваме САМО ако сме поискали ($lock === true)
        // И само ако използваме Query обекта, който сме взели по-горе
        if ($lock) {
            $query->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        }

        // ВАЖНО: Изпълняваме $query, а НЕ $qb->getQuery() (за да важи заключването)
        return $query->getSingleScalarResult() > 0;
    }

    /**
     * Get bookings for a stylist on a specific date
     */
    public function findByStylistAndDate(Stylist $stylist, \DateTimeInterface $date): array
    {
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('b')
            ->where('b.stylist = :stylist')
            ->andWhere('b.bookingDate BETWEEN :start AND :end')
            ->andWhere('b.status != :cancelled')
            ->setParameter('stylist', $stylist)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->setParameter('cancelled', Booking::STATUS_CANCELLED)
            ->orderBy('b.bookingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all bookings for a specific date range
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.bookingDate BETWEEN :start AND :end')
            ->andWhere('b.status != :cancelled')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('b.bookingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user's bookings
     */
    public function findUpcomingByUser($user): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->andWhere('b.bookingDate >= :now')
            ->andWhere('b.status != :cancelled')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('cancelled', Booking::STATUS_CANCELLED)
            ->orderBy('b.bookingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
