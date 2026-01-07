import axios from 'axios';

// Create Axios instance for Laravel SPA
const api = axios.create({
  baseURL: '/',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor for CSRF token handling
api.interceptors.request.use(async (config) => {
  // Automatically get CSRF cookie if not present
  if (!document.cookie.includes('XSRF-TOKEN') && !config.url.includes('/sanctum/csrf-cookie')) {
    try {
      await api.get('/sanctum/csrf-cookie');
    } catch (error) {
      console.warn('Failed to get CSRF cookie:', error);
    }
  }

  return config;
});

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Handle CSRF token mismatch by refreshing and retrying
    if (error.response?.status === 419) {
      return api.get('/sanctum/csrf-cookie').then(() => {
        return api(error.config);
      });
    }

    return Promise.reject(error);
  }
);

// Helper methods for authentication
export const signup = (data) => api.post('/api/register', data);
export const login = (data) => api.post('/api/login', data);
export const logout = () => api.post('/api/logout');
export const getUser = () => api.get('/api/user');

// Auth API object for convenience
export const authAPI = {
  signup,
  login,
  logout,
  getUser,
};

export default api;
