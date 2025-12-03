<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
