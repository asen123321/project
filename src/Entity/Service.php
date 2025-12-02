<?php
//
//namespace App\Entity;
//
//use App\Repository\ServiceRepository;
//use Doctrine\Common\Collections\ArrayCollection;
//use Doctrine\Common\Collections\Collection;
//use Doctrine\ORM\Mapping as ORM;
//
//#[ORM\Entity(repositoryClass: ServiceRepository::class)]
//class Service
//{
//    #[ORM\Id]
//    #[ORM\GeneratedValue]
//    #[ORM\Column]
//    private ?int $id = null;
//
//    #[ORM\Column(length: 100)]
//    private ?string $name = null;
//
//    #[ORM\Column(length: 500, nullable: true)]
//    private ?string $description = null;
//
//    #[ORM\Column]
//    private ?int $durationMinutes = null;
//
//    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
//    private ?string $price = null;
//
//    #[ORM\Column]
//    private ?bool $isActive = true;
//
//    /**
//     * @var Collection<int, Booking>
//     */
//    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'service')]
//    private Collection $bookings;
//
//    /**
//     * @var Collection<int, Stylist>
//     */
//    #[ORM\ManyToMany(targetEntity: Stylist::class, mappedBy: 'services')]
//    private Collection $stylists;
//
//    public function __construct()
//    {
//        $this->bookings = new ArrayCollection();
//        $this->stylists = new ArrayCollection();
//    }
//
//    public function getId(): ?int
//    {
//        return $this->id;
//    }
//
//    public function getName(): ?string
//    {
//        return $this->name;
//    }
//
//    public function setName(string $name): static
//    {
//        $this->name = $name;
//        return $this;
//    }
//
//    public function getDescription(): ?string
//    {
//        return $this->description;
//    }
//
//    public function setDescription(?string $description): static
//    {
//        $this->description = $description;
//        return $this;
//    }
//
//    public function getDurationMinutes(): ?int
//    {
//        return $this->durationMinutes;
//    }
//
//    public function setDurationMinutes(int $durationMinutes): static
//    {
//        $this->durationMinutes = $durationMinutes;
//        return $this;
//    }
//
//    public function getPrice(): ?string
//    {
//        return $this->price;
//    }
//
//    public function setPrice(string $price): static
//    {
//        $this->price = $price;
//        return $this;
//    }
//
//    public function isActive(): ?bool
//    {
//        return $this->isActive;
//    }
//
//    public function setIsActive(bool $isActive): static
//    {
//        $this->isActive = $isActive;
//        return $this;
//    }
//
//    /**
//     * @return Collection<int, Booking>
//     */
//    public function getBookings(): Collection
//    {
//        return $this->bookings;
//    }
//
//    public function addBooking(Booking $booking): static
//    {
//        if (!$this->bookings->contains($booking)) {
//            $this->bookings->add($booking);
//            $booking->setService($this);
//        }
//        return $this;
//    }
//
//    public function removeBooking(Booking $booking): static
//    {
//        if ($this->bookings->removeElement($booking)) {
//            // set the owning side to null (unless already changed)
//            if ($booking->getService() === $this) {
//                $booking->setService(null);
//            }
//        }
//        return $this;
//    }
//
//    /**
//     * @return Collection<int, Stylist>
//     */
//    public function getStylists(): Collection
//    {
//        return $this->stylists;
//    }
//
//    public function addStylist(Stylist $stylist): static
//    {
//        if (!$this->stylists->contains($stylist)) {
//            $this->stylists->add($stylist);
//            $stylist->addService($this);
//        }
//        return $this;
//    }
//
//    public function removeStylist(Stylist $stylist): static
//    {
//        if ($this->stylists->removeElement($stylist)) {
//            $stylist->removeService($this);
//        }
//        return $this;
//    }
//}