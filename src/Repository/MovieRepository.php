<?php

namespace App\Repository;

use App\Entity\Movie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Movie>
 */
class MovieRepository extends ServiceEntityRepository
{
    // Allow-listed sort map — only strings WE wrote reach the query
    // (your old $sortMap, translated — injection-safe by construction)
    private const SORT_MAP = [
        'date'   => ['m.releaseDate', 'DESC'],
        'rating' => ['m.rating', 'DESC'],
        'title'  => ['m.title', 'ASC'],
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Movie::class);
    }

    // ── homepage: trending strip
    public function findTrending(int $limit = 5): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.releaseDate <= :today')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('m.releaseDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ── homepage: hero (top rated with backdrop) 
    public function findHero(): ?Movie
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.backdropPath IS NOT NULL')
            ->andWhere('m.rating IS NOT NULL')
            ->orderBy('m.rating', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ── homepage: stat 
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ── movies page: the paginated grid (genre + sort aware) 
    public function findPaginated(int $page, int $perPage, ?int $genreId = null, string $sort = 'date'): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.releaseDate <= :today')          // parity: exclude upcoming
            ->setParameter('today', new \DateTime('today'));

        if ($genreId !== null) {
            $qb->innerJoin('m.genres', 'g')
               ->andWhere('g.id = :gid')
               ->setParameter('gid', $genreId);
        }

        [$field, $direction] = self::SORT_MAP[$sort] ?? self::SORT_MAP['date'];

        return $qb->orderBy($field, $direction)
            ->setFirstResult(($page - 1) * $perPage)       
            ->setMaxResults($perPage)                       
            ->getQuery()
            ->getResult();
    }

    // ── movies page: total for pagination math (genre aware) 
    public function countFiltered(?int $genreId = null): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->andWhere('m.releaseDate <= :today')
            ->setParameter('today', new \DateTime('today'));

        if ($genreId !== null) {
            $qb->innerJoin('m.genres', 'g')
               ->andWhere('g.id = :gid')
               ->setParameter('gid', $genreId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Title search. The bound parameter is the entire SQL-injection
     * defense — the Phase 1 PDO doctrine, unchanged in DQL.
     * % and _ are escaped so user input can't act as SQL wildcards.
     */
    public function searchByTitle(string $query, int $limit = 24): array
    {
        // 'LIKE' treats % and _ as commands; strip them of their powers.
        // MySQL's LIKE is case-insensitive by default — no LOWER() needed.
        $escaped = addcslashes($query, '%_\\');

        return $this->createQueryBuilder('m')
            ->andWhere('m.title LIKE :term')
            ->setParameter('term', '%' . $escaped . '%')
            ->orderBy('m.releaseDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}