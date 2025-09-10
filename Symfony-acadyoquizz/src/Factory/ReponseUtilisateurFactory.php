<?php

namespace App\Factory;

use App\Entity\ReponseUtilisateur;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ReponseUtilisateur>
 */
final class ReponseUtilisateurFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'tentativeQuestionnaire' => TentativeQuestionnaireFactory::new(),
            'question' => QuestionFactory::new(),
            // Ensure response belongs to same question in afterInstantiate
            'reponse' => null,
            'dateReponse' => new \DateTimeImmutable(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (ReponseUtilisateur $ru): void {
            if ($ru->getQuestion() === null) {
                return;
            }
            // Pick a response from the same question
            $question = $ru->getQuestion();
            $responses = $question->getReponses();
            if ($responses->count() === 0) {
                // create 4 answers with 1 correct
                $correctIndex = random_int(1, 4);
                for ($i = 1; $i <= 4; $i++) {
                    $resp = ReponseFactory::new([
                        'question' => $question,
                        'numeroOrdre' => $i,
                        'estCorrecte' => $i === $correctIndex,
                    ])->create()->object();
                }
                $responses = $question->getReponses();
            }
            $ru->setReponse($responses->get(random_int(0, $responses->count() - 1)) ?: null);
        });
    }

    public static function class(): string
    {
        return ReponseUtilisateur::class;
    }
}


