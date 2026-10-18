<?php

namespace App\Repository;

use App\Entity\Movie;
use App\Entity\MovieCredit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovieCredit>
 */
class MovieCreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovieCredit::class);
    }

    /**
     * Top billed cast for one movie, in billing order.
     * (The old plain-PHP SELECT ... WHERE job='Cast' ORDER BY credit_order.)
     */
    public function findTopCast(Movie $movie, int $limit = 12): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.movie = :movie')
            ->andWhere('c.job = :job')
            ->setParameter('movie', $movie)
            ->setParameter('job', 'Cast')
            ->orderBy('c.creditOrder', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
