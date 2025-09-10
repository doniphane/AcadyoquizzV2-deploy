<?php

namespace App\Factory;

use App\Entity\Question;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Question>
 */
final class QuestionFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'texte' => rtrim(self::faker()->sentence(10), '.').'?',
            'numeroOrdre' => self::faker()->numberBetween(1, 20),
            'questionnaire' => QuestionnaireFactory::new(),
        ];
    }

    public static function class(): string
    {
        return Question::class;
    }
}


