import type { ReactNode } from "react";
import { LoadingState, ErrorState } from "./States";
import { ApiError } from "../api/client";
export function Resource({
  loading,
  error,
  reload,
  children,
}: {
  loading: boolean;
  error: unknown;
  reload: () => void;
  children: ReactNode;
}) {
  return loading ? (
    <LoadingState />
  ) : error ? (
    <ErrorState
      onRetry={reload}
      requestId={error instanceof ApiError ? error.requestId : undefined}
    />
  ) : (
    <>{children}</>
  );
}
