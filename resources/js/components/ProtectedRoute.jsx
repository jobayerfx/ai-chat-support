import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const ProtectedRoute = ({
  children,
  requireAuth = true,
  requireTenant = false,
  requireOwner = false,
  fallbackPath = '/login',
  loadingComponent = null
}) => {
  const { isAuthenticated, user, tenant, loading } = useAuth();
  const location = useLocation();

  // Show loading component while checking auth status
  if (loading) {
    return loadingComponent || (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  // Check authentication requirement
  if (requireAuth && !isAuthenticated) {
    // Redirect to login with return URL
    return <Navigate to={fallbackPath} state={{ from: location }} replace />;
  }

  // Check tenant requirement
  if (requireTenant && !tenant) {
    // User is authenticated but doesn't have a tenant
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="max-w-md w-full space-y-8 text-center">
          <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <h2 className="text-2xl font-bold text-gray-900 mb-4">
              Access Restricted
            </h2>
            <p className="text-gray-600 mb-6">
              You need to be associated with a tenant to access this page.
            </p>
            <button
              onClick={() => window.location.href = '/signup'}
              className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              Create Tenant
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Check owner requirement
  if (requireOwner && (!user || !tenant || user.id !== tenant.owner_id)) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="max-w-md w-full space-y-8 text-center">
          <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <h2 className="text-2xl font-bold text-gray-900 mb-4">
              Owner Access Required
            </h2>
            <p className="text-gray-600 mb-6">
              Only tenant owners can access this page.
            </p>
            <button
              onClick={() => window.history.back()}
              className="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              Go Back
            </button>
          </div>
        </div>
      </div>
    );
  }

  // All checks passed, render children
  return children;
};

export default ProtectedRoute;
