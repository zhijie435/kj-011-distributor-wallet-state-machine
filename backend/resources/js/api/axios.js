import axios from 'axios';
import store from '../store';
import router from '../router';

const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

api.interceptors.request.use(config => {
  const token = store.getters['auth/isAuthenticated']
    ? store.state.auth.token
    : null;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {
      store.commit('auth/CLEAR_AUTH');
      router.push({ name: 'login' });
    }

    return Promise.reject(error);
  }
);

export default api;
