import React from 'react';
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    router.post('/login', { email, password }, {
      onError: (errors) => {
        // Gestion robuste du throttling et des erreurs 429
        if ((errors.status && errors.status === 429) || (errors.message && errors.message.includes('Too Many Attempts'))) {
          setError('Trop de tentatives de connexion. Veuillez réessayer dans une minute.');
        } else if (errors.email) {
          setError(errors.email);
        } else if (errors.message) {
          setError(errors.message);
        } else {
          setError('Login failed');
        }
      },
      onSuccess: () => {
        setError('');
      },
      onFinish: () => {
        setLoading(false);
      },
    });
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50">
      <form onSubmit={handleSubmit} className="bg-white p-8 rounded shadow w-full max-w-md space-y-4">
        <h2 className="text-2xl font-bold mb-4">Login</h2>
        {error && <div className="text-red-500">{error}</div>}
        <Input
          type="email"
          placeholder="Email"
          value={email}
          onChange={e => setEmail(e.target.value)}
          required
        />
        <Input
          type="password"
          placeholder="Password"
          value={password}
          onChange={e => setPassword(e.target.value)}
          required
        />
        <Button type="submit" className="w-full" disabled={loading}>
          {loading ? 'Logging in...' : 'Login'}
        </Button>
        <Button
          type="button"
          className="w-full mt-2 bg-gray-200 text-gray-700 hover:bg-gray-300"
          onClick={() => router.visit('/register')}
        >
          Pas de compte ? S'inscrire
        </Button>
        <div className="flex items-center my-4">
          <div className="flex-grow border-t border-gray-200"></div>
          <span className="mx-2 text-gray-400">ou</span>
          <div className="flex-grow border-t border-gray-200"></div>
        </div>
        <Button
          type="button"
          className="w-full bg-red-500 hover:bg-red-600 text-white"
          onClick={() => window.location.href = '/auth/google'}
        >
          Se connecter avec Google
        </Button>
      </form>
    </div>
  );
}
