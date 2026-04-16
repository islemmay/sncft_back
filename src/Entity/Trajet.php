<?php

namespace App\Entity;

use App\Repository\TrajetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TrajetRepository::class)]
class Trajet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['trajet:read', 'trajet:write', 'passage:write', 'trajet:summary'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['trajet:read', 'trajet:write', 'trajet:summary'])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Groups(['trajet:read', 'trajet:write', 'trajet:summary'])]
    private ?string $villeDepart = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Groups(['trajet:read', 'trajet:write', 'trajet:summary'])]
    private ?string $villeArrivee = null;

    #[ORM\ManyToOne(inversedBy: 'trajets')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['trajet:read', 'trajet:write', 'trajet:summary'])]
    private ?Train $train = null;

    /** @var Collection<int, Passage> */
    #[ORM\OneToMany(targetEntity: Passage::class, mappedBy: 'trajet', orphanRemoval: true, cascade: ['persist'])]
    private Collection $passages;

    public function __construct()
    {
        $this->passages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getVilleDepart(): ?string
    {
        return $this->villeDepart;
    }

    public function setVilleDepart(string $villeDepart): static
    {
        $this->villeDepart = $villeDepart;

        return $this;
    }

    public function getVilleArrivee(): ?string
    {
        return $this->villeArrivee;
    }

    public function setVilleArrivee(string $villeArrivee): static
    {
        $this->villeArrivee = $villeArrivee;

        return $this;
    }

    public function getTrain(): ?Train
    {
        return $this->train;
    }

    public function setTrain(?Train $train): static
    {
        $this->train = $train;

        return $this;
    }

    /**
     * @return Collection<int, Passage>
     */
    #[Groups(['trajet:read'])]
    public function getPassages(): Collection
    {
        return $this->passages;
    }

    public function addPassage(Passage $passage): static
    {
        if (!$this->passages->contains($passage)) {
            $this->passages->add($passage);
            $passage->setTrajet($this);
        }

        return $this;
    }

    public function removePassage(Passage $passage): static
    {
        if ($this->passages->removeElement($passage) && $passage->getTrajet() === $this) {
            $passage->setTrajet(null);
        }

        return $this;
    }
}
