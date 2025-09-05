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
     * Sauvegarde un questionnaire
     */
    public function save(Questionnaire $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un questionnaire
     */
    public function remove(Questionnaire $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
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
     * Trouve un questionnaire par son code d'accès
     */
    public function findByCodeAcces(string $code): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->where('q.codeAcces = :code')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les questionnaires actifs
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.estActif = true')
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les questionnaires démarrés
     */
    public function findDemarres(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.estDemarre = true')
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les questionnaires d'un utilisateur par son ID
     */
    public function findByUtilisateur(int $userId): array
    {
        return $this->createQueryBuilder('q')
            ->join('q.creePar', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les questionnaires créés par un utilisateur
     */
    public function findByCreator($user): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.creePar = :user')
            ->setParameter('user', $user)
            ->orderBy('q.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les questionnaires actifs
     */
    public function findActiveQuizzes(): array
    {
        return $this->findActifs(); // Réutilise la méthode existante
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