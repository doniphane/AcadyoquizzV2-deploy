<?php

/**
 * Tests pour l'entité Questionnaire
 * 
 * Fonctions testées :
 * - Constructeur et valeurs par défaut de l'entité
 * - Setters et getters de toutes les propriétés
 * - Formatage automatique du code d'accès
 * - Méthodes de commodité (isActive, isStarted, etc.)
 * - Relations avec les entités Utilisateur et Question
 * - Validation des contraintes de l'entité
 * - Logique métier intégrée à l'entité
 */

namespace App\Tests\Entity;

use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use App\Entity\Question;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class QuestionnaireTest extends WebTestCase
{
    private $questionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->questionnaire = new Questionnaire();
    }

    public function testQuestionnaireCreationDefaults(): void
    {
        $questionnaire = new Questionnaire();

        $this->assertInstanceOf(\DateTimeImmutable::class, $questionnaire->getDateCreation());
        $this->assertNotNull($questionnaire->getCodeAcces());
        $this->assertSame(6, strlen($questionnaire->getCodeAcces()));
        $this->assertTrue($questionnaire->isActive());
        $this->assertFalse($questionnaire->isStarted());
        $this->assertSame(70, $questionnaire->getScorePassage());
        $this->assertCount(0, $questionnaire->getQuestions());
        $this->assertCount(0, $questionnaire->getTentativesQuestionnaire());

        echo " Test création questionnaire avec valeurs par défaut : PASS\n";
    }

    public function testSettersAndGetters(): void
    {
        $titre = 'Quiz de Test';
        $description = 'Description du quiz';
        $codeAcces = 'ABC123';

        $this->questionnaire->setTitre($titre);
        $this->questionnaire->setDescription($description);
        $this->questionnaire->setCodeAcces($codeAcces);
        $this->questionnaire->setEstActif(false);
        $this->questionnaire->setEstDemarre(true);
        $this->questionnaire->setScorePassage(85);

        $this->assertSame($titre, $this->questionnaire->getTitre());
        $this->assertSame($description, $this->questionnaire->getDescription());
        $this->assertSame($codeAcces, $this->questionnaire->getCodeAcces());
        $this->assertFalse($this->questionnaire->isActive());
        $this->assertTrue($this->questionnaire->isStarted());
        $this->assertSame(85, $this->questionnaire->getScorePassage());

        echo " Test setters et getters : PASS\n";
    }

    public function testTitreTrimming(): void
    {
        $this->questionnaire->setTitre('  Quiz avec espaces  ');
        $this->assertSame('Quiz avec espaces', $this->questionnaire->getTitre());

        echo " Test trim du titre : PASS\n";
    }

    public function testDescriptionHandling(): void
    {
        // Test description normale
        $this->questionnaire->setDescription('Description normale');
        $this->assertSame('Description normale', $this->questionnaire->getDescription());

        // Test description avec espaces
        $this->questionnaire->setDescription('  Description avec espaces  ');
        $this->assertSame('Description avec espaces', $this->questionnaire->getDescription());

        // Test description null
        $this->questionnaire->setDescription(null);
        $this->assertNull($this->questionnaire->getDescription());

        // Test description vide devient null
        $this->questionnaire->setDescription('   ');
        $this->assertNull($this->questionnaire->getDescription());

        echo " Test gestion description : PASS\n";
    }

    public function testCodeAccesFormatting(): void
    {
        // Test conversion en majuscules
        $this->questionnaire->setCodeAcces('abc123');
        $this->assertSame('ABC123', $this->questionnaire->getCodeAcces());

        // Test trim
        $this->questionnaire->setCodeAcces('  XYZ789  ');
        $this->assertSame('XYZ789', $this->questionnaire->getCodeAcces());

        // Test getUniqueCode alias
        $this->assertSame('XYZ789', $this->questionnaire->getUniqueCode());

        echo " Test formatage code d'accès : PASS\n";
    }

    public function testScorePassageClamping(): void
    {
        // Test valeur normale
        $this->questionnaire->setScorePassage(75);
        $this->assertSame(75, $this->questionnaire->getScorePassage());

        // Test valeur minimale
        $this->questionnaire->setScorePassage(0);
        $this->assertSame(0, $this->questionnaire->getScorePassage());

        // Test valeur maximale
        $this->questionnaire->setScorePassage(100);
        $this->assertSame(100, $this->questionnaire->getScorePassage());

        // Test valeur en dessous du minimum
        $this->questionnaire->setScorePassage(-10);
        $this->assertSame(0, $this->questionnaire->getScorePassage());

        // Test valeur au dessus du maximum
        $this->questionnaire->setScorePassage(150);
        $this->assertSame(100, $this->questionnaire->getScorePassage());

        echo " Test clamp score passage : PASS\n";
    }

    public function testDateCreationManagement(): void
    {
        $date = new \DateTimeImmutable('2025-01-01 12:00:00');
        $this->questionnaire->setDateCreation($date);
        $this->assertSame($date, $this->questionnaire->getDateCreation());

        echo " Test gestion date création : PASS\n";
    }

    public function testUtilisateurAssociation(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail('test@example.com');

        $this->questionnaire->setCreePar($utilisateur);
        $this->assertSame($utilisateur, $this->questionnaire->getCreePar());

        echo " Test association utilisateur : PASS\n";
    }

    public function testQuestionManagement(): void
    {
        $question1 = new Question();
        $question1->setTexte('Question 1');

        $question2 = new Question();
        $question2->setTexte('Question 2');

        // Test ajout de questions
        $this->questionnaire->addQuestion($question1);
        $this->assertCount(1, $this->questionnaire->getQuestions());
        $this->assertTrue($this->questionnaire->getQuestions()->contains($question1));

        $this->questionnaire->addQuestion($question2);
        $this->assertCount(2, $this->questionnaire->getQuestions());

        // Test suppression de question
        $this->questionnaire->removeQuestion($question1);
        $this->assertCount(1, $this->questionnaire->getQuestions());
        $this->assertFalse($this->questionnaire->getQuestions()->contains($question1));
        $this->assertTrue($this->questionnaire->getQuestions()->contains($question2));

        echo " Test gestion questions : PASS\n";
    }

    public function testRegenerateCodeAcces(): void
    {
        $originalCode = $this->questionnaire->getCodeAcces();

        $this->questionnaire->regenererCodeAcces();
        $newCode = $this->questionnaire->getCodeAcces();

        $this->assertNotSame($originalCode, $newCode);
        $this->assertSame(6, strlen($newCode));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $newCode);

        echo " Test régénération code d'accès : PASS\n";
    }

    public function testCodeAccesUniqueness(): void
    {
        $questionnaire1 = new Questionnaire();
        $questionnaire2 = new Questionnaire();

        $code1 = $questionnaire1->getCodeAcces();
        $code2 = $questionnaire2->getCodeAcces();

        // Vérifie que les codes sont différents
        $this->assertNotSame($code1, $code2);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code1);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code2);

        echo " Test unicité codes d'accès : PASS\n";
    }

    /**
     * Lance tous les tests de cette classe
     */
    public function runAllTests(): void
    {
        echo "\n === Tests Entité Questionnaire ===\n";

        $this->testQuestionnaireCreationDefaults();
        $this->testSettersAndGetters();
        $this->testTitreTrimming();
        $this->testDescriptionHandling();
        $this->testCodeAccesFormatting();
        $this->testScorePassageClamping();
        $this->testDateCreationManagement();
        $this->testUtilisateurAssociation();
        $this->testQuestionManagement();
        $this->testRegenerateCodeAcces();
        $this->testCodeAccesUniqueness();

        echo "\n Tous les tests Entité Questionnaire sont passés !\n";
    }
}
