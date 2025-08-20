import { usePage } from '@inertiajs/react';

export function useCurrentUser() {
  const page = usePage();
  const user = page.props?.auth?.user ?? null;
  return user;
}
