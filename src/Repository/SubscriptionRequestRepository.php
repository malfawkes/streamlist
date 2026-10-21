<?php

namespace App\Repository;

use App\Entity\SubscriptionRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionRequest>
 */
class SubscriptionRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionRequest::class);
    }

    public function findPendingForUser(User $user): ?SubscriptionRequest
    {
        return $this->findOneBy([
            'user'   => $user,
            'status' => SubscriptionRequest::STATUS_PENDING,
        ]);
    }
}