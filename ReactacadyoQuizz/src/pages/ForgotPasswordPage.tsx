import { useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

function ForgotPasswordPage() {
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    const isValidEmail = (email: string) => {
        return email.includes('@') && email.includes('.');
    };

    const sendResetEmail = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setMessage('');
        setLoading(true);

        if (!email.trim()) {
            setError('Email requis');
            setLoading(false);
            return;
        }

        if (!isValidEmail(email)) {
            setError('Email invalide');
            setLoading(false);
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/api/mail/forgot-password`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email })
            });

            const result = await response.json();

            if (response.ok) {
                setMessage(result.message || 'Si cette adresse email existe, un email de réinitialisation a été envoyé');
                setEmail('');
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
                        Mot de passe oublié
                    </CardTitle>
                    <CardDescription className="text-gray-600">
                        Entrez votre adresse email pour recevoir un lien de réinitialisation
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
                            <p className="text-xs mt-1">
                                📧 Vérifiez votre boîte email et cliquez sur le lien pour réinitialiser votre mot de passe.
                            </p>
                            <p className="text-xs mt-1 font-semibold">
                                ⏰ Le lien expire dans 1 heure.
                            </p>
                        </div>
                    )}
                    
                    <form onSubmit={sendResetEmail} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                placeholder="Entrez votre adresse email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                disabled={loading}
                                className="focus:ring-amber-500 focus:border-amber-500"
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-yellow-500 hover:bg-yellow-400 focus:ring-amber-500 text-black font-bold"
                        >
                            {loading ? 'Envoi en cours...' : 'Envoyer le lien de réinitialisation'}
                        </Button>
                    </form>
                    
                    <div className="text-center pt-4">
                        <p className="text-sm text-gray-600">
                            Vous vous souvenez de votre mot de passe ?{' '}
                            <Button
                                variant="link"
                                className="p-0 h-auto font-medium text-amber-500 hover:text-amber-600"
                                onClick={() => navigate('/login')}
                            >
                                Se connecter
                            </Button>
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

export default ForgotPasswordPage;