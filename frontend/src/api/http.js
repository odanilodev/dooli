import axios from 'axios';

const TOKEN_KEY = 'delta-class-token';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
  headers: {
    Accept: 'application/json',
  },
});

export function getToken() {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch (e) {
    return null;
  }
}

export function setToken(token) {
  try {
    if (token) {
      localStorage.setItem(TOKEN_KEY, token);
    } else {
      localStorage.removeItem(TOKEN_KEY);
    }
  } catch (e) {
    /* localStorage unavailable */
  }
}

let onUnauthorized = null;

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler;
}

export function normalizeError(error) {
  const response = error.response;

  if (response) {
    return {
      status: response.status,
      message: response.data?.message || 'Não foi possível concluir a requisição.',
      code: response.data?.code || null,
      errors: response.data?.errors || null,
    };
  }

  return {
    status: 0,
    message: 'Não foi possível conectar ao servidor.',
    code: 'network_error',
    errors: null,
  };
}

api.interceptors.request.use((config) => {
  const token = getToken();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && onUnauthorized) {
      onUnauthorized();
    }

    return Promise.reject(normalizeError(error));
  },
);
