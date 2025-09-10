<?php

namespace App\Tests\Controller;

/**
 * @covers \App\Controller\UserController::me
 * @covers \App\Controller\UserController::updateMe
 * @covers \App\Controller\UserController::register
 * @covers \App\Controller\UserController::getItem
 */

use App\Entity\Utilisateur;
use App\Factory\UtilisateurFactory;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

class UserControllerTest extends WebTestCase
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

        $this->user = $this->createUser('user.me@example.com', 'Secret123', 'Alice', 'Martin');
        $this->token = $this->loginAndGetToken('user.me@example.com', 'Secret123');
    }

    private function createUser(string $email, string $plainPassword, ?string $first = null, ?string $last = null): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $tmp = new Utilisateur();
        $hash = $hasher->hashPassword($tmp, $plainPassword);

        return UtilisateurFactory::new([
            'email' => $email,
            'roles' => ['ROLE_USER'],
            'password' => $hash,
            'firstName' => $first,
            'lastName' => $last,
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
        return json_decode($this->client->getResponse()->getContent(), true)['token'] ?? '';
    }

    private function auth(): array
    {
        return ['HTTP_Authorization' => 'Bearer '.$this->token];
    }

    public function test_register_success(): void
    {
        $payload = [
            'email' => 'register.ok@example.com',
            'password' => 'Strong123',
            'firstName' => 'Reg',
            'lastName' => 'User',
        ];
        $this->client->request('POST', '/api/users/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('register.ok@example.com', $data['email'] ?? null);
        $this->assertEquals('Reg', $data['firstName'] ?? null);
    }

    public function test_register_invalid_json_returns_400(): void
    {
        $this->client->request('POST', '/api/users/register', [], [], ['CONTENT_TYPE' => 'application/json'], '{ invalid');
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_register_duplicate_email_returns_409(): void
    {
        // Crée un utilisateur initial avec cet email
        $this->createUser('duplicate@example.com', 'Secret123');
        $payload = [
            'email' => 'duplicate@example.com',
            'password' => 'Strong123',
            'firstName' => 'Dup',
            'lastName' => 'User',
        ];
        $this->client->request('POST', '/api/users/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertTrue(in_array($this->client->getResponse()->getStatusCode(), [400, 409]));
    }

    public function test_user_item_requires_admin(): void
    {
        // Appel sans token admin → 401
        $this->client->request('GET', '/api/users/1');
        $this->assertResponseStatusCodeSame(401);

        // Appel avec token user ROLE_USER → 403
        $this->client->request('GET', '/api/users/'.$this->user->getId(), [], [], $this->auth());
        $this->assertResponseStatusCodeSame(403);
    }

    public function test_user_item_admin_success_and_not_found(): void
    {
        // Créer un admin et se connecter
        $admin = $this->createUser('admin@example.com', 'Admin123', 'Admin', 'User');
        $admin->setRoles(['ROLE_ADMIN']);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $adminToken = $this->loginAndGetToken('admin@example.com', 'Admin123');

        // success
        $this->client->request('GET', '/api/users/'.$this->user->getId(), [], [], ['HTTP_Authorization' => 'Bearer '.$adminToken]);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($this->user->getEmail(), $data['email'] ?? null);

        // not found
        $this->client->request('GET', '/api/users/999999', [], [], ['HTTP_Authorization' => 'Bearer '.$adminToken]);
        $this->assertResponseStatusCodeSame(404);
    }
    public function test_me_requires_auth(): void
    {
        $this->client->request('GET', '/api/users/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function test_me_success_returns_user_info(): void
    {
        $this->client->request('GET', '/api/users/me', [], [], $this->auth());
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('user.me@example.com', $data['email'] ?? null);
        $this->assertEquals('Alice', $data['firstName'] ?? null);
        $this->assertEquals('Martin', $data['lastName'] ?? null);
    }

    public function test_update_me_requires_auth(): void
    {
        $payload = ['firstName' => 'Bob'];
        $this->client->request('PUT', '/api/users/me', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(401);
    }

    public function test_update_me_success(): void
    {
        $payload = [
            'firstName' => 'Bruno',
            'lastName' => 'Durand',
        ];
        $this->client->request('PUT', '/api/users/me', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Bruno', $data['firstName'] ?? null);
        $this->assertEquals('Durand', $data['lastName'] ?? null);
    }

    public function test_update_me_invalid_json_returns_400(): void
    {
        $this->client->request('PUT', '/api/users/me', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), '{ invalid');
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_update_me_invalid_name_returns_400(): void
    {
        $payload = [
            'firstName' => '<script>',
            'lastName' => '***',
        ];
        $this->client->request('PUT', '/api/users/me', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseStatusCodeSame(400);
    }
}


