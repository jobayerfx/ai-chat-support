import React, { createContext, useContext, useState, useEffect } from 'react';

const OfflineContext = createContext();

export const useOffline = () => {
  const context = useContext(OfflineContext);
  if (!context) {
    throw new Error('useOffline must be used within an OfflineProvider');
  }
  return context;
};

export const OfflineProvider = ({ children }) => {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [showBanner, setShowBanner] = useState(false);

  useEffect(() => {
    const handleOnline = () => {
      console.log('[Offline] Connection restored');
      setIsOnline(true);
      setShowBanner(false);
    };

    const handleOffline = () => {
      console.log('[Offline] Connection lost');
      setIsOnline(false);
      setShowBanner(true);
    };

    // Set initial state
    setIsOnline(navigator.onLine);
    setShowBanner(!navigator.onLine);

    // Add event listeners
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Cleanup
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Auto-hide banner after 5 seconds when coming back online
  useEffect(() => {
    if (isOnline && showBanner) {
      const timer = setTimeout(() => {
        setShowBanner(false);
      }, 5000);

      return () => clearTimeout(timer);
    }
  }, [isOnline, showBanner]);

  const dismissBanner = () => {
    setShowBanner(false);
  };

  const value = {
    isOnline,
    showBanner,
    dismissBanner,
  };

  return (
    <OfflineContext.Provider value={value}>
      {children}
    </OfflineContext.Provider>
  );
};
