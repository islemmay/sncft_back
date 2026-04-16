<?php

namespace App\Entity;

use App\Repository\StatistiqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StatistiqueRepository::class)]
class Statistique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['stat:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['stat:read', 'stat:write'])]
    private ?\DateTimeImmutable $periodeDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['stat:read', 'stat:write'])]
    private ?\DateTimeImmutable $periodeFin = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['stat:read', 'stat:write'])]
    private int $nbRetards = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriodeDebut(): ?\DateTimeImmutable
    {
        return $this->periodeDebut;
    }

    public function setPeriodeDebut(\DateTimeImmutable $periodeDebut): static
    {
        $this->periodeDebut = $periodeDebut;

        return $this;
    }

    public function getPeriodeFin(): ?\DateTimeImmutable
    {
        return $this->periodeFin;
    }

    public function setPeriodeFin(\DateTimeImmutable $periodeFin): static
    {
        $this->periodeFin = $periodeFin;

        return $this;
    }

    public function getNbRetards(): int
    {
        return $this->nbRetards;
    }

    public function setNbRetards(int $nbRetards): static
    {
        $this->nbRetards = $nbRetards;

        return $this;
    }
}
