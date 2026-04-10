import Constants from "expo-constants";

const extra = Constants.expoConfig?.extra as { apiUrl?: string } | undefined;
const fallback = "http://127.0.0.1:8000/api";

export function getApiBase(): string {
  return process.env.EXPO_PUBLIC_API_URL ?? extra?.apiUrl ?? fallback;
}

export async function apiFetch(
  path: string,
  token: string | null,
  init: RequestInit = {}
): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }
  return fetch(`${getApiBase()}${path}`, { ...init, headers });
}
