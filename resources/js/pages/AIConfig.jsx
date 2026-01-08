import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';

const AIConfig = () => {
  const { user } = useAuth();
  const [config, setConfig] = useState({
    ai_enabled: false,
    confidence_threshold: 0.7,
    human_override_enabled: true,
    auto_escalate_threshold: 0.4,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState(null);
  const [errors, setErrors] = useState({});

  // Fetch current configuration on load
  useEffect(() => {
    fetchConfig();
  }, []);

  const fetchConfig = async () => {
    try {
      const response = await fetch('/api/ai-config', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        setConfig(data.config);
      } else {
        console.error('Failed to fetch AI config');
      }
    } catch (error) {
      console.error('Error fetching AI config:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setMessage(null);

    try {
      const response = await fetch('/api/ai-config', {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(config),
      });

      const data = await response.json();

      if (response.ok) {
        setMessage({ type: 'success', text: 'AI configuration updated successfully!' });
      } else {
        if (data.errors) {
          setErrors(data.errors);
        } else {
          setMessage({ type: 'error', text: data.message || 'Failed to update configuration' });
        }
      }
    } catch (error) {
      console.error('Error updating AI config:', error);
      setMessage({ type: 'error', text: 'Network error. Please try again.' });
    } finally {
      setSaving(false);
    }
  };

  const handleReset = async () => {
    if (!confirm('Are you sure you want to reset AI configuration to defaults?')) {
      return;
    }

    setSaving(true);
    setErrors({});
    setMessage(null);

    try {
      const response = await fetch('/api/ai-config/reset', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Accept': 'application/json',
        },
      });

      const data = await response.json();

      if (response.ok) {
        setConfig(data.config);
        setMessage({ type: 'success', text: 'AI configuration reset to defaults!' });
      } else {
        setMessage({ type: 'error', text: data.message || 'Failed to reset configuration' });
      }
    } catch (error) {
      console.error('Error resetting AI config:', error);
      setMessage({ type: 'error', text: 'Network error. Please try again.' });
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Navigation */}
      <nav className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <h1 className="text-xl font-semibold text-gray-900">AI Configuration</h1>
            </div>
            <div className="flex items-center space-x-4">
              <span className="text-sm text-gray-700">
                Welcome, {user?.name}
              </span>
            </div>
          </div>
        </div>
      </nav>

      {/* Main Content */}
      <main className="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0">
          {/* Status Message */}
          {message && (
            <div className={`mb-6 p-4 rounded-md ${
              message.type === 'success'
                ? 'bg-green-50 border border-green-200 text-green-700'
                : 'bg-red-50 border border-red-200 text-red-700'
            }`}>
              {message.text}
            </div>
          )}

          {/* Configuration Form */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <div className="mb-6">
                <h2 className="text-lg font-medium text-gray-900 mb-2">AI Settings</h2>
                <p className="text-sm text-gray-600">
                  Configure how the AI assistant behaves in your customer support conversations.
                </p>
              </div>

              <form onSubmit={handleSubmit} className="space-y-6">
                {/* AI Enabled Toggle */}
                <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                  <div>
                    <h3 className="text-sm font-medium text-gray-900">Enable AI Assistant</h3>
                    <p className="text-sm text-gray-600">
                      Allow AI to automatically respond to customer messages
                    </p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={config.ai_enabled}
                      onChange={(e) => setConfig(prev => ({ ...prev, ai_enabled: e.target.checked }))}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                  </label>
                </div>

                {/* Confidence Threshold */}
                <div>
                  <label htmlFor="confidence_threshold" className="block text-sm font-medium text-gray-700 mb-2">
                    Confidence Threshold
                  </label>
                  <div className="flex items-center space-x-4">
                    <input
                      type="range"
                      id="confidence_threshold"
                      min="0"
                      max="1"
                      step="0.1"
                      value={config.confidence_threshold}
                      onChange={(e) => setConfig(prev => ({ ...prev, confidence_threshold: parseFloat(e.target.value) }))}
                      className="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                      disabled={!config.ai_enabled}
                    />
                    <span className="text-sm font-medium text-gray-900 min-w-[3rem]">
                      {(config.confidence_threshold * 100).toFixed(0)}%
                    </span>
                  </div>
                  <p className="mt-1 text-sm text-gray-600">
                    Minimum confidence level required for AI to provide an answer (0-100%)
                  </p>
                  {errors.confidence_threshold && (
                    <p className="mt-1 text-sm text-red-600">{errors.confidence_threshold[0]}</p>
                  )}
                </div>

                {/* Human Override */}
                <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                  <div>
                    <h3 className="text-sm font-medium text-gray-900">Human Override</h3>
                    <p className="text-sm text-gray-600">
                      Allow human agents to override AI responses
                    </p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={config.human_override_enabled}
                      onChange={(e) => setConfig(prev => ({ ...prev, human_override_enabled: e.target.checked }))}
                      className="sr-only peer"
                      disabled={!config.ai_enabled}
                    />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                  </label>
                </div>

                {/* Auto Escalate Threshold */}
                <div>
                  <label htmlFor="auto_escalate_threshold" className="block text-sm font-medium text-gray-700 mb-2">
                    Auto-Escalate Threshold
                  </label>
                  <div className="flex items-center space-x-4">
                    <input
                      type="range"
                      id="auto_escalate_threshold"
                      min="0"
                      max="1"
                      step="0.1"
                      value={config.auto_escalate_threshold}
                      onChange={(e) => setConfig(prev => ({ ...prev, auto_escalate_threshold: parseFloat(e.target.value) }))}
                      className="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                      disabled={!config.ai_enabled}
                    />
                    <span className="text-sm font-medium text-gray-900 min-w-[3rem]">
                      {(config.auto_escalate_threshold * 100).toFixed(0)}%
                    </span>
                  </div>
                  <p className="mt-1 text-sm text-gray-600">
                    Confidence level below which conversations are automatically escalated to human agents
                  </p>
                  {errors.auto_escalate_threshold && (
                    <p className="mt-1 text-sm text-red-600">{errors.auto_escalate_threshold[0]}</p>
                  )}
                </div>

                {/* Action Buttons */}
                <div className="flex justify-between pt-6 border-t border-gray-200">
                  <button
                    type="button"
                    onClick={handleReset}
                    disabled={saving}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                  >
                    Reset to Defaults
                  </button>

                  <button
                    type="submit"
                    disabled={saving}
                    className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                  >
                    {saving ? 'Saving...' : 'Save Configuration'}
                  </button>
                </div>
              </form>
            </div>
          </div>

          {/* Help Section */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Understanding AI Configuration</h3>
              <div className="space-y-4 text-sm text-gray-600">
                <div>
                  <h4 className="font-medium text-gray-900">Confidence Threshold</h4>
                  <p>How confident the AI must be in its answer before providing a response. Higher values mean more accurate but fewer responses.</p>
                </div>
                <div>
                  <h4 className="font-medium text-gray-900">Auto-Escalate Threshold</h4>
                  <p>When AI confidence falls below this level, the conversation is automatically flagged for human review. Must be lower than confidence threshold.</p>
                </div>
                <div>
                  <h4 className="font-medium text-gray-900">Human Override</h4>
                  <p>Allows support agents to review and modify AI responses before they are sent to customers.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
};

export default AIConfig;