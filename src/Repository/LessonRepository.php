<?php

namespace App\Repository;

use App\Entity\Lesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lesson>
 */
class LessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lesson::class);
    }

    /**
     * Находит следующий урок в курсе по порядку сортировки
     */
    public function findNextLesson(Lesson $currentLesson): ?Lesson
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.course = :course')
            ->andWhere('l.sort > :currentSort')
            ->setParameter('course', $currentLesson->getCourse())
            ->setParameter('currentSort', $currentLesson->getSort() ?? 0)
            ->orderBy('l.sort', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
