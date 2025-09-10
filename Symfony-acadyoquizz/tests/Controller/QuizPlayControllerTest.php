<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Factory\QuestionFactory;
use App\Factory\QuestionnaireFactory;
use App\Factory\ReponseFactory;
use App\Factory\TentativeQuestionnaireFactory;
use App\Factory\UtilisateurFactory;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

class QuizPlayControllerTest extends WebTestCase
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

        $this->user = $this->createUser('player@example.com', 'Secret123');
        $this->token = $this->loginAndGetToken('player@example.com', 'Secret123');
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
            'firstName' => 'Player',
            'lastName' => 'One',
        ])->create();
    }

    private function auth(): array
    {
        return ['HTTP_Authorization' => 'Bearer '.$this->token];
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => $password,
        ]));
        return json_decode($this->client->getResponse()->getContent(), true)['token'] ?? '';
    }

    private function seedPlayableQuiz(bool $active = true): array
    {
        $quiz = QuestionnaireFactory::new([
            'creePar' => $this->user,
            'estActif' => $active,
            'estDemarre' => false,
            'titre' => 'Quiz Play',
        ])->create();

        $q1 = QuestionFactory::new(['questionnaire' => $quiz, 'numeroOrdre' => 1, 'texte' => 'Q1?'])->create();
        $r1 = ReponseFactory::new(['question' => $q1, 'numeroOrdre' => 1, 'estCorrecte' => true, 'texte' => 'A1'])->create();
        $r2 = ReponseFactory::new(['question' => $q1, 'numeroOrdre' => 2, 'estCorrecte' => false, 'texte' => 'A2'])->create();

        return [$quiz, $q1, $r1, $r2];
    }

    public function test_get_available_quizzes_success(): void
    {
        $this->seedPlayableQuiz(true);
        $this->client->request('GET', '/api/quizzes', [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertArrayHasKey('title', $data[0]);
    }

    public function test_get_quiz_by_id_success(): void
    {
        [$quiz] = $this->seedPlayableQuiz(true);
        $this->client->request('GET', '/api/quizzes/play/'.$quiz->getId(), [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('questions', $data);
        $this->assertGreaterThanOrEqual(1, count($data['questions']));
    }

    public function test_get_quiz_by_id_not_found(): void
    {
        $this->client->request('GET', '/api/quizzes/play/9999', [], [], $this->auth());
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_get_quiz_by_code_success(): void
    {
        [$quiz] = $this->seedPlayableQuiz(true);
        $this->client->request('GET', '/api/quizzes/play/code/'.$quiz->getCodeAcces(), [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($quiz->getId(), $data['id']);
    }

    public function test_get_quiz_by_code_not_found(): void
    {
        $this->client->request('GET', '/api/quizzes/play/code/BAD123', [], [], $this->auth());
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_submit_quiz_success(): void
    {
        [$quiz, $q1, $r1, $r2] = $this->seedPlayableQuiz(true);
        $payload = [
            'reponses' => [
                [ 'questionId' => $q1->getId(), 'reponseId' => $r1->getId() ],
            ],
            'prenomParticipant' => 'Jean',
            'nomParticipant' => 'Dupont',
        ];
        $this->client->request('POST', '/api/quizzes/play/'.$quiz->getId().'/submit', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('score', $data);
        $this->assertArrayHasKey('tentativeId', $data);
    }

    public function test_submit_quiz_invalid_json_returns_400(): void
    {
        [$quiz] = $this->seedPlayableQuiz(true);
        $this->client->request('POST', '/api/quizzes/play/'.$quiz->getId().'/submit', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), '{ invalid');
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_submit_quiz_quiz_not_found_returns_404(): void
    {
        $payload = ['reponses' => []];
        $this->client->request('POST', '/api/quizzes/play/9999/submit', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_get_results_success(): void
    {
        [$quiz, $q1, $r1] = $this->seedPlayableQuiz(true);
        // Simuler une tentative via TentativeQuestionnaireFactory
        $tentative = TentativeQuestionnaireFactory::new([
            'questionnaire' => $quiz,
            'utilisateur' => $this->user,
        ])->create();
        $tentative->addReponseUtilisateur((new \App\Entity\ReponseUtilisateur())
            ->setTentativeQuestionnaire($tentative)
            ->setQuestion($q1)
            ->setReponse($r1));
        $tentative->calculerScore();

        $this->client->request('GET', '/api/quizzes/play/results/'.$tentative->getId(), [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('resultats', $data);
    }

    public function test_get_results_not_found_returns_404(): void
    {
        $this->client->request('GET', '/api/quizzes/play/results/9999', [], [], $this->auth());
        $this->assertResponseStatusCodeSame(404);
    }
}


