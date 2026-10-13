<?php

namespace App\Repository;

use App\Entity\Genre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Genre>
 */
class GenreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Genre::class);
    }

    public function findAllWithCounts(): array
    {
        return $this->getEntityManager()->getConnection()
            ->fetchAllAssociative(
                'SELECT g.id, g.name, COUNT(mg.movie_id) AS movie_count
                 FROM genres g
                 LEFT JOIN movie_genres mg ON mg.genre_id = g.id
                 GROUP BY g.id, g.name
                 ORDER BY g.name'
            );
    }
}