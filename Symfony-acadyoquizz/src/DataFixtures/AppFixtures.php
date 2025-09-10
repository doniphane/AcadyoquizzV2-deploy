<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Zenstruck\Foundry\faker; // for static helper
use App\Factory\UtilisateurFactory;
use App\Factory\QuestionnaireFactory;
use App\Factory\QuestionFactory;
use App\Factory\ReponseFactory;
use App\Factory\TentativeQuestionnaireFactory;
use App\Factory\ReponseUtilisateurFactory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create(); // Instancier Faker

        // Utilisateur admin dédié
        $admin = UtilisateurFactory::new([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_ADMIN'],
            // bcrypt for "adminpass"
            'password' => '$2y$13$C1sU4m0C2nqg2ijm2Z4tE.Z2cR7I9pQf7m1t1o8bQmp0QF0b0b0b0',
            'firstName' => 'Admin',
            'lastName' => 'User',
        ])->create();

        // Plusieurs utilisateurs simples avec des emails uniques
        UtilisateurFactory::createMany(5, function () use ($faker) {
            return [
                'email' => $faker->unique()->safeEmail(),
            ];
        });

        // Créer 3 questionnaires avec questions et réponses
        $questionnaires = QuestionnaireFactory::createMany(3, function () use ($admin) {
            return [
                'creePar' => $admin,
            ];
        });

        // Pour chaque questionnaire, créer 5 questions, chacune avec 4 réponses dont 1 correcte
        foreach ($questionnaires as $questionnaire) {
            for ($i = 1; $i <= 5; $i++) {
                $question = QuestionFactory::new([
                    'questionnaire' => $questionnaire,
                    'numeroOrdre' => $i,
                ])->create();

                $correctIndex = random_int(1, 4);
                for ($r = 1; $r <= 4; $r++) {
                    ReponseFactory::new([
                        'question' => $question,
                        'numeroOrdre' => $r,
                        'estCorrecte' => $r === $correctIndex,
                    ])->create();
                }
            }
        }

        // Créer 5 tentatives pour le premier questionnaire
        $firstQuiz = $questionnaires[0];
        for ($t = 0; $t < 5; $t++) {
            $tentative = TentativeQuestionnaireFactory::new([
                'questionnaire' => $firstQuiz,
            ])->create();

            // Créer des réponses utilisateur pour chaque question du questionnaire
            foreach ($firstQuiz->getQuestions() as $question) {
                ReponseUtilisateurFactory::new([
                    'tentativeQuestionnaire' => $tentative,
                    'question' => $question,
                ])->create();
            }

            // Calculer le score
            $tentative->calculerScore();
        }

        $manager->flush();
    }
}
