<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Factory\UtilisateurFactory;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

class MailControllerTest extends WebTestCase
{
    use Factories;
    use DatabaseTestTrait;

    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);
        $this->resetAutoIncrement($em);
    }

    private function createUser(string $email, string $plainPassword, ?string $first = null): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $tmp = new Utilisateur();
        $hash = $hasher->hashPassword($tmp, $plainPassword);

        return UtilisateurFactory::new([
            'email' => $email,
            'roles' => ['ROLE_USER'],
            'password' => $hash,
            'firstName' => $first ?? 'John',
            'lastName' => 'Doe',
        ])->create();
    }

    public function test_forgot_password_requires_email_returns_400(): void
    {
        $this->client->request('POST', '/api/mail/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_forgot_password_nonexistent_email_returns_200(): void
    {
        $payload = ['email' => 'unknown@example.com'];
        $this->client->request('POST', '/api/mail/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(200);
    }

    public function test_forgot_password_existing_user_sets_token_and_expiration(): void
    {
        $user = $this->createUser('exists@example.com', 'Secret123', 'Alice');
        $payload = ['email' => 'exists@example.com'];
        $this->client->request('POST', '/api/mail/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(200);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $refreshed = $em->getRepository(Utilisateur::class)->findOneBy(['email' => 'exists@example.com']);
        $this->assertNotNull($refreshed?->getResetToken());
        $this->assertNotNull($refreshed?->getResetTokenExpiresAt());
    }

    public function test_reset_password_missing_fields_returns_400(): void
    {
        $this->client->request('POST', '/api/mail/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => '']));
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_reset_password_invalid_token_format_returns_400(): void
    {
        $payload = ['token' => 'invalid', 'password' => 'NewPass123'];
        $this->client->request('POST', '/api/mail/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_reset_password_token_expired_or_invalid_returns_400(): void
    {
        // Create user with expired token
        $user = $this->createUser('reset@example.com', 'OldPass123', 'Bob');
        $token = bin2hex(random_bytes(32));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $u = $em->getRepository(Utilisateur::class)->findOneBy(['email' => 'reset@example.com']);
        $u->setResetToken($token);
        $u->setResetTokenExpiresAt(\DateTime::createFromImmutable((new \DateTimeImmutable())->modify('-1 hour')));
        $em->flush();

        $this->client->request('POST', '/api/mail/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => $token,
            'password' => 'NewPass123',
        ]));
        $this->assertResponseStatusCodeSame(400);

        // Invalid token for another user
        $this->client->request('POST', '/api/mail/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => bin2hex(random_bytes(32)),
            'password' => 'NewPass123',
        ]));
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_reset_password_success(): void
    {
        // Create user with valid token
        $user = $this->createUser('okreset@example.com', 'OldPass123', 'Carl');
        $token = bin2hex(random_bytes(32));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $u = $em->getRepository(Utilisateur::class)->findOneBy(['email' => 'okreset@example.com']);
        $u->setResetToken($token);
        $u->setResetTokenExpiresAt(\DateTime::createFromImmutable((new \DateTimeImmutable())->modify('+1 hour')));
        $em->flush();

        $this->client->request('POST', '/api/mail/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => $token,
            'password' => 'NewPass123',
        ]));
        $this->assertResponseStatusCodeSame(200);

        // Verify it cleared the token
        $refreshed = $em->getRepository(Utilisateur::class)->findOneBy(['email' => 'okreset@example.com']);
        $this->assertNull($refreshed->getResetToken());
        $this->assertNull($refreshed->getResetTokenExpiresAt());
    }
}


