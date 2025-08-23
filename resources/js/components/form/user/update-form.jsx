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
  password: z.string().min(8, { message: 'Mot de passe trop court.' }).optional().or(z.literal('')),
  password_confirmation: z.string().optional().or(z.literal('')),
});

const UpdateUserForm = ({ user, onSuccess, onError, loading }) => {
  const form = useForm({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: user?.name || '',
      firstname: user?.firstname || '',
      password: '',
      password_confirmation: '',
    },
  });

  React.useEffect(() => {
    form.reset({
      name: user?.name || '',
      firstname: user?.firstname || '',
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
    // Si un mot de passe est fourni, on envoie aussi password_confirmation
    if (values.password) {
      payload.password = values.password;
      payload.password_confirmation = values.password_confirmation;
    }
    // Toujours envoyer password_confirmation si le champ est rempli (même si password vide)
    if (!values.password && values.password_confirmation) {
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
        form.reset({ ...values, password: '', password_confirmation: '' });
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
