import { useCallback, useEffect, useRef, useState } from "react";
export function useResource<T>(load: () => Promise<T>, key: string) {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const generation = useRef(0);
  const loader = useRef(load);
  loader.current = load;
  const reload = useCallback(() => {
    const n = ++generation.current;
    setLoading(true);
    setError(null);
    setData(null);
    loader
      .current()
      .then((d) => {
        if (n === generation.current) setData(d);
      })
      .catch((e) => {
        if (n === generation.current) setError(e);
      })
      .finally(() => {
        if (n === generation.current) setLoading(false);
      });
  }, []);
  useEffect(() => {
    reload();
    return () => {
      generation.current++;
    };
  }, [key, reload]);
  return { data, loading, error, reload };
}
