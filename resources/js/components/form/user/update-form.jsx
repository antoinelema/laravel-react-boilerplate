import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Form, FormItem, FormLabel, FormControl, FormMessage, FormField } from '@/components/ui/form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { handleApiError } from '@/lib/secureApi';

const formSchema = z.object({
  name: z.string().min(2, { message: 'Nom requis.' }),
  firstname: z.string().min(2, { message: 'Prénom requis.' }),
  current_password: z.string().optional().or(z.literal('')),
  password: z.string().min(8, { message: 'Mot de passe trop court.' }).optional().or(z.literal('')),
  password_confirmation: z.string().optional().or(z.literal('')),
}).refine((data) => {
  // Si un nouveau mot de passe est fourni, l'ancien est requis
  if (data.password && data.password.length > 0) {
    return data.current_password && data.current_password.length > 0;
  }
  return true;
}, {
  message: 'Mot de passe actuel requis pour changer le mot de passe.',
  path: ['current_password'],
}).refine((data) => {
  // Vérifier que les mots de passe correspondent
  if (data.password && data.password.length > 0) {
    return data.password === data.password_confirmation;
  }
  return true;
}, {
  message: 'Les mots de passe ne correspondent pas.',
  path: ['password_confirmation'],
});

const UpdateUserForm = ({ user, onSuccess, onError, loading }) => {
  const form = useForm({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: user?.name || '',
      firstname: user?.firstname || '',
      current_password: '',
      password: '',
      password_confirmation: '',
    },
  });

  React.useEffect(() => {
    form.reset({
      name: user?.name || '',
      firstname: user?.firstname || '',
      current_password: '',
      password: '',
      password_confirmation: '',
    });
    // eslint-disable-next-line
  }, [user]);

  async function onSubmit(values) {
    const payload = {
      name: values.name,
      firstname: values.firstname,
    };
    // Si un mot de passe est fourni, on envoie current_password, password et password_confirmation
    if (values.password) {
      payload.current_password = values.current_password;
      payload.password = values.password;
      payload.password_confirmation = values.password_confirmation;
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
      const res = await fetch('/profile', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token || '',
        },
        credentials: 'include',
        body: JSON.stringify(payload),
      });
      if (res.ok) {
        onSuccess && onSuccess();
        form.reset({ ...values, current_password: '', password: '', password_confirmation: '' });
      } else {
        await handleApiError(res, 'Erreur lors de la mise à jour');
      }
    } catch (e) {
      onError && onError(e.message || 'Erreur réseau ou serveur. Veuillez réessayer.');
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="name"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Nom</FormLabel>
              <FormControl>
                <Input {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="firstname"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Prénom</FormLabel>
              <FormControl>
                <Input {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div>
          <label className="block text-sm font-medium">Adresse e-mail</label>
          <Input value={user?.email || ''} disabled className="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-500" />
        </div>
        <FormField
          control={form.control}
          name="current_password"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Mot de passe actuel</FormLabel>
              <FormControl>
                <Input type="password" autoComplete="current-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="password"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Nouveau mot de passe</FormLabel>
              <FormControl>
                <Input type="password" autoComplete="new-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="password_confirmation"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Confirmation du mot de passe</FormLabel>
              <FormControl>
                <Input type="password" autoComplete="new-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button className="w-full mt-4" type="submit" disabled={loading}>Mettre à jour</Button>
      </form>
    </Form>
  );
}

export default UpdateUserForm;
