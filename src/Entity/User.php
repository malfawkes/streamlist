<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column(name: 'password_hash', length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 10)]
    private ?string $tier = 'free';

    #[ORM\Column(name: 'is_admin', type: 'boolean')]
    private ?bool $isAdmin = false;

    #[ORM\Column(name: 'tier_expires_at', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $tierExpiresAt = null;

    #[ORM\Column(name: 'avatar_path', nullable: true)]
    private ?string $avatarPath = null;

    /**
     * Inverse side of WatchlistItem::$user (the owning side).
     * No extra DB column — but it makes app.user.watchlistItems
     * work in Twig (movie_card.html.twig).
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: WatchlistItem::class, fetch: 'EXTRA_LAZY')]
    private Collection $watchlistItems;

    public function __construct()
    {
        $this->isAdmin = false;        // every new user starts non-admin
        $this->tier = 'free';          // move the tier default here too (bonus)
        $this->watchlistItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Roles synthesized from your real columns — no roles column exists.
     * isAdmin = 1  →  ROLE_ADMIN rides along automatically.
     */
    public function getRoles(): array
    {
        return $this->isAdmin ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER'];
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

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

    public function getTier(): ?string
    {
        return $this->tier;
    }

    public function setTier(string $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    public function isAdmin(): ?bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(?bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    public function getTierExpiresAt(): ?\DateTime
    {
        return $this->tierExpiresAt;
    }

    public function setTierExpiresAt(?\DateTime $tierExpiresAt): static
    {
        $this->tierExpiresAt = $tierExpiresAt;

        return $this;
    }

    /**
     * The one true "is this account premium RIGHT NOW?" check.
     *
     * tier = 'premium' AND (no expiry recorded, or the expiry is not past).
     * NULL tier_expires_at counts as active: the admin approval flow
     * sets the tier without an expiry date.
     */
    public function hasActivePremium(): bool
    {
        if (strtolower((string) $this->tier) !== 'premium') {
            return false;
        }

        if ($this->tierExpiresAt === null) {
            return true;                          // no expiry on file → active
        }

        return $this->tierExpiresAt >= new \DateTime('today');
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): self
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }

    /**
     * All watchlist rows owned by this user (movie cards use this
     * to know which movies are already saved).
     */
    public function getWatchlistItems(): Collection
    {
        return $this->watchlistItems;
    }
}