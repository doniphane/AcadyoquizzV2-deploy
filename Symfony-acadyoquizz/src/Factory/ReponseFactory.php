<?php

namespace App\Factory;

use App\Entity\Reponse;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Reponse>
 */
final class ReponseFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'texte' => self::faker()->words(4, true),
            'estCorrecte' => false,
            'numeroOrdre' => self::faker()->numberBetween(1, 6),
            'question' => QuestionFactory::new(),
        ];
    }

    public static function class(): string
    {
        return Reponse::class;
    }
}


