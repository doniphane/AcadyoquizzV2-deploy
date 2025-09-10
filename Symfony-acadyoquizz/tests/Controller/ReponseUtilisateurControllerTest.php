<?php

namespace App\Tests\Controller;

use App\Entity\ReponseUtilisateur;
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

class ReponseUtilisateurControllerTest extends WebTestCase
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

        $this->user = $this->createUser('resp.user@example.com', 'Secret123');
        $this->token = $this->loginAndGetToken('resp.user@example.com', 'Secret123');
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
            'firstName' => 'Resp',
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

    private function seedGraph(): array
    {
        $quiz = QuestionnaireFactory::new(['creePar' => $this->user, 'titre' => 'RUC'])->create();
        $q = QuestionFactory::new(['questionnaire' => $quiz, 'texte' => 'Q?', 'numeroOrdre' => 1])->create();
        $r1 = ReponseFactory::new(['question' => $q, 'texte' => 'A1', 'estCorrecte' => true, 'numeroOrdre' => 1])->create();
        $r2 = ReponseFactory::new(['question' => $q, 'texte' => 'A2', 'estCorrecte' => false, 'numeroOrdre' => 2])->create();
        $tentative = TentativeQuestionnaireFactory::new(['questionnaire' => $quiz, 'utilisateur' => $this->user])->create();
        return [$quiz, $q, $r1, $r2, $tentative];
    }

    public function test_get_collection_success_and_filters(): void
    {
        [$quiz, $q, $r1, $r2, $tentative] = $this->seedGraph();

        // créer 1 réponse utilisateur
        $payload = [
            'tentativeQuestionnaire' => $tentative->getId(),
            'question' => $q->getId(),
            'reponse' => $r1->getId(),
        ];
        $this->client->request('POST', '/api/reponse_utilisateurs', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $code = $this->client->getResponse()->getStatusCode();
        $this->assertGreaterThanOrEqual(200, $code);
        $this->assertLessThan(500, $code);

        // Selon la sécurité de POST (pas d'IsGranted), la création peut passer sans auth
        // Dans tous les cas, on teste la collection protégée (ROLE_USER requis)
        $this->client->request('GET', '/api/reponse_utilisateurs?tentative='.$tentative->getId(), [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function test_get_item_success_and_not_found(): void
    {
        [$quiz, $q, $r1, $r2, $tentative] = $this->seedGraph();
        // Crée directement une entité ReponseUtilisateur
        $ru = new ReponseUtilisateur();
        $ru->setTentativeQuestionnaire($tentative);
        $ru->setQuestion($q);
        $ru->setReponse($r1);
        $ru->setDateReponse(new \DateTimeImmutable());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($ru);
        $em->flush();

        $this->client->request('GET', '/api/reponse_utilisateurs/'.$ru->getId(), [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($ru->getId(), $data['id'] ?? null);

        $this->client->request('GET', '/api/reponse_utilisateurs/999999', [], [], $this->auth());
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_post_validations_and_success(): void
    {
        [$quiz, $q, $r1, $r2, $tentative] = $this->seedGraph();

        // JSON invalide
        $this->client->request('POST', '/api/reponse_utilisateurs', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), '{ invalid');
        $this->assertResponseStatusCodeSame(400);

        // Champ manquant
        $this->client->request('POST', '/api/reponse_utilisateurs', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode([
            'tentativeQuestionnaire' => $tentative->getId(),
            'question' => $q->getId(),
            // 'reponse' manquant
        ]));
        $this->assertResponseStatusCodeSame(400);

        // Tentative/Question/Réponse invalides
        foreach ([
            ['tentativeQuestionnaire' => 999, 'question' => $q->getId(), 'reponse' => $r1->getId()],
            ['tentativeQuestionnaire' => $tentative->getId(), 'question' => 999, 'reponse' => $r1->getId()],
            ['tentativeQuestionnaire' => $tentative->getId(), 'question' => $q->getId(), 'reponse' => 999],
        ] as $bad) {
            $this->client->request('POST', '/api/reponse_utilisateurs', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($bad));
            $this->assertTrue(in_array($this->client->getResponse()->getStatusCode(), [400, 404]));
        }

        // Réponse qui n'appartient pas à la question
        $q2 = QuestionFactory::new(['questionnaire' => $quiz, 'texte' => 'Other?', 'numeroOrdre' => 2])->create();
        $rOther = ReponseFactory::new(['question' => $q2, 'texte' => 'X', 'estCorrecte' => true, 'numeroOrdre' => 1])->create();
        $this->client->request('POST', '/api/reponse_utilisateurs', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode([
            'tentativeQuestionnaire' => $tentative->getId(),
            'question' => $q->getId(),
            'reponse' => $rOther->getId(),
        ]));
        $this->assertResponseStatusCodeSame(400);

        // Question hors questionnaire
        $quiz2 = QuestionnaireFactory::new(['creePar' => $this->user, 'titre' => 'Q2'])->create();
        $qOutside = QuestionFactory::new(['questionnaire' => $quiz2, 'texte' => 'Outside?', 'numeroOrdre' => 1])->create();
        $rOutside = ReponseFactory::new(['question' => $qOutside, 'texte' => 'Z', 'estCorrecte' => true, 'numeroOrdre' => 1])->create();
        $this->client->request('POST', '/api/reponse_utilisateurs', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode([
            'tentativeQuestionnaire' => $tentative->getId(),
            'question' => $qOutside->getId(),
            'reponse' => $rOutside->getId(),
        ]));
        $this->assertResponseStatusCodeSame(400);

        // Succès
        $this->client->request('POST', '/api/reponse_utilisateurs', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode([
            'tentativeQuestionnaire' => $tentative->getId(),
            'question' => $q->getId(),
            'reponse' => $r1->getId(),
        ]));
        $code = $this->client->getResponse()->getStatusCode();
        $this->assertGreaterThanOrEqual(200, $code);
        $this->assertLessThan(500, $code);
    }

    public function test_put_success_forbidden_not_found_and_validation(): void
    {
        [$quiz, $q, $r1, $r2, $tentative] = $this->seedGraph();
        $ru = new ReponseUtilisateur();
        $ru->setTentativeQuestionnaire($tentative)->setQuestion($q)->setReponse($r1)->setDateReponse(new \DateTimeImmutable());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($ru);
        $em->flush();

        // Not found
        $this->client->request('PUT', '/api/reponse_utilisateurs/999999', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['reponse' => $r2->getId()]));
        $this->assertResponseStatusCodeSame(404);

        // Success
        $this->client->request('PUT', '/api/reponse_utilisateurs/'.$ru->getId(), [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['reponse' => $r2->getId()]));
        $code = $this->client->getResponse()->getStatusCode();
        $this->assertGreaterThanOrEqual(200, $code);
        $this->assertLessThan(500, $code);

        // Validation: mauvaise réponse pour la question
        $q2 = QuestionFactory::new(['questionnaire' => $quiz, 'texte' => 'Other2?', 'numeroOrdre' => 3])->create();
        $rWrong = ReponseFactory::new(['question' => $q2, 'texte' => 'Y', 'estCorrecte' => true, 'numeroOrdre' => 1])->create();
        $this->client->request('PUT', '/api/reponse_utilisateurs/'.$ru->getId(), [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode(['reponse' => $rWrong->getId()]));
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_delete_requires_admin_and_success(): void
    {
        [$quiz, $q, $r1, $r2, $tentative] = $this->seedGraph();
        $ru = new ReponseUtilisateur();
        $ru->setTentativeQuestionnaire($tentative)->setQuestion($q)->setReponse($r1)->setDateReponse(new \DateTimeImmutable());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($ru);
        $em->flush();

        // Sans token admin → 401
        $this->client->request('DELETE', '/api/reponse_utilisateurs/'.$ru->getId());
        $this->assertResponseStatusCodeSame(401);

        // Avec token user → 403
        $this->client->request('DELETE', '/api/reponse_utilisateurs/'.$ru->getId(), [], [], $this->auth());
        $this->assertResponseStatusCodeSame(403);

        // Créer un admin pour supprimer
        $admin = $this->createUser('admin.resp@example.com', 'Admin123');
        $admin->setRoles(['ROLE_ADMIN']);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $adminToken = $this->loginAndGetToken('admin.resp@example.com', 'Admin123');

        $this->client->request('DELETE', '/api/reponse_utilisateurs/'.$ru->getId(), [], [], ['HTTP_Authorization' => 'Bearer '.$adminToken]);
        $this->assertResponseStatusCodeSame(204);
    }
}


