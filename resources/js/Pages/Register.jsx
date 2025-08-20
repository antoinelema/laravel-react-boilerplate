import React from 'react';
import { Button } from '../components/ui/button';
import CreateUserForm from '@/components/form/user/create-form';


export default function RegisterPage() {

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50">
      <div className="bg-white p-8 rounded shadow w-full max-w-md space-y-4">
        <h2 className="text-2xl font-bold mb-4">Inscription</h2>
        <CreateUserForm />
        <Button
          type="button"
          className="w-full mt-2 bg-gray-200 text-gray-700 hover:bg-gray-300"
          onClick={() => window.location.href = '/login'}
        >
          Déjà un compte ? Se connecter
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
          S'inscrire avec Google
        </Button>
      </div>
    </div>
  );
}
