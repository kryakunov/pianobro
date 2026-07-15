async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(data.error || `HTTP ${res.status}`);
  }
  return data;
}

let currentUser = null;

export function getUser() {
  return currentUser;
}

export function isLoggedIn() {
  return currentUser !== null;
}

export async function initAuth() {
  try {
    const data = await fetchJson('/api/auth/me');
    currentUser = data.user ?? null;
  } catch {
    currentUser = null;
  }
  return currentUser;
}

export async function register(name, email, password, passwordConfirm, honeypot = '') {
  const data = await fetchJson('/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      name,
      email,
      password,
      passwordConfirm,
      website: honeypot,
    }),
  });
  currentUser = data.user;
  return currentUser;
}

export async function login(email, password) {
  const data = await fetchJson('/api/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
  currentUser = data.user;
  return currentUser;
}

export async function logout() {
  await fetchJson('/api/auth/logout', { method: 'POST' });
  currentUser = null;
}

export async function saveSessionStats(payload) {
  if (!isLoggedIn()) return false;
  try {
    await fetchJson('/api/stats/session', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    return true;
  } catch {
    return false;
  }
}

export async function loadNoteStats() {
  if (!isLoggedIn()) return null;
  return fetchJson('/api/stats/notes');
}

export async function loadOAuthProviders() {
  try {
    const data = await fetchJson('/api/auth/oauth/providers');
    return data.providers ?? [];
  } catch {
    return [];
  }
}

export function redirectToOAuth(provider) {
  window.location.href = `/api/auth/oauth/${encodeURIComponent(provider)}`;
}
