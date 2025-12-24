import axios from 'axios';

// Configure axios for Laravel Sanctum SPA
const api = axios.create({
  baseURL: import.meta.env.VITE_APP_URL || 'http://localhost:8000',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor to handle CSRF tokens
api.interceptors.request.use(async (config) => {
  // Get CSRF cookie if not present
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
    if (error.response?.status === 401) {
      // Handle unauthorized access
      console.error('Unauthorized access - redirecting to login');
      // You can emit an event or call a logout function here
      window.location.href = '/login';
    }

    if (error.response?.status === 419) {
      // CSRF token mismatch
      console.error('CSRF token mismatch - refreshing token');
      // Try to refresh CSRF token
      return api.get('/sanctum/csrf-cookie').then(() => {
        // Retry the original request
        return api(error.config);
      });
    }

    return Promise.reject(error);
  }
);

// Auth API methods
export const authAPI = {
  // Get CSRF cookie
  getCsrfCookie: () => api.get('/sanctum/csrf-cookie'),

  // Register new user and tenant
  register: (userData) => api.post('/api/register', userData),

  // Login user
  login: (credentials) => api.post('/api/login', credentials),

  // Logout user
  logout: () => api.post('/api/logout'),

  // Get authenticated user
  getUser: () => api.get('/api/user'),
};

// Knowledge API methods
export const knowledgeAPI = {
  // Upload document
  uploadDocument: (formData) => api.post('/api/knowledge/upload', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  }),

  // Upload text content
  uploadText: (data) => api.post('/api/knowledge/upload-text', data),

  // Get all documents
  getDocuments: () => api.get('/api/knowledge'),

  // Get specific document
  getDocument: (id) => api.get(`/api/knowledge/${id}`),

  // Delete document
  deleteDocument: (id) => api.delete(`/api/knowledge/${id}`),

  // Reprocess document
  reprocessDocument: (id) => api.post(`/api/knowledge/${id}/reprocess`),
};

// Chatwoot API methods
export const chatwootAPI = {
  // Test connection
  testConnection: (data) => api.post('/api/chatwoot/test-connection', data),

  // Get inboxes
  getInboxes: (data) => api.post('/api/chatwoot/inboxes', data),

  // Connect inbox
  connect: (data) => api.post('/api/chatwoot/connect', data),

  // Get connections
  getConnections: () => api.get('/api/chatwoot/connections'),

  // Disconnect inbox
  disconnect: (id) => api.delete(`/api/chatwoot/connections/${id}`),
};

// AI Configuration API methods
export const aiConfigAPI = {
  // Get AI config
  getConfig: () => api.get('/api/ai-config'),

  // Update AI config
  updateConfig: (config) => api.put('/api/ai-config', config),

  // Reset to defaults
  resetConfig: () => api.post('/api/ai-config/reset'),
};

export default api;
