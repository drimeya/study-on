<?php

namespace App\Repository;

use App\Entity\UserApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserApiToken>
 *
 * @method UserApiToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserApiToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserApiToken[]    findAll()
 * @method UserApiToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
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

    public function remove(UserApiToken $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Находит активный токен для пользователя
     */
    public function findActiveTokenByUser(int $userId): ?UserApiToken
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :userId')
            ->andWhere('t.isActive = :isActive')
            ->andWhere('t.expiresAt > :now OR t.expiresAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('isActive', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Деактивирует все токены пользователя
     */
    public function deactivateAllTokensByUser(int $userId): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.isActive', ':isActive')
            ->where('t.user = :userId')
            ->setParameter('isActive', false)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
