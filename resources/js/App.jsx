import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Signup from './components/Signup';
import Dashboard from './components/Dashboard';
import Login from './components/Login';

function App() {
  return (
    <AuthProvider>
      <Router>
        <div className="App">
          <Routes>
            {/* Public routes */}
            <Route path="/signup" element={<Signup />} />
            <Route path="/login" element={<Login />} />

            {/* Protected routes */}
            <Route
              path="/dashboard"
              element={
                <ProtectedRoute requireAuth={true} requireTenant={true}>
                  <Dashboard />
                </ProtectedRoute>
              }
            />

            {/* Owner-only routes (example) */}
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

            {/* Default redirect */}
            <Route
              path="/"
              element={
                <ProtectedRoute requireAuth={false}>
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
                </ProtectedRoute>
              }
            />
          </Routes>
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;
