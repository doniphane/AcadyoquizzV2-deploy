<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création d'un utilisateur de test
        $user = new \App\Entity\Utilisateur();
        $user->setEmail('test@example.com');
        $user->setLastName('Test');
        $user->setFirstName('User');
        $user->setPassword('$2y$13$examplehashpassword');
        $manager->persist($user);

        // Création d'un questionnaire de test
        $questionnaire = new \App\Entity\Questionnaire();
        $questionnaire->setTitre('Quiz de test');
        $questionnaire->setCreePar($user);
        $questionnaire->setCodeAcces('TEST123');
        $questionnaire->setEstActif(true);
        $questionnaire->setScorePassage(70);
        $questionnaire->setDateCreation(new \DateTimeImmutable());
        $manager->persist($questionnaire);

        $manager->flush();
    }
}
