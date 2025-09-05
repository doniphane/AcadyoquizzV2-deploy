<?php

/**
 * Tests pour le service QuizManagementService
 * 
 * Fonctions testées :
 * - getUserQuizzes() : Récupère tous les questionnaires d'un utilisateur
 * - getUserQuiz() : Récupère un questionnaire spécifique d'un utilisateur
 * - createQuiz() : Crée un nouveau questionnaire avec validation
 * - updateQuiz() : Met à jour un questionnaire existant
 * - deleteQuiz() : Supprime un questionnaire
 * - toggleQuizStatus() : Active/désactive un questionnaire
 * - serializeQuiz() : Sérialise un questionnaire avec ou sans questions
 * - getQuizAttempts() : Récupère les tentatives d'un questionnaire
 */

namespace App\Tests\Service;

use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use App\Entity\Question;
use App\Entity\Reponse;
use App\Entity\TentativeQuestionnaire;
use App\Repository\QuestionnaireRepository;
use App\Repository\TentativeQuestionnaireRepository;
use App\Service\QuizManagementService;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolation;

class QuizManagementServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private EntityManagerInterface $entityManager;
    private QuizManagementService $service;
    private QuestionnaireRepository $questionnaireRepository;
    private TentativeQuestionnaireRepository $tentativeRepository;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();

        $this->entityManager = $container->get('doctrine')->getManager();

        // Nettoyer la base de données au début de chaque test
        $this->clearDatabase($this->entityManager);
        $this->resetAutoIncrement($this->entityManager);

        $repository = $this->entityManager->getRepository(Questionnaire::class);
        assert($repository instanceof QuestionnaireRepository);
        $this->questionnaireRepository = $repository;

        $tentativeRepo = $this->entityManager->getRepository(TentativeQuestionnaire::class);
        assert($tentativeRepo instanceof TentativeQuestionnaireRepository);
        $this->tentativeRepository = $tentativeRepo;

        // Créer un mock du validator pour les tests
        $validatorMock = $this->createMock(ValidatorInterface::class);
        $validatorMock->method('validate')->willReturn(new ConstraintViolationList());
        $this->validator = $validatorMock;

        $this->service = new QuizManagementService(
            $this->questionnaireRepository,
            $this->tentativeRepository,
            $this->entityManager,
            $this->validator
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Nettoyer la base de données
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('DELETE FROM questionnaire');
        $connection->executeStatement('DELETE FROM utilisateur');
        $connection->executeStatement('DELETE FROM tentative_questionnaire');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->entityManager->close();
    }

    private function createTestUser(string $email = 'test@example.com'): Utilisateur
    {
        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('$2y$10$dummy.hash.for.testing.purpose.only'); // Hash factice pour les tests

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createTestQuestionnaire(Utilisateur $creator, array $data = []): Questionnaire
    {
        $questionnaire = new Questionnaire();
        $questionnaire->setTitre($data['titre'] ?? 'Quiz de Test Service');
        $questionnaire->setDescription($data['description'] ?? 'Description test service');
        $questionnaire->setEstActif($data['estActif'] ?? true);
        $questionnaire->setEstDemarre($data['estDemarre'] ?? false);
        $questionnaire->setScorePassage($data['scorePassage'] ?? 70);
        $questionnaire->setCreePar($creator);

        $this->entityManager->persist($questionnaire);
        $this->entityManager->flush();

        return $questionnaire;
    }

    public function testGetUserQuizzes(): void
    {
        $user = $this->createTestUser();
        $quiz1 = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Service 1']);
        $quiz2 = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Service 2']);

        $result = $this->service->getUserQuizzes($user);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $titres = array_map(fn($q) => $q->getTitre(), $result);
        $this->assertContains('Quiz Service 1', $titres);
        $this->assertContains('Quiz Service 2', $titres);

        echo " Test getUserQuizzes service : PASS\n";
    }

    public function testGetUserQuiz(): void
    {
        $user = $this->createTestUser();
        $quiz = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Unique Service']);

        $result = $this->service->getUserQuiz($quiz->getId(), $user);

        $this->assertNotNull($result);
        $this->assertEquals($quiz->getId(), $result->getId());
        $this->assertEquals('Quiz Unique Service', $result->getTitre());

        echo " Test getUserQuiz service : PASS\n";
    }

    public function testCreateQuizSuccess(): void
    {
        $user = $this->createTestUser();
        $data = [
            'title' => 'Nouveau Quiz Service',
            'description' => 'Description nouveau quiz',
            'isActive' => true,
            'isStarted' => false,
            'scorePassage' => 75
        ];

        $result = $this->service->createQuiz($data, $user);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Questionnaire::class, $result['quiz']);
        $this->assertEquals('Nouveau Quiz Service', $result['quiz']->getTitre());
        $this->assertEquals('Description nouveau quiz', $result['quiz']->getDescription());
        $this->assertTrue($result['quiz']->isActive());
        $this->assertFalse($result['quiz']->isStarted());
        $this->assertEquals(75, $result['quiz']->getScorePassage());
        $this->assertEquals($user, $result['quiz']->getCreePar());
        $this->assertNotNull($result['quiz']->getCodeAcces());

        echo " Test createQuiz success service : PASS\n";
    }

    public function testCreateQuizWithDefaultValues(): void
    {
        $user = $this->createTestUser();
        $data = ['title' => 'Quiz Minimal'];

        $result = $this->service->createQuiz($data, $user);

        $this->assertTrue($result['success']);
        $quiz = $result['quiz'];

        $this->assertEquals('Quiz Minimal', $quiz->getTitre());
        $this->assertTrue($quiz->isActive()); // Défaut
        $this->assertFalse($quiz->isStarted()); // Défaut
        $this->assertEquals(50, $quiz->getScorePassage()); // Défaut du service

        echo " Test createQuiz avec valeurs par défaut : PASS\n";
    }

    public function testCreateQuizValidationError(): void
    {
        $user = $this->createTestUser();
        $data = [
            'title' => 'AB', // Trop court
            'scorePassage' => 150 // Trop élevé
        ];

        // Créer un validator qui retourne des erreurs pour ce test
        $violation1 = new ConstraintViolation(
            'Le titre doit faire au moins 3 caractères',
            null,
            [],
            null,
            'titre',
            'AB'
        );
        $violation2 = new ConstraintViolation(
            'Le score de passage doit être entre 0 et 100',
            null,
            [],
            null,
            'scorePassage',
            150
        );

        $violationsList = new ConstraintViolationList([$violation1, $violation2]);

        $validatorWithErrors = $this->createMock(ValidatorInterface::class);
        $validatorWithErrors->method('validate')->willReturn($violationsList);

        // Créer un nouveau service avec le validator qui retourne des erreurs
        $serviceWithErrors = new QuizManagementService(
            $this->questionnaireRepository,
            $this->tentativeRepository,
            $this->entityManager,
            $validatorWithErrors
        );

        $result = $serviceWithErrors->createQuiz($data, $user);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsArray($result['errors']);

        echo " Test createQuiz erreur validation : PASS\n";
    }

    public function testUpdateQuizSuccess(): void
    {
        $user = $this->createTestUser();
        $questionnaire = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Original']);

        $updateData = [
            'title' => 'Quiz Modifié',
            'description' => 'Description modifiée',
            'isActive' => false,
            'isStarted' => true,
            'scorePassage' => 80
        ];

        $result = $this->service->updateQuiz($questionnaire, $updateData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Quiz Modifié', $questionnaire->getTitre());
        $this->assertEquals('Description modifiée', $questionnaire->getDescription());
        $this->assertFalse($questionnaire->isActive());
        $this->assertTrue($questionnaire->isStarted());
        $this->assertEquals(80, $questionnaire->getScorePassage());

        echo " Test updateQuiz success : PASS\n";
    }

    public function testUpdateQuizPartialUpdate(): void
    {
        $user = $this->createTestUser();
        $questionnaire = $this->createTestQuestionnaire($user, [
            'titre' => 'Quiz Original',
            'scorePassage' => 70
        ]);

        $updateData = ['title' => 'Quiz Partiellement Modifié'];

        $result = $this->service->updateQuiz($questionnaire, $updateData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Quiz Partiellement Modifié', $questionnaire->getTitre());
        $this->assertEquals(70, $questionnaire->getScorePassage()); // Inchangé

        echo " Test updateQuiz partiel : PASS\n";
    }

    public function testDeleteQuiz(): void
    {
        $user = $this->createTestUser();
        $questionnaire = $this->createTestQuestionnaire($user, ['titre' => 'Quiz à Supprimer']);
        $id = $questionnaire->getId();

        $this->service->deleteQuiz($questionnaire);

        $deleted = $this->questionnaireRepository->find($id);
        $this->assertNull($deleted);

        echo " Test deleteQuiz : PASS\n";
    }

    public function testToggleQuizStatus(): void
    {
        $user = $this->createTestUser();
        $questionnaire = $this->createTestQuestionnaire($user, ['estActif' => true]);

        // Toggle de actif vers inactif
        $result = $this->service->toggleQuizStatus($questionnaire);
        $this->assertFalse($result->isActive());

        // Toggle de inactif vers actif
        $result = $this->service->toggleQuizStatus($questionnaire);
        $this->assertTrue($result->isActive());

        echo " Test toggleQuizStatus : PASS\n";
    }

    public function testSerializeQuizBasic(): void
    {
        $user = $this->createTestUser();
        $questionnaire = $this->createTestQuestionnaire($user, [
            'titre' => 'Quiz Sérialisé',
            'description' => 'Description sérialisée',
            'scorePassage' => 80
        ]);

        $result = $this->service->serializeQuiz($questionnaire);

        $this->assertIsArray($result);
        $this->assertEquals($questionnaire->getId(), $result['id']);
        $this->assertEquals('Quiz Sérialisé', $result['title']);
        $this->assertEquals('Description sérialisée', $result['description']);
        $this->assertNotNull($result['accessCode']);
        $this->assertTrue($result['isActive']);
        $this->assertFalse($result['isStarted']);
        $this->assertEquals(80, $result['scorePassage']);
        $this->assertArrayHasKey('createdAt', $result);
        $this->assertEquals(0, $result['questionsCount']);
        $this->assertArrayNotHasKey('questions', $result); // Mode basique

        echo " Test serializeQuiz basique : PASS\n";
    }

    public function testSerializeQuizWithQuestions(): void
    {
        $user = $this->createTestUser();
        $questionnaire = $this->createTestQuestionnaire($user, ['titre' => 'Quiz avec Questions']);

        // Ajouter une question avec des réponses
        $question = new Question();
        $question->setTexte('Question de test');
        $question->setNumeroOrdre(1);
        $question->setQuestionnaire($questionnaire);

        $reponse1 = new Reponse();
        $reponse1->setTexte('Réponse correcte');
        $reponse1->setEstCorrecte(true);
        $reponse1->setNumeroOrdre(1);
        $reponse1->setQuestion($question);

        $reponse2 = new Reponse();
        $reponse2->setTexte('Réponse incorrecte');
        $reponse2->setEstCorrecte(false);
        $reponse2->setNumeroOrdre(2);
        $reponse2->setQuestion($question);

        // Ajouter les réponses à la question
        $question->addReponse($reponse1);
        $question->addReponse($reponse2);

        $this->entityManager->persist($question);
        $this->entityManager->persist($reponse1);
        $this->entityManager->persist($reponse2);
        $this->entityManager->flush();

        // Forcer le refresh du questionnaire pour charger les relations
        $this->entityManager->refresh($questionnaire);

        $result = $this->service->serializeQuiz($questionnaire, true);

        $this->assertArrayHasKey('questions', $result);
        $this->assertCount(1, $result['questions']);

        $questionData = $result['questions'][0];
        $this->assertEquals('Question de test', $questionData['texte']);
        $this->assertCount(2, $questionData['reponses']);

        echo " Test serializeQuiz avec questions : PASS\n";
    }

    public function testGetQuizAttempts(): void
    {
        $user = $this->createTestUser();
        $questionnaire = $this->createTestQuestionnaire($user, ['scorePassage' => 70]);

        // Créer une tentative
        $tentative = new TentativeQuestionnaire();
        $tentative->setPrenomParticipant('John');
        $tentative->setNomParticipant('Doe');
        $tentative->setDateDebut(new \DateTimeImmutable());
        $tentative->setDateFin(new \DateTimeImmutable());
        $tentative->setScore(14);
        $tentative->setNombreTotalQuestions(20);
        $tentative->setQuestionnaire($questionnaire);
        $tentative->setUtilisateur($user);

        $this->entityManager->persist($tentative);
        $this->entityManager->flush();

        $attempts = $this->service->getQuizAttempts($questionnaire);

        $this->assertCount(1, $attempts);
        $attempt = $attempts[0];

        $this->assertEquals('John', $attempt['prenomParticipant']);
        $this->assertEquals('Doe', $attempt['nomParticipant']);
        $this->assertEquals(14, $attempt['score']);
        $this->assertEquals(20, $attempt['nombreTotalQuestions']);
        $this->assertEquals(70, $attempt['pourcentage']);
        $this->assertTrue($attempt['estReussie']); // 70% >= 70%

        echo " Test getQuizAttempts : PASS\n";
    }

    /**
     * Lance tous les tests de service
     */
    public function runAllTests(): void
    {
        echo "\n === Tests Service QuizManagement ===\n";

        $this->testGetUserQuizzes();
        $this->testGetUserQuiz();
        $this->testCreateQuizSuccess();
        $this->testCreateQuizWithDefaultValues();
        $this->testCreateQuizValidationError();
        $this->testUpdateQuizSuccess();
        $this->testUpdateQuizPartialUpdate();
        $this->testDeleteQuiz();
        $this->testToggleQuizStatus();
        $this->testSerializeQuizBasic();
        $this->testSerializeQuizWithQuestions();
        $this->testGetQuizAttempts();

        echo "\n Tous les tests Service QuizManagement sont passés !\n";
    }
}
