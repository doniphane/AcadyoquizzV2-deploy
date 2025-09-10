<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Factory\QuestionFactory;
use App\Factory\QuestionnaireFactory;
use App\Factory\ReponseFactory;
use App\Factory\ReponseUtilisateurFactory;
use App\Factory\TentativeQuestionnaireFactory;
use App\Factory\UtilisateurFactory;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

class QuizHistoryControllerTest extends WebTestCase
{
    use Factories;
    use DatabaseTestTrait;

    private ?KernelBrowser $client = null;
    private ?string $token = null;
    private ?Utilisateur $user = null;

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);
        $this->resetAutoIncrement($em);

        $this->user = $this->createUser('history.user@example.com', 'Secret123');
        $this->token = $this->loginAndGetToken('history.user@example.com', 'Secret123');
    }

    private function createUser(string $email, string $plainPassword): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $tmp = new Utilisateur();
        $hash = $hasher->hashPassword($tmp, $plainPassword);

        return UtilisateurFactory::new([
            'email' => $email,
            'roles' => ['ROLE_USER'],
            'password' => $hash,
            'firstName' => 'Jane',
            'lastName' => 'Doe',
        ])->create();
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => $password,
        ]));
        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'] ?? '';
    }

    private function auth(): array
    {
        return ['HTTP_Authorization' => 'Bearer '.$this->token];
    }

    private function seedOneAttempt(bool $success = true): int
    {
        $quiz = QuestionnaireFactory::new([
            'creePar' => $this->user,
            'titre' => 'Quiz H'
        ])->create();

        $q = QuestionFactory::new([
            'questionnaire' => $quiz,
            'texte' => 'Capitale de la France?',
            'numeroOrdre' => 1,
        ])->create();
        $r1 = ReponseFactory::new(['question' => $q, 'texte' => 'Paris', 'estCorrecte' => true, 'numeroOrdre' => 1])->create();
        $r2 = ReponseFactory::new(['question' => $q, 'texte' => 'Lyon', 'estCorrecte' => false, 'numeroOrdre' => 2])->create();

        $attempt = TentativeQuestionnaireFactory::new([
            'questionnaire' => $quiz,
            'utilisateur' => $this->user,
        ])->create();

        // Sélection de réponse utilisateur
        ReponseUtilisateurFactory::new([
            'tentativeQuestionnaire' => $attempt,
            'question' => $q,
            'reponse' => $success ? $r1 : $r2,
        ])->create();

        // Calculer le score (via entité)
        $attempt->calculerScore();

        return $attempt->getId();
    }

    public function test_get_history_collection_success(): void
    {
        $this->seedOneAttempt(true);
        $this->client->request('GET', '/api/quizzes/history', [], [], $this->auth());
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('score', $first);
        $this->assertArrayHasKey('pourcentage', $first);
    }

    public function test_get_history_item_success(): void
    {
        $id = $this->seedOneAttempt(true);
        $this->client->request('GET', '/api/quizzes/history/'.$id, [], [], $this->auth());
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('reponsesDetails', $data);
        $this->assertIsArray($data['reponsesDetails']);
        $this->assertGreaterThanOrEqual(1, count($data['reponsesDetails']));
        $first = $data['reponsesDetails'][0];
        $this->assertArrayHasKey('questionTexte', $first);
        $this->assertArrayHasKey('reponseUtilisateurTexte', $first);
        $this->assertArrayHasKey('reponseCorrecteTexte', $first);
        $this->assertArrayHasKey('estCorrecte', $first);
    }

    public function test_get_history_item_not_found_returns_404(): void
    {
        $this->client->request('GET', '/api/quizzes/history/9999', [], [], $this->auth());
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_get_history_stats_success(): void
    {
        $this->seedOneAttempt(true);
        $this->seedOneAttempt(false);
        $this->client->request('GET', '/api/quizzes/history/stats', [], [], $this->auth());
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('totalAttempts', $data);
        $this->assertArrayHasKey('averageScore', $data);
        $this->assertArrayHasKey('passedAttempts', $data);
        $this->assertArrayHasKey('passRate', $data);
    }

    public function test_history_requires_auth(): void
    {
        $this->client->request('GET', '/api/quizzes/history');
        $this->assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/quizzes/history/123');
        $this->assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/quizzes/history/stats');
        $this->assertResponseStatusCodeSame(401);
    }
}


