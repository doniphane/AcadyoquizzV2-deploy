<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;

trait DatabaseTestTrait
{
    /**
     * Nettoie la base de données avant chaque test pour éviter les interférences
     * entre les tests et les DataFixtures
     */
    protected function clearDatabase(EntityManagerInterface $entityManager): void
    {
        // Désactiver les contraintes de clés étrangères temporairement
        $connection = $entityManager->getConnection();

        // Pour MySQL
        if ($connection->getDatabasePlatform()->getName() === 'mysql') {
            $connection->executeStatement('SET foreign_key_checks = 0');

            // Nettoyer les tables dans le bon ordre
            $connection->executeStatement('DELETE FROM reponse_utilisateur');
            $connection->executeStatement('DELETE FROM tentative_questionnaire');
            $connection->executeStatement('DELETE FROM reponse');
            $connection->executeStatement('DELETE FROM question');
            $connection->executeStatement('DELETE FROM questionnaire');
            $connection->executeStatement('DELETE FROM utilisateur');

            $connection->executeStatement('SET foreign_key_checks = 1');
        }
        // Pour SQLite (utilisé souvent dans les tests)
        elseif ($connection->getDatabasePlatform()->getName() === 'sqlite') {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');

            $connection->executeStatement('DELETE FROM reponse_utilisateur');
            $connection->executeStatement('DELETE FROM tentative_questionnaire');
            $connection->executeStatement('DELETE FROM reponse');
            $connection->executeStatement('DELETE FROM question');
            $connection->executeStatement('DELETE FROM questionnaire');
            $connection->executeStatement('DELETE FROM utilisateur');

            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
        // Pour PostgreSQL
        elseif ($connection->getDatabasePlatform()->getName() === 'postgresql') {
            $connection->executeStatement('SET session_replication_role = replica');

            $connection->executeStatement('TRUNCATE TABLE reponse_utilisateur CASCADE');
            $connection->executeStatement('TRUNCATE TABLE tentative_questionnaire CASCADE');
            $connection->executeStatement('TRUNCATE TABLE reponse CASCADE');
            $connection->executeStatement('TRUNCATE TABLE question CASCADE');
            $connection->executeStatement('TRUNCATE TABLE questionnaire CASCADE');
            $connection->executeStatement('TRUNCATE TABLE utilisateur CASCADE');

            $connection->executeStatement('SET session_replication_role = DEFAULT');
        }

        // Vider le cache de l'entity manager
        $entityManager->clear();
    }

    /**
     * Remet à zéro les séquences d'auto-increment
     */
    protected function resetAutoIncrement(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();

        if ($connection->getDatabasePlatform()->getName() === 'mysql') {
            $connection->executeStatement('ALTER TABLE utilisateur AUTO_INCREMENT = 1');
            $connection->executeStatement('ALTER TABLE questionnaire AUTO_INCREMENT = 1');
            $connection->executeStatement('ALTER TABLE question AUTO_INCREMENT = 1');
            $connection->executeStatement('ALTER TABLE reponse AUTO_INCREMENT = 1');
            $connection->executeStatement('ALTER TABLE reponse_utilisateur AUTO_INCREMENT = 1');
            $connection->executeStatement('ALTER TABLE tentative_questionnaire AUTO_INCREMENT = 1');
        } elseif ($connection->getDatabasePlatform()->getName() === 'sqlite') {
            $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name IN ("utilisateur", "questionnaire", "question", "reponse", "reponse_utilisateur", "tentative_questionnaire")');
        } elseif ($connection->getDatabasePlatform()->getName() === 'postgresql') {
            $connection->executeStatement('ALTER SEQUENCE utilisateur_id_seq RESTART WITH 1');
            $connection->executeStatement('ALTER SEQUENCE questionnaire_id_seq RESTART WITH 1');
            $connection->executeStatement('ALTER SEQUENCE question_id_seq RESTART WITH 1');
            $connection->executeStatement('ALTER SEQUENCE reponse_id_seq RESTART WITH 1');
            $connection->executeStatement('ALTER SEQUENCE reponse_utilisateur_id_seq RESTART WITH 1');
            $connection->executeStatement('ALTER SEQUENCE tentative_questionnaire_id_seq RESTART WITH 1');
        }
    }
}
