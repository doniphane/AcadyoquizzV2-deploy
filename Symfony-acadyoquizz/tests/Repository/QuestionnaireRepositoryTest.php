<?php

/**
 * Tests pour le repository QuestionnaireRepository
 * 
 * Fonctions testées :
 * - save() et remove() : Persistence et suppression d'entités
 * - findByCodeAcces() : Recherche par code d'accès
 * - findActifs() : Récupère les questionnaires actifs
 * - findDemarres() : Récupère les questionnaires démarrés
 * - findByUtilisateur() : Récupère les questionnaires d'un utilisateur par ID
 * - findByCreator() : Récupère les questionnaires d'un créateur
 * - findOneByIdAndCreator() : Récupère un questionnaire par ID et créateur
 * - findActiveQuizzes() : Récupère tous les questionnaires actifs
 * - findActiveQuizById() : Récupère un questionnaire actif par ID
 * - findActiveQuizByCode() : Récupère un questionnaire actif par code
 * - findWithQuestions() : Récupère les questionnaires avec leurs questions
 */

namespace App\Tests\Repository;

use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use App\Repository\QuestionnaireRepository;
use App\Tests\Fixtures\QuestionnaireTestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class QuestionnaireRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private QuestionnaireRepository $repository;
    private QuestionnaireTestFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $repository = $this->entityManager->getRepository(Questionnaire::class);
        assert($repository instanceof QuestionnaireRepository);
        $this->repository = $repository;

        // Initialiser les fixtures
        $this->fixtures = new QuestionnaireTestFixtures($this->entityManager);

        // Nettoyer la base avant de charger les fixtures
        $this->fixtures->clearFixtures();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Nettoyer les fixtures
        if (isset($this->fixtures)) {
            $this->fixtures->clearFixtures();
        }

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
        $questionnaire->setTitre($data['titre'] ?? 'Quiz de Test');
        $questionnaire->setDescription($data['description'] ?? 'Description test');
        $questionnaire->setCodeAcces($data['codeAcces'] ?? $this->generateRandomCode());
        $questionnaire->setEstActif($data['estActif'] ?? true);
        $questionnaire->setEstDemarre($data['estDemarre'] ?? false);
        $questionnaire->setScorePassage($data['scorePassage'] ?? 70);
        $questionnaire->setCreePar($creator);

        $this->entityManager->persist($questionnaire);
        $this->entityManager->flush();

        return $questionnaire;
    }

    private function generateRandomCode(): string
    {
        return strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
    }

    public function testSaveAndRemove(): void
    {
        $user = $this->createTestUser();
        $questionnaire = new Questionnaire();
        $questionnaire->setTitre('Test Save');
        $questionnaire->setCreePar($user);

        // Test save
        $this->repository->save($questionnaire, true);
        $this->assertNotNull($questionnaire->getId());

        $found = $this->repository->find($questionnaire->getId());
        $this->assertNotNull($found);
        $this->assertEquals('Test Save', $found->getTitre());

        // Test remove
        $id = $questionnaire->getId();
        $this->repository->remove($questionnaire, true);

        $removed = $this->repository->find($id);
        $this->assertNull($removed);

        echo " Test save/remove repository : PASS\n";
    }

    public function testFindByCodeAcces(): void
    {
        // Charger les fixtures
        $fixturesData = $this->fixtures->loadFixtures();

        // Tester la recherche par code d'accès existant
        $found = $this->repository->findByCodeAcces('TEST001');
        $this->assertNotNull($found);
        $this->assertEquals('Quiz Test Actif', $found->getTitre());

        // Tester la recherche par code d'accès inexistant
        $notFound = $this->repository->findByCodeAcces('NOTFOUND');
        $this->assertNull($notFound);

        echo " Test findByCodeAcces avec fixtures : PASS\n";
    }

    public function testFindActifs(): void
    {
        // Charger les fixtures
        $fixturesData = $this->fixtures->loadFixtures();

        $actifs = $this->repository->findActifs();

        // Il devrait y avoir au moins 2 quiz actifs dans nos fixtures
        $this->assertGreaterThanOrEqual(2, count($actifs));

        $titresActifs = array_map(fn($q) => $q->getTitre(), $actifs);
        $this->assertContains('Quiz Test Actif', $titresActifs);
        $this->assertContains('Quiz Test Démarré', $titresActifs);

        // Le quiz inactif ne devrait pas être dans les résultats
        $this->assertNotContains('Quiz Test Inactif', $titresActifs);

        echo " Test findActifs avec fixtures : PASS\n";
    }

    public function testFindDemarres(): void
    {
        $user = $this->createTestUser();

        $startedQuiz = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Démarré', 'estDemarre' => true]);
        $notStartedQuiz = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Non Démarré', 'estDemarre' => false]);

        $demarres = $this->repository->findDemarres();

        $titresDemarres = array_map(fn($q) => $q->getTitre(), $demarres);
        $this->assertContains('Quiz Démarré', $titresDemarres);

        echo " Test findDemarres : PASS\n";
    }

    public function testFindByUtilisateur(): void
    {
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');

        $quiz1 = $this->createTestQuestionnaire($user1, ['titre' => 'Quiz User1 - 1']);
        $quiz2 = $this->createTestQuestionnaire($user1, ['titre' => 'Quiz User1 - 2']);
        $quiz3 = $this->createTestQuestionnaire($user2, ['titre' => 'Quiz User2']);

        $user1Quizzes = $this->repository->findByUtilisateur($user1->getId());

        $this->assertCount(2, $user1Quizzes);

        $titres = array_map(fn($q) => $q->getTitre(), $user1Quizzes);
        $this->assertContains('Quiz User1 - 1', $titres);
        $this->assertContains('Quiz User1 - 2', $titres);

        echo " Test findByUtilisateur : PASS\n";
    }

    public function testFindByCreator(): void
    {
        $user = $this->createTestUser();

        $quiz1 = $this->createTestQuestionnaire($user, ['titre' => 'Premier Quiz']);
        $quiz2 = $this->createTestQuestionnaire($user, ['titre' => 'Deuxième Quiz']);

        $quizzes = $this->repository->findByCreator($user);

        $this->assertCount(2, $quizzes);

        $titres = array_map(fn($q) => $q->getTitre(), $quizzes);
        $this->assertContains('Premier Quiz', $titres);
        $this->assertContains('Deuxième Quiz', $titres);

        echo " Test findByCreator : PASS\n";
    }

    public function testFindOneByIdAndCreator(): void
    {
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');

        $quiz = $this->createTestQuestionnaire($user1, ['titre' => 'Quiz de User1']);

        // Test avec le bon créateur
        $found = $this->repository->findOneByIdAndCreator($quiz->getId(), $user1);
        $this->assertNotNull($found);
        $this->assertEquals($quiz->getId(), $found->getId());

        // Test avec un mauvais créateur
        $notFound = $this->repository->findOneByIdAndCreator($quiz->getId(), $user2);
        $this->assertNull($notFound);

        echo " Test findOneByIdAndCreator : PASS\n";
    }

    public function testFindActiveQuizzes(): void
    {
        $user = $this->createTestUser();

        $activeQuiz = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Actif Test', 'estActif' => true]);
        $inactiveQuiz = $this->createTestQuestionnaire($user, ['titre' => 'Quiz Inactif Test', 'estActif' => false]);

        $activeQuizzes = $this->repository->findActiveQuizzes();

        $titresActifs = array_map(fn($q) => $q->getTitre(), $activeQuizzes);
        $this->assertContains('Quiz Actif Test', $titresActifs);

        echo " Test findActiveQuizzes : PASS\n";
    }

    public function testFindActiveQuizById(): void
    {
        $user = $this->createTestUser();

        $activeQuiz = $this->createTestQuestionnaire($user, ['estActif' => true]);
        $inactiveQuiz = $this->createTestQuestionnaire($user, ['estActif' => false]);

        // Test avec quiz actif
        $found = $this->repository->findActiveQuizById($activeQuiz->getId());
        $this->assertNotNull($found);
        $this->assertEquals($activeQuiz->getId(), $found->getId());

        // Test avec quiz inactif
        $notFound = $this->repository->findActiveQuizById($inactiveQuiz->getId());
        $this->assertNull($notFound);

        echo " Test findActiveQuizById : PASS\n";
    }

    public function testFindActiveQuizByCode(): void
    {
        $user = $this->createTestUser();

        $activeQuiz = $this->createTestQuestionnaire($user, ['codeAcces' => 'ACTIVE', 'estActif' => true]);
        $inactiveQuiz = $this->createTestQuestionnaire($user, ['codeAcces' => 'INACTIVE', 'estActif' => false]);

        // Test avec quiz actif
        $found = $this->repository->findActiveQuizByCode('ACTIVE');
        $this->assertNotNull($found);
        $this->assertEquals('ACTIVE', $found->getCodeAcces());

        // Test avec quiz inactif
        $notFound = $this->repository->findActiveQuizByCode('INACTIVE');
        $this->assertNull($notFound);

        // Test case insensitive
        $foundLower = $this->repository->findActiveQuizByCode('active');
        $this->assertNotNull($foundLower);

        echo " Test findActiveQuizByCode : PASS\n";
    }

    public function testWithQuestions(): void
    {
        $user = $this->createTestUser();
        $quiz = $this->createTestQuestionnaire($user, ['titre' => 'Quiz avec Questions']);

        $quizzesWithQuestions = $this->repository->findWithQuestions();

        $this->assertIsArray($quizzesWithQuestions);

        $titres = array_map(fn($q) => $q->getTitre(), $quizzesWithQuestions);
        $this->assertContains('Quiz avec Questions', $titres);

        echo " Test findWithQuestions : PASS\n";
    }

    /**
     * Lance tous les tests de repository
     */
    public function runAllTests(): void
    {
        echo "\n === Tests Repository Questionnaire ===\n";

        $this->testSaveAndRemove();
        $this->testFindByCodeAcces();
        $this->testFindActifs();
        $this->testFindDemarres();
        $this->testFindByUtilisateur();
        $this->testFindByCreator();
        $this->testFindOneByIdAndCreator();
        $this->testFindActiveQuizzes();
        $this->testFindActiveQuizById();
        $this->testFindActiveQuizByCode();
        $this->testWithQuestions();

        echo "\n Tous les tests Repository Questionnaire sont passés !\n";
    }
}
