<?php

namespace App\Entity;

use App\Entity\User;
use App\Repository\EstablishmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EstablishmentRepository::class)]
class Establishment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'establishments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $placeId = null;

    #[ORM\Column(length: 500)]
    private ?string $address = null;

    #[ORM\Column]
    private bool $alertsEnabled = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'establishment', orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\OneToOne(mappedBy: 'establishment', cascade: ['persist', 'remove'])]
    private ?ReviewAnalysis $reviewAnalysis = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
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

    public function getPlaceId(): ?string
    {
        return $this->placeId;
    }

    public function setPlaceId(string $placeId): static
    {
        $this->placeId = $placeId;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function isAlertsEnabled(): bool
    {
        return $this->alertsEnabled;
    }

    public function setAlertsEnabled(bool $alertsEnabled): static
    {
        $this->alertsEnabled = $alertsEnabled;
        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setEstablishment($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getEstablishment() === $this) {
                $review->setEstablishment(null);
            }
        }
        return $this;
    }

    public function getReviewAnalysis(): ?ReviewAnalysis
    {
        return $this->reviewAnalysis;
    }

    public function setReviewAnalysis(?ReviewAnalysis $reviewAnalysis): static
    {
        if ($reviewAnalysis === null && $this->reviewAnalysis !== null) {
            $this->reviewAnalysis->setEstablishment(null);
        }
        if ($reviewAnalysis !== null && $reviewAnalysis->getEstablishment() !== $this) {
            $reviewAnalysis->setEstablishment($this);
        }
        $this->reviewAnalysis = $reviewAnalysis;
        return $this;
    }
}
