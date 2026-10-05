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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Movie::class);
    }

    /**
     * The homepage trending strip: newest RELEASED movies.
     */
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

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('count(m.id)')          // ← NEW: COUNT via the query builder
            ->getQuery()
            ->getSingleScalarResult();       // ← NEW: returns ONE value, not objects
            //    (your fetchColumn() equivalent — same job: "give me the single number")
    }

    public function findHero(): ?Movie
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.backdropPath IS NOT NULL')       // Twig/ORM: IS NOT NULL, no parameter needed
            ->andWhere('m.rating IS NOT NULL')
            ->orderBy('m.rating', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();      // ← one Movie object or null — your fetch() equivalent
    }    

//    /**
//     * @return Movie[] Returns an array of Movie objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Movie
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
