import { useNavigate, useSearchParams } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

function ResetPasswordPage() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const [token, setToken] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        const tokenFromUrl = searchParams.get('token');
        if (tokenFromUrl) {
            setToken(tokenFromUrl);
        } else {
            setError('Lien invalide ou expiré. Redirection...');
            setTimeout(() => navigate('/forgot-password'), 3000);
        }
    }, [searchParams, navigate]);

    const resetPassword = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setMessage('');
        setLoading(true);

        if (!password) {
            setError('Nouveau mot de passe requis');
            setLoading(false);
            return;
        }

        if (password.length < 6) {
            setError('Le mot de passe doit contenir au moins 6 caractères');
            setLoading(false);
            return;
        }

        if (password !== confirmPassword) {
            setError('Les mots de passe ne correspondent pas');
            setLoading(false);
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/api/mail/reset-password`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ token, password })
            });

            const result = await response.json();

            if (response.ok) {
                setMessage(result.message || 'Mot de passe réinitialisé avec succès');
                setTimeout(() => {
                    navigate('/login', { 
                        state: { 
                            successMessage: 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.' 
                        } 
                    });
                }, 2000);
            } else {
                setError(result.error || 'Une erreur est survenue');
            }
        } catch (err) {
            setError('Erreur réseau. Veuillez vérifier votre connexion.');
        }

        setLoading(false);
    };

    return (
        <div className="min-h-screen flex items-center justify-center p-6" style={{ backgroundColor: '#18191D' }}>
            <Card className="w-full max-w-md border-2 border-amber-500 shadow-2xl">
                <CardHeader className="text-center">
                    <CardTitle className="text-2xl font-bold text-gray-900">
                        Réinitialiser le mot de passe
                    </CardTitle>
                    <CardDescription className="text-gray-600">
                        Choisissez votre nouveau mot de passe sécurisé
                    </CardDescription>
                </CardHeader>
                
                <CardContent className="space-y-4">
                    {error && (
                        <div className="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                            <p className="text-sm">{error}</p>
                        </div>
                    )}
                    
                    {message && (
                        <div className="p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                            <p className="text-sm font-medium">{message}</p>
                            <p className="text-xs mt-1">Redirection vers la page de connexion...</p>
                        </div>
                    )}
                    
                    <form onSubmit={resetPassword} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="password">Nouveau mot de passe</Label>
                            <Input
                                id="password"
                                type="password"
                                placeholder="Entrez votre nouveau mot de passe"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                disabled={loading}
                                className="focus:ring-amber-500 focus:border-amber-500"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="confirmPassword">Confirmer le mot de passe</Label>
                            <Input
                                id="confirmPassword"
                                type="password"
                                placeholder="Confirmez votre nouveau mot de passe"
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                disabled={loading}
                                className="focus:ring-amber-500 focus:border-amber-500"
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-yellow-500 hover:bg-yellow-400 focus:ring-amber-500 text-black font-bold"
                        >
                            {loading ? 'Réinitialisation...' : 'Réinitialiser le mot de passe'}
                        </Button>
                    </form>
                    
                    <div className="text-center pt-4">
                        <p className="text-sm text-gray-600">
                            Retour à la{' '}
                            <Button
                                variant="link"
                                className="p-0 h-auto font-medium text-amber-500 hover:text-amber-600"
                                onClick={() => navigate('/login')}
                            >
                                page de connexion
                            </Button>
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

export default ResetPasswordPage;