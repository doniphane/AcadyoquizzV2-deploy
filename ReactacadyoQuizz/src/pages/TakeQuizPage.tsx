import { useState, useEffect } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { ArrowLeft, ChevronRight, Send } from "lucide-react";
import toast from "react-hot-toast";
import AuthService from "../services/AuthService";

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;

interface QuizAnswer {
  id: number;
  text: string;
}

interface QuizQuestion {
  id: number;
  text: string;
  isMultipleChoice: boolean;
  answers: QuizAnswer[];
}

interface QuizData {
  quizInfo: {
    id: number;
    title: string;
    scorePassage?: number;
  };
  participantData: {
    firstName: string;
    lastName: string;
  };
}

interface UserAnswers {
  [questionId: number]: number | number[];
}

function TakeQuizPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const quizData = location.state as QuizData;
  
  const [questions, setQuestions] = useState<QuizQuestion[]>([]);
  const [currentQuestion, setCurrentQuestion] = useState(0);
  const [answers, setAnswers] = useState<UserAnswers>({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const goBack = () => navigate("/student");

  const nextQuestion = () => {
    if (currentQuestion < questions.length - 1) {
      setCurrentQuestion(currentQuestion + 1);
    }
  };

  const selectAnswer = (questionId: number, answerId: number) => {
    const question = questions[currentQuestion];
    
    if (question.isMultipleChoice) {
      const currentAnswers = (answers[questionId] as number[]) || [];
      if (currentAnswers.includes(answerId)) {
        setAnswers((prev: UserAnswers) => ({
          ...prev,
          [questionId]: currentAnswers.filter((id: number) => id !== answerId)
        }));
      } else {
        setAnswers((prev: UserAnswers) => ({
          ...prev,
          [questionId]: [...currentAnswers, answerId]
        }));
      }
    } else {
      setAnswers((prev: UserAnswers) => ({ ...prev, [questionId]: answerId }));
    }
  };

  const callApi = async (url: string, method = "GET", body: object | null = null) => {
    const token = AuthService.getToken();
    if (!token) {
      toast.error("Vous devez être connecté");
      navigate("/login");
      return null;
    }

    try {
      const response = await fetch(`${API_BASE_URL}${url}`, {
        method,
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`
        },
        body: body ? JSON.stringify(body) : null
      });

      if (response.status === 401) {
        toast.error("Session expirée");
        AuthService.logout();
        navigate("/login");
        return null;
      }

      if (!response.ok) {
        toast.error("Erreur lors de l'opération");
        return null;
      }

      return await response.json();
    } catch (err) {
      toast.error("Erreur de connexion");
      return null;
    }
  };

  const loadQuestions = async () => {
    if (!quizData?.quizInfo?.id) return;

    setLoading(true);
    const data = await callApi(`/api/quizzes/play/${quizData.quizInfo.id}`);

    if (data?.questions) {
      const formattedQuestions = data.questions.map((q: {
        id: number;
        texte: string;
        isMultipleChoice: boolean;
        reponses: { id: number; texte: string }[];
      }) => ({
        id: q.id,
        text: q.texte,
        isMultipleChoice: q.isMultipleChoice,
        answers: q.reponses.map((r: { id: number; texte: string }) => ({
          id: r.id,
          text: r.texte
        }))
      }));
      setQuestions(formattedQuestions);
    } else {
      toast.error("Erreur lors du chargement");
      navigate("/student");
    }
    
    setLoading(false);
  };

  const submitQuiz = async () => {
    if (!quizData?.quizInfo?.id || !quizData?.participantData) {
      toast.error("Données manquantes");
      return;
    }

    setSubmitting(true);
    
    const reponses = [];
    for (const [questionId, answerId] of Object.entries(answers)) {
      if (Array.isArray(answerId)) {
        answerId.forEach(id => {
          reponses.push({
            questionId: parseInt(questionId),
            reponseId: id
          });
        });
      } else {
        reponses.push({
          questionId: parseInt(questionId),
          reponseId: answerId
        });
      }
    }

    const submitData = {
      prenomParticipant: quizData.participantData.firstName,
      nomParticipant: quizData.participantData.lastName,
      reponses: reponses
    };

    const result = await callApi(
      `/api/quizzes/play/${quizData.quizInfo.id}/submit`,
      "POST",
      submitData
    );

    if (result) {
      const totalQuestions = result.totalQuestions || questions.length;
      const bonnesReponses = result.bonnesReponses || 0;
      const percentage = Math.round((bonnesReponses / totalQuestions) * 100);
      const passed = percentage >= (quizData.quizInfo.scorePassage || 50);

      navigate("/quiz-results", {
        state: {
          quizInfo: quizData.quizInfo,
          userAnswers: answers,
          results: {
            score: bonnesReponses,
            maxScore: totalQuestions,
            percentage,
            passed,
            totalQuestions
          },
          tentativeId: result.tentativeId
        }
      });
    }
    
    setSubmitting(false);
  };

  const handleNext = async () => {
    if (currentQuestion === questions.length - 1) {
      await submitQuiz();
    } else {
      nextQuestion();
    }
  };

  useEffect(() => {
    if (!quizData?.participantData || !quizData?.quizInfo) {
      toast.error("Données manquantes pour le quiz");
      navigate("/student");
      return;
    }

    const token = AuthService.getToken();
    if (!token) {
      toast.error("Vous devez être connecté");
      navigate("/login");
      return;
    }

    loadQuestions();
  }, []);

  if (!quizData?.participantData || !quizData?.quizInfo) {
    return null;
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-900 text-white flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-yellow-400 mx-auto mb-4"></div>
          <p className="text-xl">Chargement du quiz...</p>
        </div>
      </div>
    );
  }

  if (questions.length === 0) {
    return (
      <div className="min-h-screen bg-gray-900 text-white flex items-center justify-center">
        <div className="text-center">
          <p className="text-xl mb-4">Aucune question trouvée pour ce quiz</p>
          <Button
            onClick={goBack}
            className="bg-yellow-500 hover:bg-yellow-600 text-gray-900"
          >
            Retour à l'accueil
          </Button>
        </div>
      </div>
    );
  }

  const question = questions[currentQuestion];
  const progress = ((currentQuestion + 1) / questions.length) * 100;
  const currentAnswer = answers[question.id];
  const hasAnswered = currentAnswer !== undefined && (Array.isArray(currentAnswer) ? currentAnswer.length > 0 : true);
  const isLastQuestion = currentQuestion === questions.length - 1;

  return (
    <div className="min-h-screen bg-gray-900 text-white p-4">
      <div className="max-w-4xl mx-auto">
        <div className="flex justify-between items-center mb-6">
          <Button
            onClick={goBack}
            variant="outline"
            className="border-yellow-400 text-yellow-400 hover:bg-yellow-400 hover:text-gray-900"
          >
            <ArrowLeft className="w-4 h-4 mr-2" />
            Retour
          </Button>

          <div className="text-center">
            <h1 className="text-2xl font-bold text-yellow-400">
              {quizData.quizInfo.title}
            </h1>
            <p className="text-gray-300">
              {quizData.participantData.firstName} {quizData.participantData.lastName}
            </p>
          </div>

          <div className="text-right">
            <p className="text-sm text-gray-400">Question</p>
            <p className="text-lg font-bold text-yellow-400">
              {currentQuestion + 1} / {questions.length}
            </p>
          </div>
        </div>

        <div className="mb-8">
          <Progress value={progress} className="h-2 [&>*]:bg-yellow-400" />
          <div className="flex justify-between items-center mt-2">
            <p className="text-sm text-gray-400">
              {Math.round(progress)}% complété
            </p>
            <p className="text-sm text-yellow-400 font-medium">
              {question?.isMultipleChoice ? "Choix multiple" : "Choix unique"}
            </p>
          </div>
        </div>

        <Card className="bg-gray-800 border-gray-700 mb-6">
          <CardHeader>
            <CardTitle className="text-xl text-white">
              {question.text}
            </CardTitle>
            <p className="text-sm text-yellow-400 mt-2">
              {question.isMultipleChoice ? "Sélectionnez une ou plusieurs réponses" : "Sélectionnez une seule réponse"}
            </p>
          </CardHeader>
          <CardContent className="space-y-3">
            {question.answers.map((answer: QuizAnswer) => {
              const isSelected = Array.isArray(currentAnswer)
                ? currentAnswer.includes(answer.id)
                : currentAnswer === answer.id;

              return (
                <button
                  key={answer.id}
                  onClick={() => selectAnswer(question.id, answer.id)}
                  className={`w-full p-4 text-left rounded-lg border-2 transition-all ${
                    isSelected
                      ? "border-yellow-400 bg-yellow-400/10 text-yellow-400"
                      : "border-gray-600 bg-gray-700 text-white hover:border-gray-500"
                  }`}
                >
                  <div className="flex items-center">
                    <div
                      className={`w-4 h-4 border-2 mr-3 ${
                        question.isMultipleChoice ? "rounded" : "rounded-full"
                      } ${
                        isSelected
                          ? "border-yellow-400 bg-yellow-400"
                          : "border-gray-400"
                      }`}
                    />
                    {answer.text}
                  </div>
                </button>
              );
            })}
          </CardContent>
        </Card>

        <div className="flex justify-between">
          <Button
            onClick={() => setCurrentQuestion(Math.max(0, currentQuestion - 1))}
            disabled={currentQuestion === 0}
            variant="outline"
            className="border-gray-600 text-gray-400"
          >
            Précédent
          </Button>
          <Button
            onClick={handleNext}
            disabled={!hasAnswered || submitting}
            className="bg-yellow-500 hover:bg-yellow-600 text-gray-900"
          >
            {submitting ? (
              <>
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-900 mr-2" />
                Soumission...
              </>
            ) : isLastQuestion ? (
              <>
                <Send className="w-4 h-4 mr-2" />
                Terminer le quiz
              </>
            ) : (
              <>
                <ChevronRight className="w-4 h-4 mr-2" />
                Question suivante
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default TakeQuizPage;
