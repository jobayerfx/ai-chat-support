import React from 'react';
import { Routes, Route } from 'react-router-dom';

// Import AuthContext and components
import { AuthProvider } from './auth/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Signup from './pages/Signup';
import Dashboard from './pages/Dashboard';
import Login from './components/Login';

function App() {
  return (
    <AuthProvider>
      <div className="App">
        <Routes>
          {/* Public routes */}
          <Route path="/signup" element={<Signup />} />
          <Route path="/login" element={<Login />} />

          {/* Protected routes */}
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute>
            }
          />

          {/* Default route */}
          <Route
            path="/"
            element={
              <div style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: '#f8fafc'
              }}>
                <div style={{ textAlign: 'center' }}>
                  <h1 style={{
                    fontSize: '2.25rem',
                    fontWeight: 'bold',
                    color: '#1f2937',
                    marginBottom: '1rem'
                  }}>
                    Welcome to AI Chat Support
                  </h1>
                  <p style={{
                    fontSize: '1.25rem',
                    color: '#6b7280',
                    marginBottom: '2rem'
                  }}>
                    AI-powered customer support for your business
                  </p>
                  <div style={{ display: 'flex', gap: '1rem', justifyContent: 'center' }}>
                    <a
                      href="/signup"
                      style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        padding: '0.75rem 1.5rem',
                        border: '1px solid transparent',
                        borderRadius: '0.375rem',
                        fontSize: '1rem',
                        fontWeight: '500',
                        color: 'white',
                        backgroundColor: '#4f46e5',
                        textDecoration: 'none'
                      }}
                    >
                      Get Started
                    </a>
                    <a
                      href="/login"
                      style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        padding: '0.75rem 1.5rem',
                        border: '1px solid #d1d5db',
                        borderRadius: '0.375rem',
                        fontSize: '1rem',
                        fontWeight: '500',
                        color: '#374151',
                        backgroundColor: 'white',
                        textDecoration: 'none'
                      }}
                    >
                      Sign In
                    </a>
                  </div>
                </div>
              </div>
            }
          />
        </Routes>
      </div>
    </AuthProvider>
  );
}

export default App;
