<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Factory\UtilisateurFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use App\Tests\DatabaseTestTrait;

class AuthTest extends WebTestCase
{
    use Factories;
    use DatabaseTestTrait;

    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($entityManager);
        $this->resetAutoIncrement($entityManager);
    }

    private function createUser(string $email, string $plainPassword): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Create a temporary user to compute a password hash
        $tempUser = new Utilisateur();
        $hashed = $hasher->hashPassword($tempUser, $plainPassword);

        return UtilisateurFactory::new([
            'email' => $email,
            'roles' => ['ROLE_USER'],
            'password' => $hashed,
            'firstName' => 'Test',
            'lastName' => 'User',
        ])->create();
    }

    public function test_login_success_returns_jwt_token(): void
    {
        $client = $this->client;

        $email = 'login.success@example.com';
        $password = 'Secret123';
        $this->createUser($email, $password);

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => $password,
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('token', $data, 'JWT token manquant dans la réponse');
        $this->assertIsString($data['token']);
        $this->assertNotEmpty($data['token']);
    }

    public function test_login_failure_wrong_password(): void
    {
        $client = $this->client;

        $email = 'login.failure@example.com';
        $password = 'Correct123';
        $this->createUser($email, $password);

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => 'WrongPassword',
        ]));

        $this->assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_access_protected_route_with_bearer_token(): void
    {
        $client = $this->client;

        $email = 'me.endpoint@example.com';
        $password = 'Access123';
        $this->createUser($email, $password);

        // 1) login to obtain token
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => $password,
        ]));

        $this->assertResponseIsSuccessful();
        $token = json_decode($client->getResponse()->getContent(), true)['token'] ?? null;
        $this->assertNotEmpty($token);

        // 2) call protected route
        $client->request('GET', '/api/users/me', [], [], [
            'HTTP_Authorization' => 'Bearer '.$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($email, $data['email'] ?? null);
    }

    public function test_login_missing_fields_returns_400(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            // Intentionally missing username/password
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_login_invalid_json_returns_400(): void
    {
        $client = $this->client;

        // Send invalid JSON body
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{ invalid_json');

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_login_sql_injection_attempt_is_rejected(): void
    {
        $client = $this->client;

        $email = 'security.sql@example.com';
        $password = 'Safe123';
        $this->createUser($email, $password);

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => "' OR 1=1; --",
            'password' => 'anything',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function test_me_without_token_returns_401(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/users/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function test_tampered_token_is_rejected(): void
    {
        $client = $this->client;

        $email = 'tamper@example.com';
        $password = 'Tamper123';
        $this->createUser($email, $password);

        // Obtain a valid token
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => $password,
        ]));
        $this->assertResponseIsSuccessful();
        $token = json_decode($client->getResponse()->getContent(), true)['token'] ?? '';
        $this->assertNotEmpty($token);

        // Tamper with the token (change last char)
        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');

        $client->request('GET', '/api/users/me', [], [], [
            'HTTP_Authorization' => 'Bearer '.$tampered,
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}


