<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Index(columns: ['booking_date', 'stylist_id'], name: 'idx_booking_date_stylist')]
class Booking
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Stylist::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stylist $stylist = null;

    // 👇 ПРОМЯНА: Вече сочи към ServiceItem
    #[ORM\ManyToOne(targetEntity: ServiceItem::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceItem $service = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $bookingDate = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientEmail = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $clientPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleCalendarEventId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = self::STATUS_PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getStylist(): ?Stylist
    {
        return $this->stylist;
    }

    public function setStylist(?Stylist $stylist): static
    {
        $this->stylist = $stylist;
        return $this;
    }

    public function getService(): ?ServiceItem
    {
        return $this->service;
    }

    public function setService(?ServiceItem $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getBookingDate(): ?\DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeInterface $bookingDate): static
    {
        $this->bookingDate = $bookingDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Взимаме продължителността от durationMinutes (което добавихме в ServiceItem)
    public function getEndTime(): \DateTimeInterface
    {
        $endTime = clone $this->bookingDate;
        return $endTime->modify('+' . $this->service->getDurationMinutes() . ' minutes');
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $this->clientName = $clientName;
        return $this;
    }

    public function getClientEmail(): ?string
    {
        return $this->clientEmail;
    }

    public function setClientEmail(?string $clientEmail): static
    {
        $this->clientEmail = $clientEmail;
        return $this;
    }

    public function getClientPhone(): ?string
    {
        return $this->clientPhone;
    }

    public function setClientPhone(?string $clientPhone): static
    {
        $this->clientPhone = $clientPhone;
        return $this;
    }

    public function getGoogleCalendarEventId(): ?string
    {
        return $this->googleCalendarEventId;
    }

    public function setGoogleCalendarEventId(?string $googleCalendarEventId): static
    {
        $this->googleCalendarEventId = $googleCalendarEventId;
        return $this;
    }

    public function getAppointmentDate(): ?\DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function setAppointmentDate(\DateTimeInterface $appointmentDate): static
    {
        $this->bookingDate = $appointmentDate;
        return $this;
    }
}