import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

// Import main App component
import App from './App.jsx';

// Create root element for React 18
const container = document.getElementById('app');
const root = createRoot(container);

// Render the app with BrowserRouter
// AuthContext will be added later in the App component
root.render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>
);
