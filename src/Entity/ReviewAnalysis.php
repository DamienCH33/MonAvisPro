<?php

namespace App\Entity;

use App\Repository\ReviewAnalysisRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReviewAnalysisRepository::class)]
class ReviewAnalysis
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\OneToOne(inversedBy: 'reviewAnalysis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Establishment $establishment = null;

    #[ORM\Column(type: 'json')]
    private array $positiveThemes = [];

    #[ORM\Column(type: 'json')]
    private array $negativeThemes = [];

    #[ORM\Column(type: 'text')]
    private ?string $actionSuggestion = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

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

    public function getPositiveThemes(): array
    {
        return $this->positiveThemes;
    }

    public function setPositiveThemes(array $positiveThemes): static
    {
        $this->positiveThemes = $positiveThemes;
        return $this;
    }

    public function getNegativeThemes(): array
    {
        return $this->negativeThemes;
    }

    public function setNegativeThemes(array $negativeThemes): static
    {
        $this->negativeThemes = $negativeThemes;
        return $this;
    }

    public function getActionSuggestion(): ?string
    {
        return $this->actionSuggestion;
    }

    public function setActionSuggestion(string $actionSuggestion): static
    {
        $this->actionSuggestion = $actionSuggestion;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
