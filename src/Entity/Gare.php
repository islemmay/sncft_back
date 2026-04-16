<?php

namespace App\Entity;

use App\Repository\GareRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GareRepository::class)]
class Gare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['gare:read', 'passage:read', 'gare:write', 'passage:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['gare:read', 'gare:write', 'passage:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['gare:read', 'gare:write'])]
    private float $latitude = 0.0;

    #[ORM\Column(type: 'float')]
    #[Groups(['gare:read', 'gare:write'])]
    private float $longitude = 0.0;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Groups(['gare:read', 'gare:write', 'passage:read'])]
    private ?string $ville = null;

    /** @var Collection<int, Passage> */
    #[ORM\OneToMany(targetEntity: Passage::class, mappedBy: 'gare')]
    private Collection $passages;

    public function __construct()
    {
        $this->passages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    /** @return Collection<int, Passage> */
    public function getPassages(): Collection
    {
        return $this->passages;
    }
}
