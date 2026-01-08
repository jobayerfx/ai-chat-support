import React, { useState, useEffect } from 'react';
import { signup } from '../services/api';

const Signup = () => {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    tenant_name: '',
    domain: '',
  });

  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [isOnline, setIsOnline] = useState(navigator.onLine);

  // Handle online/offline status
  useEffect(() => {
    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));

    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => ({
        ...prev,
        [name]: null
      }));
    }
  };

  const validateForm = () => {
    const newErrors = {};

    // Name validation
    if (!formData.name.trim()) {
      newErrors.name = 'Full name is required';
    }

    // Email validation
    if (!formData.email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'Email is invalid';
    }

    // Password validation
    if (!formData.password) {
      newErrors.password = 'Password is required';
    } else if (formData.password.length < 8) {
      newErrors.password = 'Password must be at least 8 characters';
    }

    // Company name validation
    if (!formData.tenant_name.trim()) {
      newErrors.tenant_name = 'Company name is required';
    }

    // Domain validation
    if (!formData.domain.trim()) {
      newErrors.domain = 'Domain is required';
    } else if (!/^[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/.test(formData.domain)) {
      newErrors.domain = 'Domain must be in format: domain.tld (e.g., mycompany.com)';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    // Check if offline
    if (!isOnline) {
      setErrors({
        general: 'You appear to be offline. Please check your internet connection and try again.'
      });
      return;
    }

    setLoading(true);
    setErrors({});

    try {
      await signup(formData);

      setSuccess(true);

      // Redirect to dashboard after successful registration
      setTimeout(() => {
        window.location.href = '/dashboard';
      }, 2000);

    } catch (error) {
      let errorMessage = 'Something went wrong. Please try again.';

      if (!navigator.onLine) {
        errorMessage = 'You appear to be offline. Please check your internet connection and try again.';
      } else if (error.code === 'NETWORK_ERROR' || error.message?.includes('Network Error')) {
        errorMessage = 'Unable to connect to our servers. Please check your internet connection and try again.';
      } else if (error.response?.status === 422 && error.response?.data?.errors) {
        // Validation errors are already handled above
        return;
      } else if (error.response?.status === 429) {
        errorMessage = 'Too many requests. Please wait a moment and try again.';
      } else if (error.response?.status >= 500) {
        errorMessage = 'Our servers are experiencing issues. Please try again in a few minutes.';
      } else if (error.response?.data?.message) {
        errorMessage = error.response.data.message;
      }

      setErrors({ general: errorMessage });
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: '#f8fafc',
        padding: '1rem'
      }}>
        <div style={{
          maxWidth: '400px',
          width: '100%',
          textAlign: 'center'
        }}>
          <div style={{
            backgroundColor: '#d1fae5',
            borderRadius: '8px',
            padding: '1rem',
            marginBottom: '1rem'
          }}>
            <svg style={{
              width: '3rem',
              height: '3rem',
              color: '#10b981',
              margin: '0 auto 1rem'
            }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
            <h2 style={{
              fontSize: '1.5rem',
              fontWeight: 'bold',
              color: '#065f46',
              marginBottom: '0.5rem'
            }}>
              Registration Successful!
            </h2>
            <p style={{
              color: '#047857',
              fontSize: '0.875rem'
            }}>
              Welcome to our platform. Redirecting you to your dashboard...
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: '#f8fafc',
      padding: '1rem'
    }}>
      <div style={{
        maxWidth: '400px',
        width: '100%',
        backgroundColor: 'white',
        borderRadius: '8px',
        boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
        padding: '2rem'
      }}>
        <div style={{ marginBottom: '2rem' }}>
          <h2 style={{
            fontSize: '1.875rem',
            fontWeight: 'bold',
            color: '#1f2937',
            textAlign: 'center',
            marginBottom: '0.5rem'
          }}>
            Create your account
          </h2>
          <p style={{
            color: '#6b7280',
            textAlign: 'center',
            fontSize: '0.875rem'
          }}>
            Set up your company and get started
          </p>
        </div>

        <form onSubmit={handleSubmit}>
          {/* Offline indicator */}
          {!isOnline && (
            <div style={{
              backgroundColor: '#fef3c7',
              border: '1px solid #f59e0b',
              borderRadius: '6px',
              padding: '0.75rem',
              marginBottom: '1rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem'
            }}>
              <svg style={{
                width: '1rem',
                height: '1rem',
                color: '#d97706'
              }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              <p style={{
                color: '#92400e',
                fontSize: '0.875rem',
                margin: 0
              }}>
                You're currently offline. Please check your internet connection.
              </p>
            </div>
          )}

          {errors.general && (
            <div style={{
              backgroundColor: '#fee2e2',
              borderRadius: '6px',
              padding: '0.75rem',
              marginBottom: '1rem'
            }}>
              <p style={{
                color: '#dc2626',
                fontSize: '0.875rem',
                margin: 0
              }}>
                {errors.general}
              </p>
            </div>
          )}

          <div style={{ marginBottom: '1.5rem' }}>
            <label htmlFor="name" style={{
              display: 'block',
              fontSize: '0.875rem',
              fontWeight: '500',
              color: '#374151',
              marginBottom: '0.5rem'
            }}>
              Full Name
            </label>
            <input
              id="name"
              name="name"
              type="text"
              required
              style={{
                width: '100%',
                padding: '0.75rem',
                border: errors.name ? '1px solid #dc2626' : '1px solid #d1d5db',
                borderRadius: '6px',
                fontSize: '1rem',
                backgroundColor: 'white',
                boxSizing: 'border-box'
              }}
              placeholder="Enter your full name"
              value={formData.name}
              onChange={handleInputChange}
            />
            {errors.name && (
              <p style={{
                color: '#dc2626',
                fontSize: '0.75rem',
                marginTop: '0.25rem'
              }}>
                {errors.name}
              </p>
            )}
          </div>

          <div style={{ marginBottom: '1.5rem' }}>
            <label htmlFor="email" style={{
              display: 'block',
              fontSize: '0.875rem',
              fontWeight: '500',
              color: '#374151',
              marginBottom: '0.5rem'
            }}>
              Email
            </label>
            <input
              id="email"
              name="email"
              type="email"
              required
              style={{
                width: '100%',
                padding: '0.75rem',
                border: errors.email ? '1px solid #dc2626' : '1px solid #d1d5db',
                borderRadius: '6px',
                fontSize: '1rem',
                backgroundColor: 'white',
                boxSizing: 'border-box'
              }}
              placeholder="Enter your email"
              value={formData.email}
              onChange={handleInputChange}
            />
            {errors.email && (
              <p style={{
                color: '#dc2626',
                fontSize: '0.75rem',
                marginTop: '0.25rem'
              }}>
                {errors.email}
              </p>
            )}
          </div>

          <div style={{ marginBottom: '1.5rem' }}>
            <label htmlFor="password" style={{
              display: 'block',
              fontSize: '0.875rem',
              fontWeight: '500',
              color: '#374151',
              marginBottom: '0.5rem'
            }}>
              Password
            </label>
            <input
              id="password"
              name="password"
              type="password"
              required
              style={{
                width: '100%',
                padding: '0.75rem',
                border: errors.password ? '1px solid #dc2626' : '1px solid #d1d5db',
                borderRadius: '6px',
                fontSize: '1rem',
                backgroundColor: 'white',
                boxSizing: 'border-box'
              }}
              placeholder="Create a password"
              value={formData.password}
              onChange={handleInputChange}
            />
            {errors.password && (
              <p style={{
                color: '#dc2626',
                fontSize: '0.75rem',
                marginTop: '0.25rem'
              }}>
                {errors.password}
              </p>
            )}
          </div>

          <div style={{ marginBottom: '1.5rem' }}>
            <label htmlFor="password_confirmation" style={{
              display: 'block',
              fontSize: '0.875rem',
              fontWeight: '500',
              color: '#374151',
              marginBottom: '0.5rem'
            }}>
              Confirm Password
            </label>
            <input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              required
              style={{
                width: '100%',
                padding: '0.75rem',
                border: errors.password_confirmation ? '1px solid #dc2626' : '1px solid #d1d5db',
                borderRadius: '6px',
                fontSize: '1rem',
                backgroundColor: 'white',
                boxSizing: 'border-box'
              }}
              placeholder="Confirm your password"
              value={formData.password_confirmation}
              onChange={handleInputChange}
            />
            {errors.password_confirmation && (
              <p style={{
                color: '#dc2626',
                fontSize: '0.75rem',
                marginTop: '0.25rem'
              }}>
                {errors.password_confirmation}
              </p>
            )}
          </div>

          <div style={{ marginBottom: '1.5rem' }}>
            <label htmlFor="tenant_name" style={{
              display: 'block',
              fontSize: '0.875rem',
              fontWeight: '500',
              color: '#374151',
              marginBottom: '0.5rem'
            }}>
              Company Name
            </label>
            <input
              id="tenant_name"
              name="tenant_name"
              type="text"
              required
              style={{
                width: '100%',
                padding: '0.75rem',
                border: errors.tenant_name ? '1px solid #dc2626' : '1px solid #d1d5db',
                borderRadius: '6px',
                fontSize: '1rem',
                backgroundColor: 'white',
                boxSizing: 'border-box'
              }}
              placeholder="Enter your company name"
              value={formData.tenant_name}
              onChange={handleInputChange}
            />
            {errors.tenant_name && (
              <p style={{
                color: '#dc2626',
                fontSize: '0.75rem',
                marginTop: '0.25rem'
              }}>
                {errors.tenant_name}
              </p>
            )}
          </div>

          <div style={{ marginBottom: '2rem' }}>
            <label htmlFor="domain" style={{
              display: 'block',
              fontSize: '0.875rem',
              fontWeight: '500',
              color: '#374151',
              marginBottom: '0.5rem'
            }}>
              Domain
            </label>
            <input
              id="domain"
              name="domain"
              type="text"
              required
              style={{
                width: '100%',
                padding: '0.75rem',
                border: errors.domain ? '1px solid #dc2626' : '1px solid #d1d5db',
                borderRadius: '6px',
                fontSize: '1rem',
                backgroundColor: 'white',
                boxSizing: 'border-box'
              }}
              placeholder="Enter your domain (e.g., mycompany.com or mycompany-com.com)"
              value={formData.domain}
              onChange={handleInputChange}
            />
            {errors.domain && (
              <p style={{
                color: '#dc2626',
                fontSize: '0.75rem',
                marginTop: '0.25rem'
              }}>
                {errors.domain}
              </p>
            )}
          </div>

          <button
            type="submit"
            disabled={loading}
            style={{
              width: '100%',
              padding: '0.75rem 1rem',
              backgroundColor: loading ? '#9ca3af' : '#4f46e5',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              fontSize: '1rem',
              fontWeight: '500',
              cursor: loading ? 'not-allowed' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '0.5rem'
            }}
          >
            {loading ? (
              <>
                <svg style={{
                  width: '1rem',
                  height: '1rem',
                  animation: 'spin 1s linear infinite'
                }} fill="none" viewBox="0 0 24 24">
                  <circle style={{
                    opacity: 0.25
                  }} cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path style={{
                    opacity: 0.75
                  }} fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Creating Workspace...
              </>
            ) : (
              'Create Workspace'
            )}
          </button>

          <div style={{
            textAlign: 'center',
            marginTop: '1.5rem'
          }}>
            <p style={{
              fontSize: '0.875rem',
              color: '#6b7280'
            }}>
              Already have an account?{' '}
              <a
                href="/login"
                style={{
                  color: '#4f46e5',
                  textDecoration: 'none'
                }}
              >
                Sign in
              </a>
            </p>
          </div>
        </form>
      </div>

      <style>
        {`
          @keyframes spin {
            to {
              transform: rotate(360deg);
            }
          }
        `}
      </style>
    </div>
  );
};

export default Signup;
