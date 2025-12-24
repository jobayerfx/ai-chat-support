import React, { createContext, useContext, useState, useEffect } from 'react';
import { authAPI } from '../services/api';

// Create the Auth Context
const AuthContext = createContext();

// Auth Provider Component
export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [tenant, setTenant] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  // Initialize auth state on app load
  useEffect(() => {
    checkAuthStatus();
  }, []);

  // Check if user is authenticated
  const checkAuthStatus = async () => {
    try {
      const response = await authAPI.getUser();
      const userData = response.data;

      setUser(userData);
      setTenant(userData.tenant || null);
      setIsAuthenticated(true);
    } catch (error) {
      // User is not authenticated
      setUser(null);
      setTenant(null);
      setIsAuthenticated(false);
    } finally {
      setLoading(false);
    }
  };

  // Login function
  const login = async (credentials) => {
    try {
      const response = await authAPI.login(credentials);
      const userData = response.data.user;

      setUser(userData);
      setTenant(userData.tenant || null);
      setIsAuthenticated(true);

      return { success: true, user: userData };
    } catch (error) {
      throw error;
    }
  };

  // Register function
  const register = async (userData) => {
    try {
      const response = await authAPI.register(userData);
      const userResponse = response.data.user;
      const tenantResponse = response.data.tenant;

      setUser(userResponse);
      setTenant(tenantResponse);
      setIsAuthenticated(true);

      return { success: true, user: userResponse, tenant: tenantResponse };
    } catch (error) {
      throw error;
    }
  };

  // Logout function
  const logout = async () => {
    try {
      await authAPI.logout();
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Always clear local state
      setUser(null);
      setTenant(null);
      setIsAuthenticated(false);
    }
  };

  // Update user info
  const updateUser = (userData) => {
    setUser(prevUser => ({ ...prevUser, ...userData }));
  };

  // Update tenant info
  const updateTenant = (tenantData) => {
    setTenant(prevTenant => ({ ...prevTenant, ...tenantData }));
  };

  // Get tenant ID (useful for API calls)
  const getTenantId = () => {
    return tenant?.id || null;
  };

  // Check if user is tenant owner
  const isTenantOwner = () => {
    return user && tenant && user.id === tenant.owner_id;
  };

  // Context value
  const value = {
    // State
    user,
    tenant,
    loading,
    isAuthenticated,

    // Actions
    login,
    register,
    logout,
    updateUser,
    updateTenant,

    // Utilities
    getTenantId,
    isTenantOwner,
    checkAuthStatus,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

// Custom hook to use auth context
export const useAuth = () => {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
};

// Hook specifically for tenant operations
export const useTenant = () => {
  const { tenant, getTenantId, isTenantOwner, updateTenant } = useAuth();

  return {
    tenant,
    tenantId: getTenantId(),
    isTenantOwner: isTenantOwner(),
    updateTenant,
  };
};

export default AuthContext;
