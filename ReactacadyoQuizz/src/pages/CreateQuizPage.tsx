import { useState } from "react";
import { useNavigate } from "react-router-dom";
import toast from "react-hot-toast";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import AuthService from "../services/AuthService";

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;

function CreateQuizPage() {
  const navigate = useNavigate();
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const goBackToAdmin = () => {
    navigate("/admin");
  };

  const createQuiz = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    if (!title.trim()) {
      setError("Le titre est obligatoire");
      setLoading(false);
      return;
    }

    const token = AuthService.getToken();
    if (!token) {
      setError("Vous devez être connecté");
      setLoading(false);
      return;
    }

    const quizData = {
      title: title.trim(),
      description: description.trim(),
      estActif: true,
      estDemarre: false,
      scorePassage: 70
    };

    try {
      const response = await fetch(`${API_BASE_URL}/api/quizzes`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(quizData)
      });

      if (response.ok) {
        const createdQuiz = await response.json();
        toast.success("Quiz créé avec succès !");
        
        if (createdQuiz.uniqueCode) {
          toast.success(`Code d'accès : ${createdQuiz.uniqueCode}`, {
            duration: 10000,
            icon: "📋"
          });
        }
        
        navigate(`/manage-questions/${createdQuiz.id}`);
      } else {
        setError("Erreur lors de la création du quiz");
      }
    } catch (err) {
      setError("Erreur de connexion");
    }
    
    setLoading(false);
  };


  return (
    <div className="min-h-screen bg-gray-900 text-white p-6">
      <nav className="flex items-center space-x-2 text-sm text-gray-400 mb-6">
        <button onClick={goBackToAdmin} className="hover:text-yellow-400 transition-colors duration-200">
          Dashboard
        </button>
        <span className="text-gray-600">/</span>
        <span className="text-yellow-400 font-medium">Créer un quiz</span>
      </nav>

      <div className="mb-8">
        <h1 className="text-4xl font-bold text-yellow-400 mb-2">
          Créer un nouveau quiz
        </h1>
        <p className="text-gray-300 text-lg">
          Créez votre quiz en quelques étapes simples
        </p>
      </div>

      <div className="max-w-2xl mx-auto">
        <Card className="bg-gray-100 text-gray-900">
          <CardHeader>
            <CardTitle className="text-xl font-bold">
              Créer un nouveau quiz
            </CardTitle>
          </CardHeader>
          <CardContent>
            {error && (
              <div className="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg mb-4">
                <p className="text-sm">{error}</p>
              </div>
            )}

            <form onSubmit={createQuiz} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="title" className="text-sm font-medium">
                  Titre du quiz <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="title"
                  type="text"
                  placeholder="Ex: Quiz de culture générale"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  disabled={loading}
                  maxLength={100}
                  className="border border-black focus:ring-amber-500 focus:border-amber-500"
                />
                <div className="text-sm text-gray-600">100 caractères maximum</div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="description" className="text-sm font-medium">Description</Label>
                <Textarea
                  id="description"
                  placeholder="Description optionnelle du quiz"
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  disabled={loading}
                  maxLength={500}
                  className="border border-black focus:ring-amber-500 focus:border-amber-500"
                />
                <div className="text-sm text-gray-600">500 caractères maximum (optionnel)</div>
              </div>

              <div className="flex gap-4 pt-4">
                <Button
                  type="button"
                  onClick={goBackToAdmin}
                  variant="outline"
                  className="border-gray-300 text-gray-700 hover:bg-gray-50"
                  disabled={loading}
                >
                  Annuler
                </Button>
                <Button
                  type="submit"
                  className="bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-semibold"
                  disabled={loading}
                >
                  {loading ? "Création..." : "Créer le quiz"}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export default CreateQuizPage;