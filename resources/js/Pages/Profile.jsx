import React, { useState, useEffect } from 'react';
import UpdateUserForm from '../components/form/user/update-form';
import { Button } from '@/components/ui/button';

export default function ProfilePage() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    fetch('/user', { credentials: 'include' })
      .then(async res => {
        if (!res.ok) throw new Error('Not authenticated');
        const data = await res.json();
        setUser(data);
      })
      .catch(() => setError('Not authenticated'))
      .finally(() => setLoading(false));
    // eslint-disable-next-line
  }, []);

  function handleSuccess() {
    setSuccess('Profil mis à jour !');
    setError('');
    // Optionnel: re-fetch user
    fetch('/user', { credentials: 'include' })
      .then(async res => {
        if (!res.ok) return;
        const data = await res.json();
        setUser(data);
      });
  }
  function handleError(msg) {
    setError(msg);
    setSuccess('');
  }

  const handleLogout = async () => {
    await fetch('/logout', { method: 'POST', credentials: 'include' });
    Inertia.visit('/login');
  };

  if (loading) return <div className="flex items-center justify-center min-h-screen">Loading...</div>;
  if (error) return <div className="flex items-center justify-center min-h-screen text-red-500">{error}</div>;

  return (
    <div className="flex flex-col items-center justify-center min-h-screen bg-gray-50">
      <div className="bg-white p-8 rounded shadow w-full max-w-md space-y-4">
        <h2 className="text-2xl font-bold mb-4">Mon profil</h2>
        {success && <div className="text-green-600 text-center">{success}</div>}
        {error && <div className="text-red-600 text-center">{error}</div>}
        <UpdateUserForm
          user={user}
          onSuccess={handleSuccess}
          onError={handleError}
          loading={loading}
        />
        <Button className="w-full mt-4" onClick={handleLogout}>Déconnexion</Button>
      </div>
    </div>
  );
}
