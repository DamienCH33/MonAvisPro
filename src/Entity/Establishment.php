<?php

namespace App\Entity;

use App\Repository\EstablishmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EstablishmentRepository::class)]
class Establishment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'establishments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $placeId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googlePlaceId = null;

    #[ORM\Column(length: 500)]
    private ?string $address = null;

    #[ORM\Column]
    private bool $alertsEnabled = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleAccountId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleLocationId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleAccessToken = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleRefreshToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $googleTokenExpiresAt = null;

    public const FORMALITIES = ['vous', 'tu'];
    public const TONES = ['cordial', 'formel', 'empathique'];

    /** Vouvoiement ou tutoiement dans les réponses aux avis. */
    #[ORM\Column(length: 4, options: ['default' => 'vous'])]
    private string $replyFormality = 'vous';

    /** Ton proposé par défaut lors de la génération d'une réponse. */
    #[ORM\Column(length: 20, options: ['default' => 'cordial'])]
    private string $replyTone = 'cordial';

    /** Signature ajoutée à la fin de chaque réponse (ex. « L'équipe du Fournil Béglais »). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $replySignature = null;

    /** Consignes propres au commerce (sujets à éviter, infos à rappeler, prénom du gérant…). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $replyInstructions = null;

    /**
     * @var Collection<int, Review>
     */
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
        if (null === $reviewAnalysis && null !== $this->reviewAnalysis) {
            $this->reviewAnalysis->setEstablishment(null);
        }
        if (null !== $reviewAnalysis && $reviewAnalysis->getEstablishment() !== $this) {
            $reviewAnalysis->setEstablishment($this);
        }
        $this->reviewAnalysis = $reviewAnalysis;

        return $this;
    }

    public function getGoogleAccountId(): ?string
    {
        return $this->googleAccountId;
    }

    public function setGoogleAccountId(?string $googleAccountId): static
    {
        $this->googleAccountId = $googleAccountId;

        return $this;
    }

    public function getGoogleLocationId(): ?string
    {
        return $this->googleLocationId;
    }

    public function setGoogleLocationId(?string $googleLocationId): static
    {
        $this->googleLocationId = $googleLocationId;

        return $this;
    }

    public function getGoogleAccessToken(): ?string
    {
        return $this->googleAccessToken;
    }

    public function setGoogleAccessToken(?string $googleAccessToken): static
    {
        $this->googleAccessToken = $googleAccessToken;

        return $this;
    }

    public function getGoogleRefreshToken(): ?string
    {
        return $this->googleRefreshToken;
    }

    public function setGoogleRefreshToken(?string $googleRefreshToken): static
    {
        $this->googleRefreshToken = $googleRefreshToken;

        return $this;
    }

    public function getGoogleTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->googleTokenExpiresAt;
    }

    public function setGoogleTokenExpiresAt(?\DateTimeImmutable $googleTokenExpiresAt): static
    {
        $this->googleTokenExpiresAt = $googleTokenExpiresAt;

        return $this;
    }

    public function getGooglePlaceId(): ?string
    {
        return $this->googlePlaceId;
    }

    public function setGooglePlaceId(?string $googlePlaceId): static
    {
        $this->googlePlaceId = $googlePlaceId;

        return $this;
    }

    public function getReplyFormality(): string
    {
        return $this->replyFormality;
    }

    public function setReplyFormality(string $replyFormality): static
    {
        if (!in_array($replyFormality, self::FORMALITIES, true)) {
            throw new \InvalidArgumentException('Formalité invalide : '.$replyFormality);
        }

        $this->replyFormality = $replyFormality;

        return $this;
    }

    public function getReplyTone(): string
    {
        return $this->replyTone;
    }

    public function setReplyTone(string $replyTone): static
    {
        if (!in_array($replyTone, self::TONES, true)) {
            throw new \InvalidArgumentException('Ton invalide : '.$replyTone);
        }

        $this->replyTone = $replyTone;

        return $this;
    }

    public function getReplySignature(): ?string
    {
        return $this->replySignature;
    }

    public function setReplySignature(?string $replySignature): static
    {
        $this->replySignature = $replySignature;

        return $this;
    }

    public function getReplyInstructions(): ?string
    {
        return $this->replyInstructions;
    }

    public function setReplyInstructions(?string $replyInstructions): static
    {
        $this->replyInstructions = $replyInstructions;

        return $this;
    }

    public function isConnectedToGoogleBusiness(): bool
    {
        return null !== $this->googleAccessToken
            && null !== $this->googleAccountId
            && null !== $this->googleLocationId;
    }
}
