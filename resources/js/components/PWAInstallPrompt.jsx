import React, { useState, useEffect } from 'react';

const PWAInstallPrompt = () => {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [showInstallButton, setShowInstallButton] = useState(false);
  const [isInstalled, setIsInstalled] = useState(false);

  useEffect(() => {
    // Check if app is already installed
    const checkIfInstalled = () => {
      // Check if running in standalone mode (installed PWA)
      const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
      // Check if running as PWA
      const isInWebAppiOS = window.navigator.standalone === true;

      if (isStandalone || isInWebAppiOS) {
        setIsInstalled(true);
        return;
      }

      // Check if app was installed before (using localStorage)
      const wasInstalled = localStorage.getItem('pwa-installed') === 'true';
      if (wasInstalled) {
        setIsInstalled(true);
        return;
      }

      setIsInstalled(false);
    };

    checkIfInstalled();

    // Listen for the beforeinstallprompt event
    const handleBeforeInstallPrompt = (event) => {
      // Prevent the mini-infobar from appearing on mobile
      event.preventDefault();
      // Stash the event so it can be triggered later
      setDeferredPrompt(event);
      // Show the install button
      setShowInstallButton(true);

      console.log('[PWA] Install prompt captured');
    };

    // Listen for successful installation
    const handleAppInstalled = (event) => {
      console.log('[PWA] App was installed');
      setIsInstalled(true);
      setShowInstallButton(false);
      setDeferredPrompt(null);

      // Mark as installed in localStorage
      localStorage.setItem('pwa-installed', 'true');
    };

    // Listen for display mode changes (when app becomes standalone)
    const handleDisplayModeChange = (event) => {
      if (event.matches) {
        console.log('[PWA] App is now running in standalone mode');
        setIsInstalled(true);
        setShowInstallButton(false);
        localStorage.setItem('pwa-installed', 'true');
      }
    };

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);

    const mediaQuery = window.matchMedia('(display-mode: standalone)');
    mediaQuery.addEventListener('change', handleDisplayModeChange);

    // Check display mode on load
    if (mediaQuery.matches) {
      setIsInstalled(true);
      localStorage.setItem('pwa-installed', 'true');
    }

    // Cleanup
    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
      window.removeEventListener('appinstalled', handleAppInstalled);
      mediaQuery.removeEventListener('change', handleDisplayModeChange);
    };
  }, []);

  const handleInstallClick = async () => {
    if (!deferredPrompt) {
      console.warn('[PWA] No install prompt available');
      return;
    }

    // Show the install prompt
    deferredPrompt.prompt();

    // Wait for the user to respond to the prompt
    const { outcome } = await deferredPrompt.userChoice;

    console.log('[PWA] User response to install prompt:', outcome);

    // Reset the deferred prompt
    setDeferredPrompt(null);
    setShowInstallButton(false);

    if (outcome === 'accepted') {
      console.log('[PWA] User accepted the install prompt');
      setIsInstalled(true);
      localStorage.setItem('pwa-installed', 'true');
    } else {
      console.log('[PWA] User dismissed the install prompt');
    }
  };

  const handleDismiss = () => {
    setShowInstallButton(false);
    // Remember user dismissed for this session
    sessionStorage.setItem('pwa-install-dismissed', 'true');
  };

  // Don't show if already installed or user dismissed in this session
  if (isInstalled || !showInstallButton || sessionStorage.getItem('pwa-install-dismissed') === 'true') {
    return null;
  }

  return (
    <div
      style={{
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        backgroundColor: '#4F46E5',
        color: 'white',
        padding: '1rem',
        borderRadius: '8px',
        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
        zIndex: 1000,
        maxWidth: '300px',
        fontSize: '0.875rem',
        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: '0.75rem' }}>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: '600', marginBottom: '0.25rem' }}>
            Install AI Chat Support
          </div>
          <div style={{ opacity: 0.9, lineHeight: '1.4' }}>
            Get the full app experience with offline access and native features.
          </div>
        </div>
        <button
          onClick={handleDismiss}
          style={{
            background: 'transparent',
            border: 'none',
            color: 'white',
            cursor: 'pointer',
            padding: '0.25rem',
            opacity: 0.7,
            fontSize: '1.25rem',
            lineHeight: 1,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center'
          }}
          aria-label="Dismiss install prompt"
        >
          ×
        </button>
      </div>
      <div style={{ marginTop: '1rem', display: 'flex', gap: '0.5rem' }}>
        <button
          onClick={handleInstallClick}
          style={{
            backgroundColor: 'white',
            color: '#4F46E5',
            border: 'none',
            padding: '0.5rem 1rem',
            borderRadius: '6px',
            fontWeight: '500',
            cursor: 'pointer',
            fontSize: '0.875rem',
            flex: 1
          }}
        >
          Install App
        </button>
        <button
          onClick={handleDismiss}
          style={{
            backgroundColor: 'transparent',
            color: 'white',
            border: '1px solid rgba(255, 255, 255, 0.3)',
            padding: '0.5rem 1rem',
            borderRadius: '6px',
            fontWeight: '500',
            cursor: 'pointer',
            fontSize: '0.875rem'
          }}
        >
          Not Now
        </button>
      </div>
    </div>
  );
};

export default PWAInstallPrompt;
