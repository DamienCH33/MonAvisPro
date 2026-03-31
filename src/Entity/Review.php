<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
class Review
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Establishment $establishment = null;

    #[ORM\Column(length: 255)]
    private ?string $googleAuthor = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $googleAuthorPhoto = null;

    #[ORM\Column]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $googleReviewId = null;

    #[ORM\Column]
    private bool $isRead = false;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEstablishment(): ?Establishment
    {
        return $this->establishment;
    }

    public function setEstablishment(?Establishment $establishment): static
    {
        $this->establishment = $establishment;

        return $this;
    }

    public function getGoogleAuthor(): ?string
    {
        return $this->googleAuthor;
    }

    public function setGoogleAuthor(string $googleAuthor): static
    {
        $this->googleAuthor = $googleAuthor;

        return $this;
    }

    public function getGoogleAuthorPhoto(): ?string
    {
        return $this->googleAuthorPhoto;
    }

    public function setGoogleAuthorPhoto(?string $googleAuthorPhoto): static
    {
        $this->googleAuthorPhoto = $googleAuthorPhoto;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getGoogleReviewId(): ?string
    {
        return $this->googleReviewId;
    }

    public function setGoogleReviewId(string $googleReviewId): static
    {
        $this->googleReviewId = $googleReviewId;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }
}
