import React from 'react';
import { createRoot } from 'react-dom/client';
import Signup from './components/Signup';
import './bootstrap';

// Create React root and render the Signup component
const container = document.getElementById('app');
const root = createRoot(container);
root.render(<Signup />);
