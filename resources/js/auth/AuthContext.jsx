import React, { createContext, useContext, useState, useEffect } from 'react';
import { login, logout, getUser } from '../services/api';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [tenant, setTenant] = useState(null);
  const [loading, setLoading] = useState(true);

  // Auto-fetch user on app load
  useEffect(() => {
    fetchUser();
  }, []);

  // Fetch current authenticated user
  const fetchUser = async () => {
    try {
      const response = await getUser();
      const userData = response.data;

      setUser(userData);
      setTenant(userData.tenant || null);
    } catch (error) {
      // User is not authenticated
      setUser(null);
      setTenant(null);
    } finally {
      setLoading(false);
    }
  };

  // Login user
  const loginUser = async (credentials) => {
    const response = await login(credentials);
    const userData = response.data.user;

    setUser(userData);
    setTenant(userData.tenant || null);

    return response;
  };

  // Logout user
  const logoutUser = async () => {
    await logout();
    setUser(null);
    setTenant(null);
  };

  const value = {
    user,
    tenant,
    loading,
    fetchUser,
    login: loginUser,
    logout: logoutUser,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
};

export default AuthContext;
