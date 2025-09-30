<?php

namespace App\Repository;

use App\Entity\UserApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserApiToken::class);
    }

    public function save(UserApiToken $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveTokenByUser(int $userId): ?UserApiToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :userId')
            ->andWhere('t.isActive = true')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deactivateAllTokensByUser(int $userId): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.isActive', ':false')
            ->where('t.user = :userId')
            ->setParameter('false', false)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
