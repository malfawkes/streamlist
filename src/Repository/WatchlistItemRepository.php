<?php

namespace App\Repository;

use App\Entity\WatchlistItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WatchlistItem>
 */
class WatchlistItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WatchlistItem::class);
    }

    /**
     * The user's watchlist — movie + added-date, newest first.
     * (Your old JOIN, through the two ManyToOnes.)
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(User $user, int $movieId): ?WatchlistItem
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.movie = :movieId')        // comparing a FK column directly —
            ->setParameter('user', $user)            // no join needed for the filter
            ->setParameter('movieId', $movieId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('count(w.id)')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}