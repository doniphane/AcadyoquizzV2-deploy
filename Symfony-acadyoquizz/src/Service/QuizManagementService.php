<?php

namespace App\Service;

use App\Entity\Questionnaire;
use App\Entity\Utilisateur;
use App\Entity\TentativeQuestionnaire;
use App\Repository\QuestionnaireRepository;
use App\Repository\TentativeQuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class QuizManagementService
{
    public function __construct(
        private QuestionnaireRepository $questionnaireRepository,
        private TentativeQuestionnaireRepository $tentativeRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Récupère tous les questionnaires créés par un utilisateur
     */
    public function getUserQuizzes(Utilisateur $user): array
    {
        return $this->questionnaireRepository->findBy(['creePar' => $user]);
    }

    /**
     * Récupère un questionnaire spécifique appartenant à l'utilisateur
     */
    public function getUserQuiz(int $id, Utilisateur $user): ?Questionnaire
    {
        return $this->questionnaireRepository->findOneByIdAndCreator($id, $user);
    }

    /**
     * Crée un nouveau questionnaire
     */
    public function createQuiz(array $data, Utilisateur $creator): array
    {
        $questionnaire = new Questionnaire();

        // Support des clés françaises et anglaises
        $questionnaire->setTitre($data['titre'] ?? $data['title'] ?? '');
        $questionnaire->setDescription($data['description'] ?? '');
        $questionnaire->setEstActif($data['estActif'] ?? $data['isActive'] ?? true);
        $questionnaire->setEstDemarre($data['estDemarre'] ?? $data['isStarted'] ?? false);
        $questionnaire->setScorePassage($data['scorePassage'] ?? 50);
        $questionnaire->setCreePar($creator);
        $questionnaire->setDateCreation(new \DateTimeImmutable());

        // Génération automatique du code d'accès
        $questionnaire->setCodeAcces($this->generateAccessCode());

        // Validation
        $errors = $this->validator->validate($questionnaire);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return ['success' => false, 'errors' => $errorMessages];
        }

        $this->entityManager->persist($questionnaire);
        $this->entityManager->flush();

        return ['success' => true, 'quiz' => $questionnaire];
    }

    /**
     * Met à jour un questionnaire existant
     */
    public function updateQuiz(Questionnaire $questionnaire, array $data): array
    {
        // Support des clés françaises et anglaises
        if (isset($data['titre']) || isset($data['title'])) {
            $questionnaire->setTitre($data['titre'] ?? $data['title']);
        }
        if (isset($data['description'])) {
            $questionnaire->setDescription($data['description']);
        }
        if (isset($data['estActif']) || isset($data['isActive'])) {
            $questionnaire->setEstActif($data['estActif'] ?? $data['isActive']);
        }
        if (isset($data['estDemarre']) || isset($data['isStarted'])) {
            $questionnaire->setEstDemarre($data['estDemarre'] ?? $data['isStarted']);
        }
        if (isset($data['scorePassage'])) {
            $questionnaire->setScorePassage($data['scorePassage']);
        }

        // Validation
        $errors = $this->validator->validate($questionnaire);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return ['success' => false, 'errors' => $errorMessages];
        }

        $this->entityManager->flush();

        return ['success' => true, 'quiz' => $questionnaire];
    }

    /**
     * Supprime un questionnaire
     */
    public function deleteQuiz(Questionnaire $questionnaire): void
    {
        $this->entityManager->remove($questionnaire);
        $this->entityManager->flush();
    }

    /**
     * Bascule le statut actif/inactif d'un questionnaire
     */
    public function toggleQuizStatus(Questionnaire $questionnaire): Questionnaire
    {
        $questionnaire->setEstActif(!$questionnaire->isActive());
        $this->entityManager->flush();

        return $questionnaire;
    }

    /**
     * Sérialise un questionnaire pour l'API
     */
    public function serializeQuiz(Questionnaire $questionnaire, bool $includeQuestions = false): array
    {
        $data = [
            'id' => $questionnaire->getId(),
            'titre' => $questionnaire->getTitre(),
            'description' => $questionnaire->getDescription(),
            'codeAcces' => $questionnaire->getCodeAcces(),
            'estActif' => $questionnaire->isActive(),
            'estDemarre' => $questionnaire->isStarted(),
            'scorePassage' => $questionnaire->getScorePassage(),
            'dateCreation' => $questionnaire->getDateCreation()?->format('c'),
            'nbQuestions' => $questionnaire->getQuestions()->count(),
        ];

        if ($includeQuestions) {
            $questions = [];
            foreach ($questionnaire->getQuestions() as $question) {
                $reponses = [];
                foreach ($question->getReponses() as $reponse) {
                    $reponses[] = [
                        'id' => $reponse->getId(),
                        'texte' => $reponse->getTexte(),
                        'estCorrecte' => $reponse->isCorrect(),
                        'numeroOrdre' => $reponse->getNumeroOrdre()
                    ];
                }

                $questions[] = [
                    'id' => $question->getId(),
                    'texte' => $question->getTexte(),
                    'numeroOrdre' => $question->getNumeroOrdre(),
                    'isMultipleChoice' => $question->isMultipleChoice(),
                    'reponses' => $reponses
                ];
            }

            usort($questions, fn($a, $b) => $a['numeroOrdre'] <=> $b['numeroOrdre']);
            $data['questions'] = $questions;
        }

        return $data;
    }

    /**
     * Récupère toutes les tentatives d'un questionnaire
     */
    public function getQuizAttempts(Questionnaire $questionnaire): array
    {
        $attempts = $this->tentativeRepository->findBy(
            ['questionnaire' => $questionnaire],
            ['dateDebut' => 'DESC']
        );

        $data = [];
        foreach ($attempts as $attempt) {
            $data[] = [
                'id' => $attempt->getId(),
                'prenomParticipant' => $attempt->getPrenomParticipant(),
                'nomParticipant' => $attempt->getNomParticipant(),
                'dateDebut' => $attempt->getDateDebut()?->format('c'),
                'dateFin' => $attempt->getDateFin()?->format('c'),
                'score' => $attempt->getScore(),
                'nombreTotalQuestions' => $attempt->getNombreTotalQuestions(),
                'pourcentage' => $attempt->getPourcentage(),
                'estReussie' => $attempt->getPourcentage() >= $questionnaire->getScorePassage(),
                'utilisateur' => [
                    'id' => $attempt->getUtilisateur()?->getId(),
                    'email' => $attempt->getUtilisateur()?->getEmail()
                ]
            ];
        }

        return $data;
    }

    /**
     * Récupère les détails d'une tentative spécifique
     */
    public function getAttemptDetails(int $attemptId, Questionnaire $questionnaire): ?array
    {
        $attempt = $this->tentativeRepository->findOneBy([
            'id' => $attemptId,
            'questionnaire' => $questionnaire
        ]);

        if (!$attempt) {
            return null;
        }

        // Récupérer les réponses utilisateur
        $reponsesUtilisateur = $attempt->getReponsesUtilisateur();
        $details = [];

        foreach ($reponsesUtilisateur as $reponseUtilisateur) {
            $question = $reponseUtilisateur->getQuestion();
            $reponseSelectionnee = $reponseUtilisateur->getReponse();

            // Trouver les bonnes réponses pour cette question
            $bonnesReponses = [];
            foreach ($question->getReponses() as $reponse) {
                if ($reponse->isCorrect()) {
                    $bonnesReponses[] = [
                        'id' => $reponse->getId(),
                        'texte' => $reponse->getTexte()
                    ];
                }
            }

            $details[] = [
                'questionId' => $question->getId(),
                'questionTexte' => $question->getTexte(),
                'reponseUtilisateur' => [
                    'id' => $reponseSelectionnee->getId(),
                    'texte' => $reponseSelectionnee->getTexte(),
                    'estCorrecte' => $reponseSelectionnee->isCorrect()
                ],
                'bonnesReponses' => $bonnesReponses,
                'estCorrecte' => $reponseSelectionnee->isCorrect()
            ];
        }

        return [
            'tentative' => [
                'id' => $attempt->getId(),
                'prenomParticipant' => $attempt->getPrenomParticipant(),
                'nomParticipant' => $attempt->getNomParticipant(),
                'dateDebut' => $attempt->getDateDebut()?->format('c'),
                'dateFin' => $attempt->getDateFin()?->format('c'),
                'score' => $attempt->getScore(),
                'nombreTotalQuestions' => $attempt->getNombreTotalQuestions(),
                'pourcentage' => $attempt->getPourcentage()
            ],
            'reponsesDetails' => $details
        ];
    }

    /**
     * Génère un code d'accès unique
     */
    private function generateAccessCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        } while ($this->questionnaireRepository->findOneBy(['codeAcces' => $code]) !== null);

        return $code;
    }
}