<?php

namespace App\Factory;

use App\Entity\TentativeQuestionnaire;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TentativeQuestionnaire>
 */
final class TentativeQuestionnaireFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        $start = new \DateTimeImmutable('-1 hour');
        $endMutable = self::faker()->optional(0.8)->dateTimeBetween('-30 minutes', 'now');
        /** @var \DateTimeImmutable|null $end */
        $end = $endMutable ? \DateTimeImmutable::createFromMutable($endMutable) : null;
        return [
            'prenomParticipant' => self::faker()->firstName(),
            'nomParticipant' => self::faker()->lastName(),
            'dateDebut' => $start,
            'dateFin' => $end,
            'score' => null,
            'nombreTotalQuestions' => null,
            'questionnaire' => QuestionnaireFactory::new(),
            'utilisateur' => self::faker()->boolean(50) ? UtilisateurFactory::new() : null,
        ];
    }

    public static function class(): string
    {
        return TentativeQuestionnaire::class;
    }
}


