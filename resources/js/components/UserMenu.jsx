import React from 'react';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from './ui/dropdown-menu';
import { Button } from '../components/ui/button';
import { useCurrentUser } from '../hooks/useCurrentUser';
import { Link, router } from '@inertiajs/react';

export default function UserMenu({ user: propUser }) {
  const hookUser = useCurrentUser();
  const user = propUser || hookUser;

  const handleLogout = () => {
    router.post('/logout', {}, {
      onFinish: () => router.visit('/login'),
    });
  };

  if (!user) return null;

  return (
    <div className="absolute top-4 right-8 z-50">
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" className="font-semibold px-4 py-2">
            {user.name}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem asChild>
            <Link href="/profile">Profile</Link>
          </DropdownMenuItem>
          <DropdownMenuItem onClick={handleLogout} variant="destructive">
            Disconnect
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
