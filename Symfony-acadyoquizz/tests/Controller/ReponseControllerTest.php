<?php

namespace App\Tests\Controller;

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

class ReponseControllerTest extends WebTestCase
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

        $this->user = $this->createUser('reponse.user@example.com', 'Secret123');
        $this->token = $this->loginAndGetToken('reponse.user@example.com', 'Secret123');
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
            'firstName' => 'Rep',
            'lastName' => 'User',
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

    private function seedQuestionWithTwoAnswers(): array
    {
        $quiz = QuestionnaireFactory::new(['creePar' => $this->user, 'titre' => 'R'])->create();
        $q = QuestionFactory::new(['questionnaire' => $quiz, 'texte' => 'Q?', 'numeroOrdre' => 1])->create();
        $r1 = ReponseFactory::new(['question' => $q, 'texte' => 'A1', 'estCorrecte' => true, 'numeroOrdre' => 1])->create();
        $r2 = ReponseFactory::new(['question' => $q, 'texte' => 'A2', 'estCorrecte' => false, 'numeroOrdre' => 2])->create();
        return [$quiz, $q, $r1, $r2];
    }

    public function test_get_collection_success(): void
    {
        [$quiz, $q, $r1, $r2] = $this->seedQuestionWithTwoAnswers();
        $this->client->request('GET', '/api/reponses?question='.$q->getId(), [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
    }

    public function test_get_item_success_forbidden_and_not_found(): void
    {
        [$quiz, $q, $r1, $r2] = $this->seedQuestionWithTwoAnswers();
        // success owner
        $this->client->request('GET', '/api/reponses/'.$r1->getId(), [], [], $this->auth());
        $this->assertResponseIsSuccessful();

        // not found
        $this->client->request('GET', '/api/reponses/999999', [], [], $this->auth());
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_post_validations_and_success(): void
    {
        [$quiz, $q] = $this->seedQuestionWithTwoAnswers();

        // JSON invalide
        $this->client->request('POST', '/api/reponses', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), '{ invalid');
        $this->assertResponseStatusCodeSame(400);

        // Question manquante
        $this->client->request('POST', '/api/reponses', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['texte' => 'X']));
        $this->assertResponseStatusCodeSame(400);

        // Question inexistante
        $this->client->request('POST', '/api/reponses', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['question' => 999, 'texte' => 'X']));
        $this->assertResponseStatusCodeSame(404);

        // Accès refusé si autre créateur
        $other = $this->createUser('other@example.com', 'Secret123');
        $otherQuiz = QuestionnaireFactory::new(['creePar' => $other, 'titre' => 'O'])->create();
        $otherQuestion = QuestionFactory::new(['questionnaire' => $otherQuiz, 'texte' => 'OQ?', 'numeroOrdre' => 1])->create();
        $this->client->request('POST', '/api/reponses', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['question' => $otherQuestion->getId(), 'texte' => 'X']));
        $this->assertResponseStatusCodeSame(403);

        // Succès
        $payload = ['question' => $q->getId(), 'texte' => 'New', 'estCorrecte' => true, 'numeroOrdre' => 3];
        $this->client->request('POST', '/api/reponses', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('New', $data['texte'] ?? null);
    }

    public function test_put_validations_success_forbidden_not_found(): void
    {
        [$quiz, $q, $r1, $r2] = $this->seedQuestionWithTwoAnswers();

        // not found
        $this->client->request('PUT', '/api/reponses/999999', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['texte' => 'UP']));
        $this->assertResponseStatusCodeSame(404);

        // forbidden (autre créateur)
        $other = $this->createUser('other2@example.com', 'Secret123');
        $otherQuiz = QuestionnaireFactory::new(['creePar' => $other, 'titre' => 'O2'])->create();
        $otherQuestion = QuestionFactory::new(['questionnaire' => $otherQuiz, 'texte' => 'QO2?', 'numeroOrdre' => 1])->create();
        $otherResp = ReponseFactory::new(['question' => $otherQuestion, 'texte' => 'Z', 'estCorrecte' => false, 'numeroOrdre' => 1])->create();
        $this->client->request('PUT', '/api/reponses/'.$otherResp->getId(), [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['texte' => 'UP']));
        $this->assertResponseStatusCodeSame(403);

        // success
        $payload = ['texte' => 'UP2', 'estCorrecte' => false, 'numeroOrdre' => 5];
        $this->client->request('PUT', '/api/reponses/'.$r1->getId(), [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('UP2', $data['texte'] ?? null);
    }

    public function test_delete_success_forbidden_not_found(): void
    {
        [$quiz, $q, $r1, $r2] = $this->seedQuestionWithTwoAnswers();

        // not found
        $this->client->request('DELETE', '/api/reponses/999999', [], [], $this->auth());
        $this->assertResponseStatusCodeSame(404);

        // forbidden (autre créateur)
        $other = $this->createUser('other3@example.com', 'Secret123');
        $otherQuiz = QuestionnaireFactory::new(['creePar' => $other, 'titre' => 'O3'])->create();
        $otherQuestion = QuestionFactory::new(['questionnaire' => $otherQuiz, 'texte' => 'QO3?', 'numeroOrdre' => 1])->create();
        $otherResp = ReponseFactory::new(['question' => $otherQuestion, 'texte' => 'Z', 'estCorrecte' => false, 'numeroOrdre' => 1])->create();
        $this->client->request('DELETE', '/api/reponses/'.$otherResp->getId(), [], [], $this->auth());
        $this->assertResponseStatusCodeSame(403);

        // success
        $this->client->request('DELETE', '/api/reponses/'.$r2->getId(), [], [], $this->auth());
        $this->assertResponseStatusCodeSame(204);
    }
}


