import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

const Onboarding = () => {
  const [currentStep, setCurrentStep] = useState(1);
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const navigate = useNavigate();

  // Form states
  const [chatwootForm, setChatwootForm] = useState({
    chatwoot_url: '',
    account_id: '',
    access_token: '',
  });

  const [aiEnabled, setAiEnabled] = useState(false);
  const [errors, setErrors] = useState({});

  // Fetch onboarding status on load
  useEffect(() => {
    fetchOnboardingStatus();
  }, []);

  const fetchOnboardingStatus = async () => {
    try {
      const response = await fetch('/api/onboarding/status');
      const data = await response.json();

      if (response.ok) {
        setStatus(data);

        // Determine current step based on completion status
        if (data.completed) {
          navigate('/dashboard');
          return;
        } else if (data.ai_enabled) {
          setCurrentStep(3);
          setAiEnabled(true);
        } else if (data.chatwoot_connected) {
          setCurrentStep(2);
        } else {
          setCurrentStep(1);
        }
      } else {
        setErrors({ general: data.error || 'Failed to load onboarding status' });
      }
    } catch (error) {
      setErrors({ general: 'Network error. Please check your connection.' });
    } finally {
      setLoading(false);
    }
  };

  const handleChatwootSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});

    try {
      const response = await fetch('/api/onboarding/chatwoot/connect', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(chatwootForm),
      });

      const data = await response.json();

      if (response.ok) {
        setStatus(prev => ({ ...prev, chatwoot_connected: true }));
        setCurrentStep(2);
      } else {
        setErrors(data.errors || { general: data.message });
      }
    } catch (error) {
      setErrors({ general: 'Network error. Please try again.' });
    } finally {
      setSaving(false);
    }
  };

  const handleAiToggle = async () => {
    setSaving(true);
    setErrors({});

    try {
      const response = await fetch('/api/onboarding/ai/enable', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ enabled: !aiEnabled }),
      });

      const data = await response.json();

      if (response.ok && data.success) {
        setAiEnabled(!aiEnabled);
        setStatus(prev => ({ ...prev, ai_enabled: !aiEnabled }));

        if (!aiEnabled) {
          // If enabling AI, move to completion step
          setTimeout(() => {
            setCurrentStep(3);
          }, 1000);
        }
      } else {
        setErrors({ general: data.message });
      }
    } catch (error) {
      setErrors({ general: 'Network error. Please try again.' });
    } finally {
      setSaving(false);
    }
  };

  const handleFinish = () => {
    navigate('/dashboard');
  };

  const steps = [
    { id: 1, title: 'Connect Chatwoot', completed: status?.chatwoot_connected },
    { id: 2, title: 'Enable AI', completed: status?.ai_enabled },
    { id: 3, title: 'Complete Setup', completed: status?.completed },
  ];

  if (loading) {
    return (
      <div style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: '#f8fafc'
      }}>
        <div style={{
          width: '3rem',
          height: '3rem',
          border: '4px solid #e5e7eb',
          borderTop: '4px solid #4f46e5',
          borderRadius: '50%',
          animation: 'spin 1s linear infinite'
        }}></div>
        <style>{`
          @keyframes spin {
            to { transform: rotate(360deg); }
          }
        `}</style>
      </div>
    );
  }

  return (
    <div style={{
      minHeight: '100vh',
      backgroundColor: '#f8fafc',
      padding: '1rem'
    }}>
      <div style={{
        maxWidth: '600px',
        margin: '0 auto',
        padding: '2rem 1rem'
      }}>
        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
          <h1 style={{
            fontSize: '1.875rem',
            fontWeight: 'bold',
            color: '#1f2937',
            marginBottom: '0.5rem'
          }}>
            Welcome to AI Chat Support
          </h1>
          <p style={{
            color: '#6b7280',
            fontSize: '1rem'
          }}>
            Let's get your workspace set up in just a few steps
          </p>
        </div>

        {/* Progress Indicator */}
        <div style={{
          marginBottom: '2rem',
          padding: '1.5rem',
          backgroundColor: 'white',
          borderRadius: '8px',
          boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)'
        }}>
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            marginBottom: '1rem'
          }}>
            {steps.map((step, index) => (
              <div key={step.id} style={{ display: 'flex', alignItems: 'center' }}>
                <div style={{
                  width: '2.5rem',
                  height: '2.5rem',
                  borderRadius: '50%',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  backgroundColor: step.completed ? '#10b981' : currentStep === step.id ? '#4f46e5' : '#e5e7eb',
                  color: step.completed || currentStep === step.id ? 'white' : '#6b7280',
                  fontWeight: 'bold',
                  fontSize: '0.875rem',
                  marginRight: '0.5rem'
                }}>
                  {step.completed ? '✓' : step.id}
                </div>
                <span style={{
                  fontSize: '0.875rem',
                  fontWeight: currentStep === step.id ? '600' : '400',
                  color: currentStep === step.id ? '#1f2937' : '#6b7280'
                }}>
                  {step.title}
                </span>
                {index < steps.length - 1 && (
                  <div style={{
                    flex: 1,
                    height: '2px',
                    backgroundColor: step.completed ? '#10b981' : '#e5e7eb',
                    marginLeft: '1rem',
                    marginRight: '1rem'
                  }}></div>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Error Display */}
        {errors.general && (
          <div style={{
            backgroundColor: '#fee2e2',
            border: '1px solid #fca5a5',
            borderRadius: '6px',
            padding: '0.75rem',
            marginBottom: '1.5rem',
            color: '#dc2626',
            fontSize: '0.875rem'
          }}>
            {errors.general}
          </div>
        )}

        {/* Step Content */}
        <div style={{
          backgroundColor: 'white',
          borderRadius: '8px',
          padding: '2rem',
          boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)'
        }}>
          {currentStep === 1 && (
            <div>
              <h2 style={{
                fontSize: '1.25rem',
                fontWeight: 'bold',
                color: '#1f2937',
                marginBottom: '1rem'
              }}>
                Connect Your Chatwoot Account
              </h2>
              <p style={{
                color: '#6b7280',
                marginBottom: '1.5rem',
                fontSize: '0.875rem'
              }}>
                Connect your Chatwoot instance to enable AI-powered customer support.
              </p>

              <form onSubmit={handleChatwootSubmit}>
                <div style={{ marginBottom: '1rem' }}>
                  <label style={{
                    display: 'block',
                    fontSize: '0.875rem',
                    fontWeight: '500',
                    color: '#374151',
                    marginBottom: '0.5rem'
                  }}>
                    Chatwoot URL
                  </label>
                  <input
                    type="url"
                    placeholder="https://your-chatwoot-instance.com"
                    value={chatwootForm.chatwoot_url}
                    onChange={(e) => setChatwootForm(prev => ({ ...prev, chatwoot_url: e.target.value }))}
                    style={{
                      width: '100%',
                      padding: '0.75rem',
                      border: '1px solid #d1d5db',
                      borderRadius: '6px',
                      fontSize: '1rem',
                      boxSizing: 'border-box'
                    }}
                    required
                  />
                </div>

                <div style={{ marginBottom: '1rem' }}>
                  <label style={{
                    display: 'block',
                    fontSize: '0.875rem',
                    fontWeight: '500',
                    color: '#374151',
                    marginBottom: '0.5rem'
                  }}>
                    Account ID
                  </label>
                  <input
                    type="number"
                    placeholder="123"
                    value={chatwootForm.account_id}
                    onChange={(e) => setChatwootForm(prev => ({ ...prev, account_id: e.target.value }))}
                    style={{
                      width: '100%',
                      padding: '0.75rem',
                      border: '1px solid #d1d5db',
                      borderRadius: '6px',
                      fontSize: '1rem',
                      boxSizing: 'border-box'
                    }}
                    required
                  />
                </div>

                <div style={{ marginBottom: '1.5rem' }}>
                  <label style={{
                    display: 'block',
                    fontSize: '0.875rem',
                    fontWeight: '500',
                    color: '#374151',
                    marginBottom: '0.5rem'
                  }}>
                    Access Token
                  </label>
                  <input
                    type="password"
                    placeholder="Your Chatwoot access token"
                    value={chatwootForm.access_token}
                    onChange={(e) => setChatwootForm(prev => ({ ...prev, access_token: e.target.value }))}
                    style={{
                      width: '100%',
                      padding: '0.75rem',
                      border: '1px solid #d1d5db',
                      borderRadius: '6px',
                      fontSize: '1rem',
                      boxSizing: 'border-box'
                    }}
                    required
                  />
                </div>

                <button
                  type="submit"
                  disabled={saving}
                  style={{
                    width: '100%',
                    padding: '0.75rem 1rem',
                    backgroundColor: saving ? '#9ca3af' : '#4f46e5',
                    color: 'white',
                    border: 'none',
                    borderRadius: '6px',
                    fontSize: '1rem',
                    fontWeight: '500',
                    cursor: saving ? 'not-allowed' : 'pointer'
                  }}
                >
                  {saving ? 'Connecting...' : 'Connect Chatwoot'}
                </button>
              </form>
            </div>
          )}

          {currentStep === 2 && (
            <div>
              <h2 style={{
                fontSize: '1.25rem',
                fontWeight: 'bold',
                color: '#1f2937',
                marginBottom: '1rem'
              }}>
                Enable AI-Powered Support
              </h2>
              <p style={{
                color: '#6b7280',
                marginBottom: '1.5rem',
                fontSize: '0.875rem'
              }}>
                Enable AI to automatically respond to customer messages and provide intelligent support.
              </p>

              <div style={{
                backgroundColor: aiEnabled ? '#d1fae5' : '#f3f4f6',
                border: aiEnabled ? '1px solid #10b981' : '1px solid #d1d5db',
                borderRadius: '8px',
                padding: '1.5rem',
                marginBottom: '1.5rem'
              }}>
                <div style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  marginBottom: '1rem'
                }}>
                  <div>
                    <h3 style={{
                      fontSize: '1rem',
                      fontWeight: '600',
                      color: '#1f2937',
                      marginBottom: '0.25rem'
                    }}>
                      AI Support Assistant
                    </h3>
                    <p style={{
                      fontSize: '0.875rem',
                      color: '#6b7280'
                    }}>
                      {aiEnabled ? 'AI is enabled and ready to help' : 'Enable AI to assist with customer support'}
                    </p>
                  </div>
                  <button
                    onClick={handleAiToggle}
                    disabled={saving}
                    style={{
                      padding: '0.5rem',
                      backgroundColor: aiEnabled ? '#10b981' : '#e5e7eb',
                      border: 'none',
                      borderRadius: '50%',
                      cursor: saving ? 'not-allowed' : 'pointer',
                      width: '3rem',
                      height: '3rem',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center'
                    }}
                  >
                    <svg
                      style={{
                        width: '1.25rem',
                        height: '1.25rem',
                        color: aiEnabled ? 'white' : '#6b7280'
                      }}
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                      />
                    </svg>
                  </button>
                </div>

                <button
                  onClick={() => setCurrentStep(3)}
                  style={{
                    width: '100%',
                    padding: '0.75rem 1rem',
                    backgroundColor: '#f3f4f6',
                    color: '#374151',
                    border: '1px solid #d1d5db',
                    borderRadius: '6px',
                    fontSize: '1rem',
                    fontWeight: '500',
                    cursor: 'pointer'
                  }}
                >
                  {aiEnabled ? 'Continue with AI Enabled' : 'Skip AI for Now'}
                </button>
              </div>
            </div>
          )}

          {currentStep === 3 && (
            <div style={{ textAlign: 'center' }}>
              <div style={{
                width: '4rem',
                height: '4rem',
                backgroundColor: '#d1fae5',
                borderRadius: '50%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto 1.5rem'
              }}>
                <svg style={{
                  width: '2rem',
                  height: '2rem',
                  color: '#10b981'
                }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
              </div>

              <h2 style={{
                fontSize: '1.25rem',
                fontWeight: 'bold',
                color: '#1f2937',
                marginBottom: '1rem'
              }}>
                Setup Complete!
              </h2>
              <p style={{
                color: '#6b7280',
                marginBottom: '2rem',
                fontSize: '0.875rem'
              }}>
                Your AI Chat Support workspace is ready. You can now start managing customer conversations with AI assistance.
              </p>

              <button
                onClick={handleFinish}
                style={{
                  width: '100%',
                  padding: '0.75rem 1rem',
                  backgroundColor: '#4f46e5',
                  color: 'white',
                  border: 'none',
                  borderRadius: '6px',
                  fontSize: '1rem',
                  fontWeight: '500',
                  cursor: 'pointer'
                }}
              >
                Go to Dashboard
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default Onboarding;
