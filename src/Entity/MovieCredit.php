<?php

namespace App\Entity;

use App\Repository\MovieCreditRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovieCreditRepository::class)]
#[ORM\Table(name: 'movie_credits')]
class MovieCredit
{
    // ── Junction-with-data ── the PK spans movie_id + person_id + job,
    //    so a person can be both Cast AND Director of the same movie.
    //    All three Id fields are assigned by hand, no GeneratedValue.
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'movie_id', nullable: false)]
    private ?Movie $movie = null;

    #[ORM\Id]
    #[ORM\Column]
    private ?int $personId = null;

    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private ?string $job = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $characterName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePath = null;

    #[ORM\Column]
    private ?int $creditOrder = null;

    public function getMovie(): ?Movie
    {
        return $this->movie;
    }

    public function setMovie(?Movie $movie): static
    {
        $this->movie = $movie;

        return $this;
    }

    public function getPersonId(): ?int
    {
        return $this->personId;
    }

    public function setPersonId(int $personId): static
    {
        $this->personId = $personId;

        return $this;
    }

    public function getJob(): ?string
    {
        return $this->job;
    }

    public function setJob(string $job): static
    {
        $this->job = $job;

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

    public function getCharacterName(): ?string
    {
        return $this->characterName;
    }

    public function setCharacterName(?string $characterName): static
    {
        $this->characterName = $characterName;

        return $this;
    }

    public function getProfilePath(): ?string
    {
        return $this->profilePath;
    }

    public function setProfilePath(?string $profilePath): static
    {
        $this->profilePath = $profilePath;

        return $this;
    }

    public function getCreditOrder(): ?int
    {
        return $this->creditOrder;
    }

    public function setCreditOrder(int $creditOrder): static
    {
        $this->creditOrder = $creditOrder;

        return $this;
    }
}
