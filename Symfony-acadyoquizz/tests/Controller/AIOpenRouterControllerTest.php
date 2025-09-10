<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Factory\UtilisateurFactory;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zenstruck\Foundry\Test\Factories;

class AIOpenRouterControllerTest extends WebTestCase
{
    use Factories;
    use DatabaseTestTrait;

    private ?KernelBrowser $client = null;
    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearDatabase($em);
        $this->resetAutoIncrement($em);

        // user for auth
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $tmp = new Utilisateur();
        $hash = $hasher->hashPassword($tmp, 'Secret123');
        UtilisateurFactory::new([
            'email' => 'ai.user@example.com',
            'roles' => ['ROLE_USER'],
            'password' => $hash,
            'firstName' => 'AI',
            'lastName' => 'User',
        ])->create();
        $this->token = $this->loginAndGetToken('ai.user@example.com', 'Secret123');
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

    private function replaceHttpClient(HttpClientInterface $client): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        static::getContainer()->set(HttpClientInterface::class, $client);
    }

    public function test_generate_questions_requires_fields_returns_400(): void
    {
        $_ENV['VITE_OPENROUTER_API_KEY'] = 'dummy';
        $this->client->request('POST', '/api/ai/generate-questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode([]));
        $this->assertResponseStatusCodeSame(400);
    }

    public function test_generate_questions_missing_api_key_returns_500(): void
    {
        unset($_ENV['VITE_OPENROUTER_API_KEY']);
        $payload = ['text' => 'Texte', 'numberOfQuestions' => 2];
        $this->client->request('POST', '/api/ai/generate-questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseStatusCodeSame(500);
    }

    public function test_generate_questions_success_with_mocked_client(): void
    {
        $_ENV['VITE_OPENROUTER_API_KEY'] = 'dummy';

        // Mock HttpClientInterface
        $mockResponse = new class implements ResponseInterface {
            public function getStatusCode(): int { return 200; }
            public function getHeaders(bool $throw = true): array { return []; }
            public function getContent(bool $throw = true): string { return json_encode(['choices' => [['message' => ['content' => '{"questions":[{"question":"Q1?","answers":[{"text":"A","correct":true},{"text":"B","correct":false},{"text":"C","correct":false},{"text":"D","correct":false}]}]}']]]]); }
            public function toArray(bool $throw = true): array { return json_decode($this->getContent(), true); }
            public function cancel(): void {}
            public function getInfo(string $type = null): mixed { return null; }
        };

        $mockClient = new class($mockResponse) implements HttpClientInterface {
            public function __construct(private ResponseInterface $response) {}
            public function request(string $method, string $url, array $options = []): ResponseInterface { return $this->response; }
            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): \Symfony\Contracts\HttpClient\ResponseStreamInterface {
                $resp = $this->response;
                return new class($resp) implements \Symfony\Contracts\HttpClient\ResponseStreamInterface {
                    private bool $done = false;
                    public function __construct(private \Symfony\Contracts\HttpClient\ResponseInterface $response) {}
                    public function rewind(): void { $this->done = false; }
                    public function next(): void { $this->done = true; }
                    public function valid(): bool { return !$this->done; }
                    public function key(): \Symfony\Contracts\HttpClient\ResponseInterface { return $this->response; }
                    public function current(): \Symfony\Contracts\HttpClient\ChunkInterface {
                        return new class implements \Symfony\Contracts\HttpClient\ChunkInterface {
                            public function isTimeout(): bool { return false; }
                            public function isFirst(): bool { return true; }
                            public function isLast(): bool { return true; }
                            public function getInformationalStatus(): ?array { return null; }
                            public function getContent(): string { return ''; }
                            public function getOffset(): int { return 0; }
                            public function getError(): ?string { return null; }
                        };
                    }
                };
            }
            public function withOptions(array $options): static { return $this; }
        };

        $this->replaceHttpClient($mockClient);

        $payload = ['text' => 'Texte', 'numberOfQuestions' => 1];
        $this->client->request('POST', '/api/ai/generate-questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('questions', $data);
        $this->assertCount(1, $data['questions']);
    }

    public function test_generate_questions_openrouter_error_codes(): void
    {
        $_ENV['VITE_OPENROUTER_API_KEY'] = 'dummy';

        foreach ([401,403,429,500] as $status) {
            $mockResponse = new class($status) implements ResponseInterface {
                public function __construct(private int $status) {}
                public function getStatusCode(): int { return $this->status; }
                public function getHeaders(bool $throw = true): array { return []; }
                public function getContent(bool $throw = true): string { return json_encode(['error'=>['message'=>'err']]); }
                public function toArray(bool $throw = true): array { return ['error'=>['message'=>'err']]; }
                public function cancel(): void {}
                public function getInfo(string $type = null): mixed { return null; }
            };
            $mockClient = new class($mockResponse) implements HttpClientInterface {
                public function __construct(private ResponseInterface $response) {}
                public function request(string $method, string $url, array $options = []): ResponseInterface { return $this->response; }
                public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): \Symfony\Contracts\HttpClient\ResponseStreamInterface {
                    $resp = $this->response;
                    return new class($resp) implements \Symfony\Contracts\HttpClient\ResponseStreamInterface {
                        private bool $done = false;
                        public function __construct(private \Symfony\Contracts\HttpClient\ResponseInterface $response) {}
                        public function rewind(): void { $this->done = false; }
                        public function next(): void { $this->done = true; }
                        public function valid(): bool { return !$this->done; }
                        public function key(): \Symfony\Contracts\HttpClient\ResponseInterface { return $this->response; }
                        public function current(): \Symfony\Contracts\HttpClient\ChunkInterface {
                            return new class implements \Symfony\Contracts\HttpClient\ChunkInterface {
                                public function isTimeout(): bool { return false; }
                                public function isFirst(): bool { return true; }
                                public function isLast(): bool { return true; }
                                public function getInformationalStatus(): ?array { return null; }
                                public function getContent(): string { return ''; }
                                public function getOffset(): int { return 0; }
                                public function getError(): ?string { return null; }
                            };
                        }
                    };
                }
                public function withOptions(array $options): static { return $this; }
            };
            $this->replaceHttpClient($mockClient);

            $payload = ['text' => 'T', 'numberOfQuestions' => 1];
            $this->client->request('POST', '/api/ai/generate-questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
            $this->assertResponseStatusCodeSame($status);
        }
    }

    public function test_generate_questions_exception_timeout_and_network(): void
    {
        $_ENV['VITE_OPENROUTER_API_KEY'] = 'dummy';

        // timeout
        $mockClientTimeout = new class implements HttpClientInterface {
            public function request(string $method, string $url, array $options = []): ResponseInterface { throw new \Exception('timed out'); }
            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): \Symfony\Contracts\HttpClient\ResponseStreamInterface { throw new \Exception('not used'); }
            public function withOptions(array $options): static { return $this; }
        };
        $this->replaceHttpClient($mockClientTimeout);
        $payload = ['text' => 'T', 'numberOfQuestions' => 1];
        $this->client->request('POST', '/api/ai/generate-questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseStatusCodeSame(500);

        // network
        $mockClientNetwork = new class implements HttpClientInterface {
            public function request(string $method, string $url, array $options = []): ResponseInterface { throw new \Exception('network error'); }
            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): \Symfony\Contracts\HttpClient\ResponseStreamInterface { throw new \Exception('not used'); }
            public function withOptions(array $options): static { return $this; }
        };
        $this->replaceHttpClient($mockClientNetwork);
        $this->client->request('POST', '/api/ai/generate-questions', [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $this->auth()), json_encode($payload));
        $this->assertResponseStatusCodeSame(500);
    }
}


