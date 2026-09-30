export type ApiErrorBody = {
  code: string;
  message_key: string;
  request_id: string;
  retryable: boolean;
  details: Record<string, unknown>;
};
export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    public requestId?: string,
  ) {
    super(code);
  }
}
export type User = {
  id: string;
  name: string;
  role: "client" | "support" | "admin";
};
export type ServiceStatus = { status: "ready"; mode: "mock"; phase: number };
function object(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === "object";
}
export function parseUser(value: unknown): User {
  if (
    !object(value) ||
    typeof value.id !== "string" ||
    value.id.length === 0 ||
    typeof value.name !== "string" ||
    !["client", "support", "admin"].includes(String(value.role))
  )
    throw new ApiError(502, "INVALID_RESPONSE");
  return value as User;
}
export function parseStatus(value: unknown): ServiceStatus {
  if (
    !object(value) ||
    value.status !== "ready" ||
    value.mode !== "mock" ||
    typeof value.phase !== "number"
  )
    throw new ApiError(502, "INVALID_RESPONSE");
  return value as ServiceStatus;
}
export async function request<T>(
  path: string,
  parse: (value: unknown) => T,
  options: RequestInit = {},
  envelope = false,
): Promise<T> {
  if (!path.startsWith("/api/") && path !== "/sanctum/csrf-cookie")
    throw new Error("Only same-origin portal endpoints are allowed");
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body && !(options.body instanceof FormData))
    headers.set("Content-Type", "application/json");
  const csrf = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.slice(11);
  if (csrf) headers.set("X-XSRF-TOKEN", decodeURIComponent(csrf));
  let response: Response;
  try {
    response = await fetch(path, {
      ...options,
      headers,
      signal: options.signal
        ? AbortSignal.any([options.signal, AbortSignal.timeout(15000)])
        : AbortSignal.timeout(15000),
      credentials: "include",
      cache: "no-store",
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError")
      throw error;
    throw new ApiError(0, "NETWORK_ERROR");
  }
  const id = response.headers.get("X-Request-ID") ?? undefined;
  let body: unknown;
  try {
    body = response.status === 204 ? { data: null } : await response.json();
  } catch {
    throw new ApiError(response.status, "INVALID_RESPONSE", id);
  }
  if (!response.ok) {
    const code =
      object(body) && object(body.error) && typeof body.error.code === "string"
        ? body.error.code
        : "REQUEST_FAILED";
    throw new ApiError(response.status, code, id);
  }
  if (!object(body) || !("data" in body))
    throw new ApiError(502, "INVALID_RESPONSE", id);
  return parse(envelope ? body : body.data);
}
export const api = {
  status: (signal?: AbortSignal) =>
    request("/api/v1/status", parseStatus, { signal }),
  session: () => request("/api/v1/session", parseUser),
  login: async (email: string, password: string) => {
    await request("/sanctum/csrf-cookie", () => null);
    return request("/api/v1/auth/login", parseUser, {
      method: "POST",
      body: JSON.stringify({ email, password }),
    });
  },
  logout: async () => {
    await request("/sanctum/csrf-cookie", () => null);
    return request("/api/v1/auth/logout", () => null, { method: "POST" });
  },
};
