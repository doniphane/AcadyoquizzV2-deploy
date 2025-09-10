<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function test_index_returns_api_info(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Acadyoquizz API', $data['message'] ?? null);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertIsArray($data['endpoints']);
    }
}


