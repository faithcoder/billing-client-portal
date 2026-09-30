import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  useRef,
} from "react";
import type { ReactNode } from "react";
import { api, ApiError } from "../api/client";
import type { User } from "../api/client";
const Context = createContext<{
  user: User | null;
  loading: boolean;
  error: boolean;
  reload: () => void;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
} | null>(null);
export function SessionProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const generation = useRef(0);
  const reload = useCallback(() => {
    const current = ++generation.current;
    setLoading(true);
    setError(false);
    api
      .session()
      .then((value) => {
        if (current === generation.current) setUser(value);
      })
      .catch((error) => {
        if (current !== generation.current) return;
        setUser(null);
        setError(!(error instanceof ApiError && error.status === 401));
      })
      .finally(() => {
        if (current === generation.current) setLoading(false);
      });
  }, []);
  useEffect(reload, [reload]);
  return (
    <Context.Provider
      value={{
        user,
        loading,
        error,
        reload,
        login: async (email, password) => {
          ++generation.current;
          setLoading(false);
          setUser(await api.login(email, password));
          setError(false);
        },
        logout: async () => {
          ++generation.current;
          setLoading(false);
          await api.logout();
          setUser(null);
        },
      }}
    >
      {children}
    </Context.Provider>
  );
}
export function useSession() {
  const value = useContext(Context);
  if (!value) throw new Error("Missing session provider");
  return value;
}
