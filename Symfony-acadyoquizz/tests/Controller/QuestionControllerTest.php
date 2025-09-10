<?php

namespace App\Tests\Controller;

use App\Entity\Question;
use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use App\Factory\QuestionFactory;
use App\Factory\QuestionnaireFactory;
use App\Factory\ReponseFactory;
use App\Factory\UtilisateurFactory;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

class QuestionControllerTest extends WebTestCase
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

        // Create a user and login to get JWT token
        $this->user = $this->createUser('owner@example.com', 'Secret123');
        $this->token = $this->loginAndGetToken('owner@example.com', 'Secret123');
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
            'firstName' => 'John',
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

    private function authHeaders(): array
    {
        return ['HTTP_Authorization' => 'Bearer '.$this->token];
    }

    public function test_get_collection_success(): void
    {
        // Questionnaire created by authenticated user
        $quiz = QuestionnaireFactory::new([
            'creePar' => $this->user,
            'titre' => 'Quiz Q',
        ])->create();

        // Create 2 questions with ordered answers
        $q1 = QuestionFactory::new([
            'questionnaire' => $quiz,
            'numeroOrdre' => 1,
            'texte' => 'Q1?'
        ])->create();
        ReponseFactory::new(['question' => $q1, 'numeroOrdre' => 1, 'estCorrecte' => true, 'texte' => 'A1'])->create();
        ReponseFactory::new(['question' => $q1, 'numeroOrdre' => 2, 'estCorrecte' => false, 'texte' => 'A2'])->create();

        $q2 = QuestionFactory::new([
            'questionnaire' => $quiz,
            'numeroOrdre' => 2,
            'texte' => 'Q2?'
        ])->create();
        ReponseFactory::new(['question' => $q2, 'numeroOrdre' => 1, 'estCorrecte' => true, 'texte' => 'B1'])->create();
        ReponseFactory::new(['question' => $q2, 'numeroOrdre' => 2, 'estCorrecte' => false, 'texte' => 'B2'])->create();

        $this->client->request('GET', '/api/questions?questionnaire='.$quiz->getId(), [], [], $this->authHeaders());
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('Q1?', $data[0]['texte']);
        $this->assertSame('Q2?', $data[1]['texte']);
    }

    public function test_get_collection_missing_id_returns_400(): void
    {
        $this->client->request('GET', '/api/questions', [], [], $this->authHeaders());
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_get_collection_questionnaire_not_found_returns_404(): void
    {
        $this->client->request('GET', '/api/questions?questionnaire=9999', [], [], $this->authHeaders());
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_get_collection_forbidden_when_not_creator(): void
    {
        // Owner quiz
        $quiz = QuestionnaireFactory::new([
            'creePar' => $this->user,
            'titre' => 'Owner Quiz',
        ])->create();

        // Another user login
        $other = $this->createUser('other@example.com', 'Other123');
        $this->client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'username' => 'other@example.com', 'password' => 'Other123'
        ]));
        $tokenOther = json_decode($this->client->getResponse()->getContent(), true)['token'] ?? '';

        $this->client->request('GET', '/api/questions?questionnaire='.$quiz->getId(), [], [], ['HTTP_Authorization' => 'Bearer '.$tokenOther]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function test_get_item_success(): void
    {
        $quiz = QuestionnaireFactory::new(['creePar' => $this->user])->create();
        $question = QuestionFactory::new(['questionnaire' => $quiz, 'texte' => 'Hello?', 'numeroOrdre' => 1])->create();
        ReponseFactory::new(['question' => $question, 'numeroOrdre' => 1, 'estCorrecte' => true, 'texte' => 'Yes'])->create();
        ReponseFactory::new(['question' => $question, 'numeroOrdre' => 2, 'estCorrecte' => false, 'texte' => 'No'])->create();

        $this->client->request('GET', '/api/questions/'.$question->getId(), [], [], $this->authHeaders());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Hello?', $data['texte']);
        $this->assertArrayHasKey('reponses', $data);
    }

    public function test_get_item_not_found_returns_404(): void
    {
        $this->client->request('GET', '/api/questions/9999', [], [], $this->authHeaders());
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_post_success_creates_question_and_answers(): void
    {
        $quiz = QuestionnaireFactory::new(['creePar' => $this->user])->create();

        $payload = [
            'questionnaire' => $quiz->getId(),
            'texte' => 'New question?',
            'reponses' => [
                ['texte' => 'A', 'estCorrecte' => true, 'numeroOrdre' => 1],
                ['texte' => 'B', 'estCorrecte' => false, 'numeroOrdre' => 2],
            ],
        ];

        $this->client->request('POST', '/api/questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders()), json_encode($payload));
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('New question?', $data['texte']);
        $this->assertCount(2, $data['reponses']);
    }

    public function test_post_invalid_json_returns_400(): void
    {
        $this->client->request('POST', '/api/questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders()), '{ invalid');
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_post_questionnaire_not_found_returns_404(): void
    {
        $payload = ['questionnaire' => 9999, 'texte' => 'Q?'];
        $this->client->request('POST', '/api/questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders()), json_encode($payload));
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_put_success_updates_question_and_answers(): void
    {
        $quiz = QuestionnaireFactory::new(['creePar' => $this->user])->create();
        $question = QuestionFactory::new(['questionnaire' => $quiz, 'texte' => 'Old?', 'numeroOrdre' => 1])->create();
        ReponseFactory::new(['question' => $question, 'numeroOrdre' => 1, 'estCorrecte' => true, 'texte' => 'X'])->create();
        ReponseFactory::new(['question' => $question, 'numeroOrdre' => 2, 'estCorrecte' => false, 'texte' => 'Y'])->create();

        $payload = [
            'texte' => 'Updated?',
            'numeroOrdre' => 5,
            'reponses' => [
                ['texte' => 'N1', 'estCorrecte' => true, 'numeroOrdre' => 1],
                ['texte' => 'N2', 'estCorrecte' => false, 'numeroOrdre' => 2],
            ],
        ];

        $this->client->request('PUT', '/api/questions/'.$question->getId(), [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders()), json_encode($payload));
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Updated?', $data['texte']);
        $this->assertSame(5, $data['numeroOrdre']);
        $this->assertCount(2, $data['reponses']);
    }

    public function test_delete_success(): void
    {
        $quiz = QuestionnaireFactory::new(['creePar' => $this->user])->create();
        $question = QuestionFactory::new(['questionnaire' => $quiz, 'texte' => 'Todelete?', 'numeroOrdre' => 1])->create();
        ReponseFactory::new(['question' => $question, 'numeroOrdre' => 1, 'estCorrecte' => true, 'texte' => 'A'])->create();

        $this->client->request('DELETE', '/api/questions/'.$question->getId(), [], [], $this->authHeaders());
        $this->assertResponseStatusCodeSame(204);
    }
}


