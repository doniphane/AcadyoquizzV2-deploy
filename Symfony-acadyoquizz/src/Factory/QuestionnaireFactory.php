<?php

namespace App\Factory;

use App\Entity\Questionnaire;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Questionnaire>
 */
final class QuestionnaireFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'titre' => 'Quiz '.self::faker()->words(3, true),
            'description' => self::faker()->optional(0.6)->sentence(12),
            // codeAcces is generated in constructor, keep null to use entity logic
            'estActif' => true,
            'estDemarre' => false,
            'scorePassage' => self::faker()->numberBetween(50, 90),
            'dateCreation' => new \DateTimeImmutable(),
            'creePar' => UtilisateurFactory::new(),
        ];
    }

    public static function class(): string
    {
        return Questionnaire::class;
    }
}


