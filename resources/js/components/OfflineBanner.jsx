import React from 'react';
import { useOffline } from '../contexts/OfflineContext';

const OfflineBanner = () => {
  const { showBanner, dismissBanner } = useOffline();

  if (!showBanner) {
    return null;
  }

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        backgroundColor: '#dc2626',
        color: 'white',
        padding: '0.75rem 1rem',
        textAlign: 'center',
        fontSize: '0.875rem',
        fontWeight: '500',
        zIndex: 9999,
        boxShadow: '0 2px 4px rgba(0, 0, 0, 0.1)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '0.5rem'
      }}
    >
      <svg
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
        <line x1="12" y1="2" x2="12" y2="12"></line>
        <line x1="8" y1="6" x2="12" y2="10"></line>
        <line x1="16" y1="6" x2="12" y2="10"></line>
      </svg>
      <span>You are currently offline. Some features may be limited.</span>
      <button
        onClick={dismissBanner}
        style={{
          background: 'transparent',
          border: 'none',
          color: 'white',
          cursor: 'pointer',
          padding: '0.25rem',
          marginLeft: '0.5rem',
          borderRadius: '0.25rem',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center'
        }}
        aria-label="Dismiss offline banner"
      >
        <svg
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
  );
};

export default OfflineBanner;
