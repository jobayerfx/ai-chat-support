import React, { useState } from 'react';

const ChatwootConnectForm = ({
  onSuccess,
  onError,
  submitButtonText = 'Connect Chatwoot',
  className = '',
  showLabels = true,
  compact = false
}) => {
  const [formData, setFormData] = useState({
    chatwoot_url: '',
    account_id: '',
    access_token: '',
  });

  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);

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

    // Chatwoot URL validation
    if (!formData.chatwoot_url.trim()) {
      newErrors.chatwoot_url = 'Chatwoot URL is required';
    } else {
      try {
        const url = new URL(formData.chatwoot_url);
        if (!url.protocol.startsWith('http')) {
          newErrors.chatwoot_url = 'URL must start with http:// or https://';
        }
      } catch {
        newErrors.chatwoot_url = 'Please enter a valid URL';
      }
    }

    // Account ID validation
    if (!formData.account_id.trim()) {
      newErrors.account_id = 'Account ID is required';
    } else if (!/^\d+$/.test(formData.account_id)) {
      newErrors.account_id = 'Account ID must be a number';
    }

    // Access token validation
    if (!formData.access_token.trim()) {
      newErrors.access_token = 'Access token is required';
    } else if (formData.access_token.length < 20) {
      newErrors.access_token = 'Access token appears to be too short';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    setLoading(true);
    setErrors({});

    try {
      const response = await fetch('/api/onboarding/chatwoot/connect', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();

      if (response.ok) {
        // Call success callback with response data
        if (onSuccess) {
          onSuccess(data);
        }
      } else {
        const errorMessage = data.errors
          ? Object.values(data.errors).flat().join(', ')
          : data.message || 'Connection failed';

        setErrors({ general: errorMessage });

        // Call error callback
        if (onError) {
          onError(errorMessage);
        }
      }
    } catch (error) {
      const errorMessage = 'Network error. Please check your connection and try again.';
      setErrors({ general: errorMessage });

      if (onError) {
        onError(errorMessage);
      }
    } finally {
      setLoading(false);
    }
  };

  const inputStyle = {
    width: '100%',
    padding: compact ? '0.5rem' : '0.75rem',
    border: '1px solid #d1d5db',
    borderRadius: '6px',
    fontSize: compact ? '0.875rem' : '1rem',
    backgroundColor: 'white',
    boxSizing: 'border-box',
    transition: 'border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out',
  };

  const inputErrorStyle = {
    ...inputStyle,
    borderColor: '#dc2626',
    boxShadow: '0 0 0 1px #dc2626',
  };

  const labelStyle = {
    display: 'block',
    fontSize: compact ? '0.75rem' : '0.875rem',
    fontWeight: '500',
    color: '#374151',
    marginBottom: compact ? '0.25rem' : '0.5rem',
  };

  const errorStyle = {
    color: '#dc2626',
    fontSize: compact ? '0.75rem' : '0.875rem',
    marginTop: '0.25rem',
  };

  const buttonStyle = {
    width: '100%',
    padding: compact ? '0.5rem 1rem' : '0.75rem 1rem',
    backgroundColor: loading ? '#9ca3af' : '#4f46e5',
    color: 'white',
    border: 'none',
    borderRadius: '6px',
    fontSize: compact ? '0.875rem' : '1rem',
    fontWeight: '500',
    cursor: loading ? 'not-allowed' : 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '0.5rem',
    transition: 'background-color 0.15s ease-in-out',
  };

  return (
    <div className={className}>
      <form onSubmit={handleSubmit}>
        {/* General error */}
        {errors.general && (
          <div style={{
            backgroundColor: '#fee2e2',
            border: '1px solid #fca5a5',
            borderRadius: '6px',
            padding: compact ? '0.5rem' : '0.75rem',
            marginBottom: '1rem',
            color: '#dc2626',
            fontSize: compact ? '0.75rem' : '0.875rem'
          }}>
            {errors.general}
          </div>
        )}

        {/* Chatwoot URL */}
        <div style={{ marginBottom: compact ? '0.75rem' : '1rem' }}>
          {showLabels && (
            <label htmlFor="chatwoot_url" style={labelStyle}>
              Chatwoot URL
            </label>
          )}
          <input
            id="chatwoot_url"
            name="chatwoot_url"
            type="url"
            placeholder="https://your-chatwoot-instance.com"
            value={formData.chatwoot_url}
            onChange={handleInputChange}
            style={errors.chatwoot_url ? inputErrorStyle : inputStyle}
            required
            disabled={loading}
          />
          {errors.chatwoot_url && (
            <p style={errorStyle}>{errors.chatwoot_url}</p>
          )}
        </div>

        {/* Account ID */}
        <div style={{ marginBottom: compact ? '0.75rem' : '1rem' }}>
          {showLabels && (
            <label htmlFor="account_id" style={labelStyle}>
              Account ID
            </label>
          )}
          <input
            id="account_id"
            name="account_id"
            type="number"
            placeholder="123"
            value={formData.account_id}
            onChange={handleInputChange}
            style={errors.account_id ? inputErrorStyle : inputStyle}
            required
            disabled={loading}
            min="1"
          />
          {errors.account_id && (
            <p style={errorStyle}>{errors.account_id}</p>
          )}
        </div>

        {/* Access Token */}
        <div style={{ marginBottom: compact ? '1rem' : '1.5rem' }}>
          {showLabels && (
            <label htmlFor="access_token" style={labelStyle}>
              Access Token
            </label>
          )}
          <input
            id="access_token"
            name="access_token"
            type="password"
            placeholder="Your Chatwoot access token"
            value={formData.access_token}
            onChange={handleInputChange}
            style={errors.access_token ? inputErrorStyle : inputStyle}
            required
            disabled={loading}
            autoComplete="off"
          />
          {errors.access_token && (
            <p style={errorStyle}>{errors.access_token}</p>
          )}
        </div>

        {/* Submit Button */}
        <button
          type="submit"
          disabled={loading}
          style={buttonStyle}
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
              Connecting...
            </>
          ) : (
            submitButtonText
          )}
        </button>
      </form>

      <style>
        {`
          @keyframes spin {
            to { transform: rotate(360deg); }
          }

          input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 1px #4f46e5;
          }

          button:hover:not(:disabled) {
            background-color: #4338ca;
          }

          button:active {
            transform: translateY(1px);
          }
        `}
      </style>
    </div>
  );
};

export default ChatwootConnectForm;
