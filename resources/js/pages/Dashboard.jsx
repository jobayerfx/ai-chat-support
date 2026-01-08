import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const Dashboard = () => {
  const { user, tenant, logout } = useAuth();
  const [onboardingStatus, setOnboardingStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  // Check onboarding status on component mount
  useEffect(() => {
    checkOnboardingStatus();
  }, []);

  const checkOnboardingStatus = async () => {
    try {
      const response = await fetch('/api/onboarding/status');
      const data = await response.json();

      if (response.ok) {
        setOnboardingStatus(data);

        // Redirect to onboarding if not completed
        if (!data.completed) {
          navigate('/onboarding');
          return;
        }
      } else {
        console.error('Failed to check onboarding status:', data);
      }
    } catch (error) {
      console.error('Error checking onboarding status:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = async () => {
    await logout();
  };

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

  // If onboarding not completed, this component won't render (redirected above)
  return (
    <div style={{
      minHeight: '100vh',
      backgroundColor: '#f8fafc',
      padding: '2rem 1rem'
    }}>
      <div style={{
        maxWidth: '800px',
        margin: '0 auto'
      }}>
        {/* Header */}
        <div style={{
          backgroundColor: 'white',
          borderRadius: '8px',
          padding: '2rem',
          marginBottom: '2rem',
          boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)'
        }}>
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: '1rem'
          }}>
            <div>
              <h1 style={{
                fontSize: '2rem',
                fontWeight: 'bold',
                color: '#1f2937',
                marginBottom: '0.5rem'
              }}>
                Welcome to your workspace
              </h1>
              <p style={{
                color: '#6b7280',
                fontSize: '1rem'
              }}>
                Your AI Chat Support platform is ready to use
              </p>
            </div>

            <button
              onClick={handleLogout}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#dc2626',
                color: 'white',
                border: 'none',
                borderRadius: '6px',
                fontSize: '0.875rem',
                fontWeight: '500',
                cursor: 'pointer'
              }}
            >
              Logout
            </button>
          </div>

          {/* User and Tenant Info */}
          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
            gap: '1rem',
            marginTop: '1.5rem'
          }}>
            {user && (
              <div style={{
                backgroundColor: '#f3f4f6',
                borderRadius: '6px',
                padding: '1rem'
              }}>
                <h3 style={{
                  fontSize: '0.875rem',
                  fontWeight: '600',
                  color: '#374151',
                  marginBottom: '0.5rem'
                }}>
                  Account Information
                </h3>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>
                  <p style={{ margin: '0.25rem 0' }}><strong>Name:</strong> {user.name}</p>
                  <p style={{ margin: '0.25rem 0' }}><strong>Email:</strong> {user.email}</p>
                </div>
              </div>
            )}

            {tenant && (
              <div style={{
                backgroundColor: '#f3f4f6',
                borderRadius: '6px',
                padding: '1rem'
              }}>
                <h3 style={{
                  fontSize: '0.875rem',
                  fontWeight: '600',
                  color: '#374151',
                  marginBottom: '0.5rem'
                }}>
                  Workspace Information
                </h3>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>
                  <p style={{ margin: '0.25rem 0' }}><strong>Name:</strong> {tenant.name}</p>
                  <p style={{ margin: '0.25rem 0' }}><strong>Domain:</strong> {tenant.domain}</p>
                  <p style={{ margin: '0.25rem 0' }}>
                    <strong>AI Status:</strong>
                    <span style={{
                      color: tenant.ai_enabled ? '#10b981' : '#6b7280',
                      marginLeft: '0.5rem'
                    }}>
                      {tenant.ai_enabled ? 'Enabled' : 'Disabled'}
                    </span>
                  </p>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Onboarding Status */}
        {onboardingStatus && (
          <div style={{
            backgroundColor: 'white',
            borderRadius: '8px',
            padding: '2rem',
            boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)'
          }}>
            <h2 style={{
              fontSize: '1.25rem',
              fontWeight: 'bold',
              color: '#1f2937',
              marginBottom: '1rem'
            }}>
              Setup Status
            </h2>

            <div style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
              gap: '1rem'
            }}>
              <div style={{
                display: 'flex',
                alignItems: 'center',
                padding: '1rem',
                backgroundColor: onboardingStatus.chatwoot_connected ? '#d1fae5' : '#fef3c7',
                borderRadius: '6px',
                border: `1px solid ${onboardingStatus.chatwoot_connected ? '#10b981' : '#f59e0b'}`
              }}>
                <div style={{
                  width: '2rem',
                  height: '2rem',
                  borderRadius: '50%',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  backgroundColor: onboardingStatus.chatwoot_connected ? '#10b981' : '#f59e0b',
                  color: 'white',
                  marginRight: '0.75rem',
                  fontSize: '0.875rem',
                  fontWeight: 'bold'
                }}>
                  {onboardingStatus.chatwoot_connected ? '✓' : '!'}
                </div>
                <div>
                  <p style={{
                    fontSize: '0.875rem',
                    fontWeight: '600',
                    color: '#1f2937',
                    margin: '0 0 0.25rem 0'
                  }}>
                    Chatwoot Connection
                  </p>
                  <p style={{
                    fontSize: '0.75rem',
                    color: '#6b7280',
                    margin: 0
                  }}>
                    {onboardingStatus.chatwoot_connected ? 'Connected' : 'Not connected'}
                  </p>
                </div>
              </div>

              <div style={{
                display: 'flex',
                alignItems: 'center',
                padding: '1rem',
                backgroundColor: onboardingStatus.ai_enabled ? '#d1fae5' : '#f3f4f6',
                borderRadius: '6px',
                border: `1px solid ${onboardingStatus.ai_enabled ? '#10b981' : '#d1d5db'}`
              }}>
                <div style={{
                  width: '2rem',
                  height: '2rem',
                  borderRadius: '50%',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  backgroundColor: onboardingStatus.ai_enabled ? '#10b981' : '#9ca3af',
                  color: 'white',
                  marginRight: '0.75rem',
                  fontSize: '0.875rem',
                  fontWeight: 'bold'
                }}>
                  {onboardingStatus.ai_enabled ? '✓' : '○'}
                </div>
                <div>
                  <p style={{
                    fontSize: '0.875rem',
                    fontWeight: '600',
                    color: '#1f2937',
                    margin: '0 0 0.25rem 0'
                  }}>
                    AI Features
                  </p>
                  <p style={{
                    fontSize: '0.75rem',
                    color: '#6b7280',
                    margin: 0
                  }}>
                    {onboardingStatus.ai_enabled ? 'Enabled' : 'Disabled'}
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Quick Actions */}
        <div style={{
          backgroundColor: 'white',
          borderRadius: '8px',
          padding: '2rem',
          marginTop: '2rem',
          boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)'
        }}>
          <h2 style={{
            fontSize: '1.25rem',
            fontWeight: 'bold',
            color: '#1f2937',
            marginBottom: '1rem'
          }}>
            Quick Actions
          </h2>

          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
            gap: '1rem'
          }}>
            <button style={{
              padding: '1rem',
              backgroundColor: '#4f46e5',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              fontSize: '0.875rem',
              fontWeight: '500',
              cursor: 'pointer',
              textAlign: 'left'
            }}>
              <div style={{
                fontSize: '1rem',
                fontWeight: '600',
                marginBottom: '0.25rem'
              }}>
                📊 View Analytics
              </div>
              <div style={{ opacity: 0.9 }}>
                Monitor conversation metrics
              </div>
            </button>

            <button style={{
              padding: '1rem',
              backgroundColor: '#059669',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              fontSize: '0.875rem',
              fontWeight: '500',
              cursor: 'pointer',
              textAlign: 'left'
            }}>
              <div style={{
                fontSize: '1rem',
                fontWeight: '600',
                marginBottom: '0.25rem'
              }}>
                ⚙️ Configure AI
              </div>
              <div style={{ opacity: 0.9 }}>
                Adjust AI settings and preferences
              </div>
            </button>

            <button style={{
              padding: '1rem',
              backgroundColor: '#7c3aed',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              fontSize: '0.875rem',
              fontWeight: '500',
              cursor: 'pointer',
              textAlign: 'left'
            }}>
              <div style={{
                fontSize: '1rem',
                fontWeight: '600',
                marginBottom: '0.25rem'
              }}>
                📚 Manage Knowledge
              </div>
              <div style={{ opacity: 0.9 }}>
                Upload and organize documents
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
