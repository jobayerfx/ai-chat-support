import React from 'react';
import { Routes, Route } from 'react-router-dom';

// Import components
import Signup from './components/Signup';
import Dashboard from './components/Dashboard';
import Login from './components/Login';

// TODO: Import AuthProvider and ProtectedRoute when AuthContext is implemented
// import { AuthProvider } from './contexts/AuthContext';
// import ProtectedRoute from './components/ProtectedRoute';
// import PWAInstallPrompt from './components/PWAInstallPrompt';

function App() {
  return (
    // TODO: Wrap with AuthProvider when AuthContext is implemented
    // <AuthProvider>
    <div className="App">
      <Routes>
        {/* Public routes */}
        <Route path="/signup" element={<Signup />} />
        <Route path="/login" element={<Login />} />

        {/* TODO: Protected routes - uncomment when AuthContext is implemented */}
        {/*
        <Route
          path="/dashboard"
          element={
            <ProtectedRoute requireAuth={true} requireTenant={true}>
              <Dashboard />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin"
          element={
            <ProtectedRoute requireAuth={true} requireTenant={true} requireOwner={true}>
              <div className="min-h-screen flex items-center justify-center">
                <div className="text-center">
                  <h1 className="text-3xl font-bold text-gray-900 mb-4">
                    Admin Panel
                  </h1>
                  <p className="text-gray-600">
                    Only tenant owners can see this page.
                  </p>
                </div>
              </div>
            </ProtectedRoute>
          }
        />
        */}

        {/* Temporary unprotected dashboard for development */}
        <Route path="/dashboard" element={<Dashboard />} />

        {/* Default route */}
        <Route
          path="/"
          element={
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
              <div className="text-center">
                <h1 className="text-4xl font-bold text-gray-900 mb-4">
                  Welcome to AI Chat Support
                </h1>
                <p className="text-xl text-gray-600 mb-8">
                  AI-powered customer support for your business
                </p>
                <div className="space-x-4">
                  <a
                    href="/signup"
                    className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    Get Started
                  </a>
                  <a
                    href="/login"
                    className="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    Sign In
                  </a>
                </div>
              </div>
            </div>
          }
        />
      </Routes>

      {/* TODO: PWA Install Prompt - uncomment when implemented */}
      {/* <PWAInstallPrompt /> */}
    </div>
    // </AuthProvider>
  );
}

export default App;
