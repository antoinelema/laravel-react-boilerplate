import React, { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { handleApiError } from '@/lib/secureApi';

export default function CreateUserForm({ onSuccess, onError, loading: loadingProp }) {
  const [form, setForm] = useState({ name: '', firstname: '', email: '', password: '', password_confirmation: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token || '',
        },
        credentials: 'include',
        body: JSON.stringify(form),
      });
      if (!res.ok) {
        await handleApiError(res, "Erreur lors de l'inscription");
      } else {
        onSuccess && onSuccess();
      }
    } catch (err) {
      setError(err.message || 'Erreur réseau ou serveur. Veuillez réessayer.');
      onError && onError(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <div className="text-red-500">{error}</div>}
      <Input
        name="name"
        type="text"
        placeholder="Nom"
        value={form.name}
        onChange={handleChange}
        required
      />
      <Input
        name="firstname"
        type="text"
        placeholder="Prénom"
        value={form.firstname}
        onChange={handleChange}
        required
      />
      <Input
        name="email"
        type="email"
        placeholder="Email"
        value={form.email}
        onChange={handleChange}
        required
      />
      <Input
        name="password"
        type="password"
        placeholder="Mot de passe"
        value={form.password}
        onChange={handleChange}
        required
      />
      <Input
        name="password_confirmation"
        type="password"
        placeholder="Confirmer le mot de passe"
        value={form.password_confirmation}
        onChange={handleChange}
        required
      />
      <Button type="submit" className="w-full" disabled={loadingProp || loading}>
        {(loadingProp || loading) ? 'Inscription...' : "S'inscrire"}
      </Button>
    </form>
  );
}
