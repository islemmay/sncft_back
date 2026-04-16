<?php

namespace App\Entity;

use App\Enum\PassageClassification;
use App\Repository\PassageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PassageRepository::class)]
class Passage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['passage:read', 'trajet:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    #[Groups(['passage:read', 'passage:write', 'trajet:read'])]
    private ?\DateTimeImmutable $heureTheorique = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    #[Groups(['passage:read', 'passage:write', 'trajet:read'])]
    private ?\DateTimeImmutable $heureReelle = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['passage:read', 'trajet:read'])]
    private ?int $retardMinutes = null;

    #[ORM\Column(length: 20, enumType: PassageClassification::class)]
    #[Groups(['passage:read', 'passage:write', 'trajet:read'])]
    private PassageClassification $classification = PassageClassification::ON_TIME;

    #[ORM\ManyToOne(inversedBy: 'passages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['passage:write', 'passage:list'])]
    private ?Trajet $trajet = null;

    #[ORM\ManyToOne(inversedBy: 'passages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['passage:read', 'passage:write', 'trajet:read'])]
    private ?Gare $gare = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHeureTheorique(): ?\DateTimeImmutable
    {
        return $this->heureTheorique;
    }

    public function setHeureTheorique(?\DateTimeImmutable $heureTheorique): static
    {
        $this->heureTheorique = $heureTheorique;

        return $this;
    }

    public function getHeureReelle(): ?\DateTimeImmutable
    {
        return $this->heureReelle;
    }

    public function setHeureReelle(?\DateTimeImmutable $heureReelle): static
    {
        $this->heureReelle = $heureReelle;

        return $this;
    }

    public function getRetardMinutes(): ?int
    {
        return $this->retardMinutes;
    }

    public function setRetardMinutes(?int $retardMinutes): static
    {
        $this->retardMinutes = $retardMinutes;

        return $this;
    }

    public function getClassification(): PassageClassification
    {
        return $this->classification;
    }

    public function setClassification(PassageClassification $classification): static
    {
        $this->classification = $classification;

        return $this;
    }

    public function getTrajet(): ?Trajet
    {
        return $this->trajet;
    }

    public function setTrajet(?Trajet $trajet): static
    {
        $this->trajet = $trajet;

        return $this;
    }

    public function getGare(): ?Gare
    {
        return $this->gare;
    }

    public function setGare(?Gare $gare): static
    {
        $this->gare = $gare;

        return $this;
    }
}
