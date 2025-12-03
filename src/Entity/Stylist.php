<?php

namespace App\Entity;

use App\Repository\StylistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StylistRepository::class)]
class Stylist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $specialization = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'stylist')]
    private Collection $bookings;

    /**
     * @var Collection<int, ServiceItem>
     */
    // 👇 ПРОМЯНА: Вече сочи към ServiceItem
    #[ORM\ManyToMany(targetEntity: ServiceItem::class, inversedBy: 'stylists')]
    #[ORM\JoinTable(name: 'stylist_services')]
    private Collection $services;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->services = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): static
    {
        $this->photoUrl = $photoUrl;
        return $this;
    }

    public function getSpecialization(): ?string
    {
        return $this->specialization;
    }

    public function setSpecialization(?string $specialization): static
    {
        $this->specialization = $specialization;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
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
            $booking->setStylist($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getStylist() === $this) {
                $booking->setStylist(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ServiceItem>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(ServiceItem $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
        }
        return $this;
    }

    public function removeService(ServiceItem $service): static
    {
        $this->services->removeElement($service);
        return $this;
    }
}