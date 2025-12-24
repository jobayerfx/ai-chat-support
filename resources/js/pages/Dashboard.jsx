import React from 'react';
import { useAuth } from '../auth/AuthContext';

const Dashboard = () => {
  const { user, tenant, logout } = useAuth();

  const handleLogout = async () => {
    await logout();
  };

  return (
    <div>
      <h1>Welcome to your workspace</h1>

      {user && (
        <div>
          <p>User: {user.name}</p>
          <p>Email: {user.email}</p>
        </div>
      )}

      {tenant && (
        <div>
          <p>Tenant: {tenant.name}</p>
          <p>Domain: {tenant.domain}</p>
        </div>
      )}

      <button onClick={handleLogout}>
        Logout
      </button>
    </div>
  );
};

export default Dashboard;
