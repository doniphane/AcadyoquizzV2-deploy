<?php

namespace App\Repository;

use App\Entity\Questionnaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Questionnaire>
 *
 * @method Questionnaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Questionnaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Questionnaire[]    findAll()
 * @method Questionnaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuestionnaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Questionnaire::class);
    }

    /**
     * Trouve les questionnaires avec leurs questions (optimisé avec jointure)
     */
    public function findWithQuestions(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.questions', 'questions')
            ->addSelect('questions')
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un questionnaire spécifique appartenant à un créateur
     */
    public function findOneByIdAndCreator(int $id, $user): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->where('q.id = :id')
            ->andWhere('q.creePar = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un questionnaire actif par ID
     */
    public function findActiveQuizById(int $id): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->where('q.id = :id')
            ->andWhere('q.estActif = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un questionnaire actif par code d'accès
     */
    public function findActiveQuizByCode(string $code): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->where('q.codeAcces = :code')
            ->andWhere('q.estActif = true')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }
}