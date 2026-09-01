import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import type { TzhUser } from '@/types';

const UserContext = createContext<TzhUser | null>(null);

export function useTzhUser() {
  return useContext(UserContext);
}

/**
 * Ports the inline <script> that used to sit at the very top of <head> in the
 * legacy monolith: gate the page behind checkAuth.php before rendering anything,
 * same as every other mobile/reg page in this app.
 */
export function AuthGate({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<TzhUser | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    fetch('checkAuth.php', { credentials: 'include' })
      .then((response) => {
        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
        return response.json();
      })
      .then((data: TzhUser) => {
        if (data.success) {
          setUser(data);
          setReady(true);
        } else {
          alert('You are not authorized to view this page. You must login first.');
          location.href = '/';
        }
      })
      .catch((error) => {
        console.error('Error:', error);
      });
  }, []);

  if (!ready) return null;

  return <UserContext.Provider value={user}>{children}</UserContext.Provider>;
}
