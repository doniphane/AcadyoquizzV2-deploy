<?php

namespace App\Tests\Security;

use App\Entity\Utilisateur;
use App\Factory\UtilisateurFactory;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

class SecurityTest extends WebTestCase
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
        $temp = new Utilisateur();
        $hash = $hasher->hashPassword($temp, $plainPassword);

        return UtilisateurFactory::new([
            'email' => $email,
            'roles' => ['ROLE_USER'],
            'password' => $hash,
            'firstName' => 'Alice',
            'lastName' => 'Doe',
        ])->create();
    }

    /** TS001: XSS Injection script dans champ nom → doit être échappé/bloqué */
    public function test_TS001_xss_injection_in_last_name_is_blocked(): void
    {
        $client = $this->client;

        // Créer et se connecter pour obtenir un token
        $email = 'xss.user@example.com';
        $password = 'Safe123';
        $this->createUser($email, $password);

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => $password,
        ]));
        $this->assertResponseIsSuccessful();
        $token = json_decode($client->getResponse()->getContent(), true)['token'] ?? '';
        $this->assertNotEmpty($token);

        // Tentative de XSS via nom de famille
        $malicious = '<script>alert(1)</script>';
        $client->request('PUT', '/api/users/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer '.$token,
        ], json_encode([
            'lastName' => $malicious,
        ]));

        // La validation regex de l'entité bloque < > → 400 attendu
        $this->assertResponseStatusCodeSame(400, 'Le backend doit bloquer le XSS dans lastName');
    }

    /** TS002: SQL Injection ' OR 1=1 -- dans login → requête bloquée */
    public function test_TS002_sql_injection_in_login_is_blocked(): void
    {
        $client = $this->client;

        // Envoi d'un login avec tentative d'injection SQL
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => "' OR 1=1 --",
            'password' => 'does-not-matter',
        ]));

        $this->assertResponseStatusCodeSame(401, 'Tentative SQLi doit être rejetée');
    }
}


