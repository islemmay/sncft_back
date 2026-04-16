<?php

namespace App\Entity;

use App\Repository\TrainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TrainRepository::class)]
class Train
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['train:read', 'trajet:read', 'train:write', 'trajet:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Groups(['train:read', 'train:write', 'trajet:read'])]
    private ?string $numero = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Groups(['train:read', 'train:write', 'trajet:read'])]
    private ?string $type = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['train:read', 'train:write', 'trajet:read'])]
    private ?int $capacite = null;

    /** @var Collection<int, Trajet> */
    #[ORM\OneToMany(targetEntity: Trajet::class, mappedBy: 'train', orphanRemoval: true)]
    private Collection $trajets;

    public function __construct()
    {
        $this->trajets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): static
    {
        $this->capacite = $capacite;

        return $this;
    }

    /** @return Collection<int, Trajet> */
    public function getTrajets(): Collection
    {
        return $this->trajets;
    }

    public function addTrajet(Trajet $trajet): static
    {
        if (!$this->trajets->contains($trajet)) {
            $this->trajets->add($trajet);
            $trajet->setTrain($this);
        }

        return $this;
    }

    public function removeTrajet(Trajet $trajet): static
    {
        if ($this->trajets->removeElement($trajet) && $trajet->getTrain() === $this) {
            $trajet->setTrain(null);
        }

        return $this;
    }
}
