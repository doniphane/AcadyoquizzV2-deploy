<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Empêcher le chargement automatique des DataFixtures pendant les tests
// Les tests utilisent leurs propres fixtures spécifiques
$_ENV['APP_ENV'] = 'test';
$_ENV['DATABASE_URL'] = $_ENV['DATABASE_URL'] ?? 'sqlite:///:memory:';
