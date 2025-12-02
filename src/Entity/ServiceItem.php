<?php

namespace App\Entity;

use App\Repository\ServiceItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceItemRepository::class)]
class ServiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $iconClass = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $duration = null; // Текстово поле (напр. "30 мин")

    // 👇 НОВО: Задължително за календара (изчисления)
    #[ORM\Column(type: 'integer')]
    private ?int $durationMinutes = 30; // Default 30 min

    // 👇 НОВО: Активна ли е услугата
    #[ORM\Column]
    private ?bool $isActive = true;

    // 👇 ВРЪЗКА: Кои стилисти предлагат тази услуга
    /**
     * @var Collection<int, Stylist>
     */
    #[ORM\ManyToMany(targetEntity: Stylist::class, mappedBy: 'services')]
    private Collection $stylists;

    // 👇 ВРЪЗКА: Резервации за тази услуга
    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'service')]
    private Collection $bookings;

    public function __construct()
    {
        $this->stylists = new ArrayCollection();
        $this->bookings = new ArrayCollection();
    }

    // --- СЪВМЕСТИМОСТ ---
    // Добавяме този метод, защото много от стария код търси getName()
    public function getName(): ?string
    {
        return $this->title;
    }
    // -------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;
        return $this;
    }

    public function getIconClass(): ?string
    {
        return $this->iconClass;
    }

    public function setIconClass(?string $iconClass): static
    {
        $this->iconClass = $iconClass;
        return $this;
    }

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(?string $duration): void
    {
        $this->duration = $duration;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return Collection<int, Stylist>
     */
    public function getStylists(): Collection
    {
        return $this->stylists;
    }

    public function addStylist(Stylist $stylist): static
    {
        if (!$this->stylists->contains($stylist)) {
            $this->stylists->add($stylist);
            $stylist->addService($this);
        }
        return $this;
    }

    public function removeStylist(Stylist $stylist): static
    {
        if ($this->stylists->removeElement($stylist)) {
            $stylist->removeService($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setService($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getService() === $this) {
                $booking->setService(null);
            }
        }
        return $this;
    }
}