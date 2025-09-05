<?php

namespace App\Tests\Fixtures;

use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use App\Entity\Question;
use App\Entity\Reponse;
use Doctrine\ORM\EntityManagerInterface;

class QuestionnaireTestFixtures
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Nettoie toutes les données de test de la base de données
     */
    public function clearFixtures(): void
    {
        // Désactiver les contraintes de clés étrangères temporairement
        $this->entityManager->getConnection()->executeStatement('SET foreign_key_checks = 0');

        // Nettoyer les tables dans le bon ordre (en tenant compte des relations)
        $this->entityManager->createQuery('DELETE FROM App\Entity\Question')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Questionnaire')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Utilisateur')->execute();

        // Réactiver les contraintes de clés étrangères
        $this->entityManager->getConnection()->executeStatement('SET foreign_key_checks = 1');

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Crée un utilisateur de test
     */
    public function createTestUser(array $data = []): Utilisateur
    {
        $user = new Utilisateur();
        $user->setEmail($data['email'] ?? 'test@example.com');
        $user->setLastName($data['lastName'] ?? 'Test');
        $user->setFirstName($data['firstName'] ?? 'User');
        $user->setRoles($data['roles'] ?? ['ROLE_USER']);
        $user->setPassword($data['password'] ?? '$2y$10$dummy.hash.for.testing.purpose.only');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Crée un questionnaire de test
     */
    public function createTestQuestionnaire(Utilisateur $creator, array $data = []): Questionnaire
    {
        $questionnaire = new Questionnaire();
        $questionnaire->setTitre($data['titre'] ?? 'Quiz de Test');
        $questionnaire->setDescription($data['description'] ?? 'Description du quiz de test');
        $questionnaire->setCreePar($creator);
        $questionnaire->setCodeAcces($data['codeAcces'] ?? 'TEST' . rand(1000, 9999));
        $questionnaire->setEstActif($data['estActif'] ?? true);
        $questionnaire->setEstDemarre($data['estDemarre'] ?? false);
        $questionnaire->setScorePassage($data['scorePassage'] ?? 50);
        $questionnaire->setDateCreation($data['dateCreation'] ?? new \DateTimeImmutable());

        // Les dates de début/fin ne sont pas dans l'entité actuelle
        // Supprimé car ces méthodes n'existent pas

        $this->entityManager->persist($questionnaire);
        $this->entityManager->flush();

        return $questionnaire;
    }

    /**
     * Crée une question de test avec ses réponses
     */
    public function createTestQuestion(Questionnaire $questionnaire, array $data = []): Question
    {
        $question = new Question();
        $question->setTexte($data['texte'] ?? 'Question de test ?');
        $question->setQuestionnaire($questionnaire);
        $question->setNumeroOrdre($data['ordre'] ?? 1);

        $this->entityManager->persist($question);
        $this->entityManager->flush();

        // Créer les réponses pour cette question
        $reponses = $data['reponses'] ?? [
            ['texte' => 'Réponse A', 'estCorrecte' => true, 'ordre' => 1],
            ['texte' => 'Réponse B', 'estCorrecte' => false, 'ordre' => 2],
            ['texte' => 'Réponse C', 'estCorrecte' => false, 'ordre' => 3],
        ];

        foreach ($reponses as $reponseData) {
            $this->createTestReponse($question, $reponseData);
        }

        return $question;
    }

    /**
     * Crée une réponse de test
     */
    public function createTestReponse(Question $question, array $data = []): Reponse
    {
        $reponse = new Reponse();
        $reponse->setTexte($data['texte'] ?? 'Réponse de test');
        $reponse->setEstCorrecte($data['estCorrecte'] ?? false);
        $reponse->setNumeroOrdre($data['ordre'] ?? 1);
        $reponse->setQuestion($question);

        $this->entityManager->persist($reponse);
        $this->entityManager->flush();

        return $reponse;
    }

    /**
     * Crée un set complet de données de test (utilisateur + questionnaire + questions)
     */
    public function createCompleteTestData(): array
    {
        $user = $this->createTestUser();

        $questionnaire = $this->createTestQuestionnaire($user, [
            'titre' => 'Quiz Complet de Test',
            'description' => 'Un quiz avec plusieurs questions',
        ]);

        $questions = [];
        for ($i = 1; $i <= 3; $i++) {
            $questions[] = $this->createTestQuestion($questionnaire, [
                'texte' => "Question $i de test ?",
                'ordre' => $i,
            ]);
        }

        return [
            'user' => $user,
            'questionnaire' => $questionnaire,
            'questions' => $questions,
        ];
    }

    /**
     * Charge les fixtures de base pour les tests
     * Crée les données spécifiques attendues par les tests
     */
    public function loadFixtures(): array
    {
        // Créer des utilisateurs de test
        $user1 = $this->createTestUser(['email' => 'user1@test.com']);
        $user2 = $this->createTestUser(['email' => 'user2@test.com']);

        // Créer des questionnaires avec des codes spécifiques attendus par les tests
        $quizActif = $this->createTestQuestionnaire($user1, [
            'titre' => 'Quiz Test Actif',
            'codeAcces' => 'TEST001',
            'estActif' => true,
            'estDemarre' => false,
        ]);

        $quizDemarre = $this->createTestQuestionnaire($user1, [
            'titre' => 'Quiz Test Démarré',
            'codeAcces' => 'TEST002',
            'estActif' => true,
            'estDemarre' => true,
        ]);

        $quizInactif = $this->createTestQuestionnaire($user2, [
            'titre' => 'Quiz Test Inactif',
            'codeAcces' => 'TEST003',
            'estActif' => false,
            'estDemarre' => false,
        ]);

        // Pour les tests d'active quiz
        $activeQuiz = $this->createTestQuestionnaire($user1, [
            'titre' => 'Quiz Actif',
            'codeAcces' => 'ACTIVE',
            'estActif' => true,
        ]);

        $inactiveQuiz = $this->createTestQuestionnaire($user1, [
            'titre' => 'Quiz Inactif',
            'codeAcces' => 'INACTIVE',
            'estActif' => false,
        ]);

        // Créer des questions pour les tests avec questions
        $question1 = $this->createTestQuestion($quizActif, [
            'texte' => 'Question 1 de test ?',
            'ordre' => 1,
        ]);

        $question2 = $this->createTestQuestion($quizActif, [
            'texte' => 'Question 2 de test ?',
            'ordre' => 2,
        ]);

        return [
            'users' => [$user1, $user2],
            'questionnaires' => [$quizActif, $quizDemarre, $quizInactif, $activeQuiz, $inactiveQuiz],
            'questions' => [$question1, $question2],
        ];
    }
}