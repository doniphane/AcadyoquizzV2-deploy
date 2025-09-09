import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Plus, LogOut, User, Menu, X } from "lucide-react";
import AuthService from "../services/AuthService";
import toast from "react-hot-toast";
import { ListeQuiz, MetricsDashboard } from "../components";

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;

interface SimpleQuiz {
  id: number;
  title: string;
  isActive: boolean;
  accessCode: string;
  isStarted: boolean;
}

interface Metrics {
  quizzesCreated: number;
  totalAttempts: number;
  registeredUsers: number;
}

function AdminPage() {
  const navigate = useNavigate();
  const [quizzes, setQuizzes] = useState<SimpleQuiz[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingQuizId, setLoadingQuizId] = useState<number | null>(null);
  const [showMobileMenu, setShowMobileMenu] = useState(false);
  const [metrics, setMetrics] = useState<Metrics>({
    quizzesCreated: 0,
    totalAttempts: 0,
    registeredUsers: 0
  });

  const makeApiCall = async (url: string, method = 'GET', body = null) => {
    const token = AuthService.getToken();
    if (!token) {
      toast.error("Vous devez être connecté");
      navigate("/login");
      return null;
    }

    const response = await fetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: body ? JSON.stringify(body) : null
    });

    return response;
  };

  const logout = async () => {
    await AuthService.logout();
    navigate("/login");
  };

  const loadQuizzes = async () => {
    setLoading(true);
    const response = await makeApiCall(`${API_BASE_URL}/api/quizzes`);
    
    if (response && response.ok) {
      const data = await response.json();
      setQuizzes(data);
      setMetrics(prev => ({ ...prev, quizzesCreated: data.length }));
    } else {
      toast.error("Erreur lors du chargement des quiz");
    }
    
    setLoading(false);
  };

  const toggleQuizStatus = async (quizId: number, currentStatus: boolean) => {
    setLoadingQuizId(quizId);
    const response = await makeApiCall(`${API_BASE_URL}/api/quizzes/${quizId}/toggle-status`, 'PATCH');
    
    if (response && response.ok) {
      setQuizzes(quizzes.map(quiz => 
        quiz.id === quizId ? { ...quiz, isActive: !currentStatus } : quiz
      ));
      toast.success(!currentStatus ? "Quiz activé" : "Quiz désactivé");
    } else {
      toast.error("Erreur lors de la mise à jour");
    }
    
    setLoadingQuizId(null);
  };

  const deleteQuiz = async (quizId: number, quizTitre: string) => {
    if (!window.confirm(`Supprimer le quiz "${quizTitre}" ?`)) return;

    setLoadingQuizId(quizId);
    const response = await makeApiCall(`${API_BASE_URL}/api/quizzes/${quizId}`, 'DELETE');
    
    if (response && response.ok) {
      setQuizzes(quizzes.filter(quiz => quiz.id !== quizId));
      toast.success("Quiz supprimé");
    } else {
      toast.error("Erreur lors de la suppression");
    }
    
    setLoadingQuizId(null);
  };

  useEffect(() => {
    loadQuizzes();
  }, []);

  const buttonClass = "bg-white hover:bg-gray-100 text-gray-900 border border-gray-300 px-6 py-3";

  return (
    <div className="min-h-screen bg-gray-900 text-white p-6">
      <div className="flex justify-between items-start mb-8">
        <div>
          <h1 className="text-3xl md:text-4xl font-bold text-yellow-400 mb-2">
            Dashboard Admin
          </h1>
          <p className="text-sm md:text-lg text-gray-300">
            Gérez vos quiz et analysez les performances
          </p>
        </div>
        
        <div className="md:hidden">
          <button 
            onClick={() => setShowMobileMenu(!showMobileMenu)} 
            className="text-white focus:outline-none"
          >
            {showMobileMenu ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>
      
        <div className="hidden md:flex gap-4">
          <Button onClick={() => navigate("/create-quiz")} className={buttonClass}>
            <Plus className="w-4 h-4 mr-2" />
            Créer un quiz
          </Button>
          <Button onClick={() => navigate("/student")} className={buttonClass}>
            <User className="w-4 h-4 mr-2" />
            Espace Élève
          </Button>
          <Button onClick={logout} className={buttonClass}>
            <LogOut className="w-4 h-4 mr-2" />
            Déconnexion
          </Button>
        </div>
      </div>

      {showMobileMenu && (
        <div className="md:hidden bg-gray-800 p-4 rounded-lg space-y-4 mb-6">
          <Button onClick={() => navigate("/create-quiz")} className={`${buttonClass} w-full`}>
            <Plus className="w-4 h-4 mr-2" />
            Créer un quiz
          </Button>
          <Button onClick={() => navigate("/student")} className={`${buttonClass} w-full`}>
            <User className="w-4 h-4 mr-2" />
            Espace Élève
          </Button>
          <Button onClick={logout} className={`${buttonClass} w-full`}>
            <LogOut className="w-4 h-4 mr-2" />
            Déconnexion
          </Button>
        </div>
      )}

      <MetricsDashboard metrics={metrics} />
      <ListeQuiz
        quizzes={quizzes}
        loading={loading}
        loadingQuizId={loadingQuizId}
        onToggleStatus={toggleQuizStatus}
        onDeleteQuiz={deleteQuiz}
      />
    </div>
  );
}

export default AdminPage;