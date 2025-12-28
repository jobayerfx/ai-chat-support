import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';

const Knowledge = () => {
  const { user } = useAuth();
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [searching, setSearching] = useState(false);
  const [error, setError] = useState(null);

  // Upload states
  const [uploadTitle, setUploadTitle] = useState('');
  const [uploadContent, setUploadContent] = useState('');
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [uploadError, setUploadError] = useState(null);
  const [uploadSuccess, setUploadSuccess] = useState(null);

  // Poll for document status
  useEffect(() => {
    fetchDocuments();
    const interval = setInterval(fetchDocuments, 5000); // Poll every 5 seconds
    return () => clearInterval(interval);
  }, []);

  const fetchDocuments = async () => {
    try {
      const response = await fetch('/api/knowledge', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        setDocuments(data.documents || []);
      } else {
        console.error('Failed to fetch documents');
      }
    } catch (error) {
      console.error('Error fetching documents:', error);
    } finally {
      setLoading(false);
    }
  };

  const getDocumentStatus = (doc) => {
    if (doc.knowledge_embeddings_count > 0) {
      return 'Ready';
    }
    // Check if document is very recent (less than 30 seconds old)
    const createdAt = new Date(doc.created_at);
    const now = new Date();
    const diffSeconds = (now - createdAt) / 1000;

    if (diffSeconds < 30) {
      return 'Pending';
    }
    return 'Processing';
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'Ready':
        return 'text-green-600 bg-green-100';
      case 'Processing':
        return 'text-yellow-600 bg-yellow-100';
      case 'Pending':
        return 'text-blue-600 bg-blue-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const isKnowledgeReady = () => {
    return documents.some(doc => doc.knowledge_embeddings_count > 0);
  };

  const handleUpload = async (e) => {
    e.preventDefault();
    if (!uploadTitle.trim() || !uploadContent.trim()) return;

    setUploading(true);
    setUploadProgress(0);
    setUploadError(null);
    setUploadSuccess(null);

    try {
      // Simulate progress for better UX
      const progressInterval = setInterval(() => {
        setUploadProgress(prev => Math.min(prev + 10, 90));
      }, 200);

      const response = await fetch('/api/knowledge', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          title: uploadTitle.trim(),
          content: uploadContent.trim(),
        }),
      });

      clearInterval(progressInterval);
      setUploadProgress(100);

      if (response.ok) {
        const data = await response.json();
        setUploadSuccess('Document uploaded successfully! Processing will begin shortly.');
        setUploadTitle('');
        setUploadContent('');
        // Refresh documents list
        fetchDocuments();
      } else {
        const errorData = await response.json();
        setUploadError(errorData.message || 'Upload failed');
      }
    } catch (error) {
      console.error('Upload error:', error);
      setUploadError('Upload failed. Please try again.');
    } finally {
      setUploading(false);
      setTimeout(() => setUploadProgress(0), 1000);
    }
  };

  const handleDelete = async (documentId) => {
    if (!confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
      return;
    }

    try {
      const response = await fetch(`/api/knowledge/${documentId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        setDocuments(prev => prev.filter(doc => doc.id !== documentId));
      } else {
        console.error('Failed to delete document');
      }
    } catch (error) {
      console.error('Delete error:', error);
    }
  };

  const handleSearch = async (e) => {
    e.preventDefault();
    if (!searchQuery.trim() || !isKnowledgeReady()) return;

    setSearching(true);
    setError(null);

    try {
      const response = await fetch('/api/knowledge/search', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          query: searchQuery,
          limit: 10,
        }),
      });

      if (response.ok) {
        const data = await response.json();
        setSearchResults(data.results || []);
      } else {
        const errorData = await response.json();
        setError(errorData.message || 'Search failed');
      }
    } catch (error) {
      console.error('Search error:', error);
      setError('Search failed. Please try again.');
    } finally {
      setSearching(false);
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
              <h1 className="text-xl font-semibold text-gray-900">Knowledge Base</h1>
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
      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0">
          {/* Status Overview */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6">
              <h2 className="text-lg font-medium text-gray-900 mb-4">Knowledge Base Status</h2>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="flex items-center">
                    <div className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor('Pending')}`}>
                      Pending
                    </div>
                    <span className="ml-2 text-sm text-gray-600">
                      {documents.filter(doc => getDocumentStatus(doc) === 'Pending').length} documents
                    </span>
                  </div>
                </div>
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="flex items-center">
                    <div className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor('Processing')}`}>
                      Processing
                    </div>
                    <span className="ml-2 text-sm text-gray-600">
                      {documents.filter(doc => getDocumentStatus(doc) === 'Processing').length} documents
                    </span>
                  </div>
                </div>
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="flex items-center">
                    <div className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor('Ready')}`}>
                      Ready
                    </div>
                    <span className="ml-2 text-sm text-gray-600">
                      {documents.filter(doc => getDocumentStatus(doc) === 'Ready').length} documents
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Upload Form */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6">
              <h2 className="text-lg font-medium text-gray-900 mb-4">Upload Document</h2>
              <form onSubmit={handleUpload} className="space-y-4">
                <div>
                  <label htmlFor="title" className="block text-sm font-medium text-gray-700 mb-1">
                    Document Title
                  </label>
                  <input
                    type="text"
                    id="title"
                    value={uploadTitle}
                    onChange={(e) => setUploadTitle(e.target.value)}
                    placeholder="Enter document title..."
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    required
                    disabled={uploading}
                  />
                </div>

                <div>
                  <label htmlFor="content" className="block text-sm font-medium text-gray-700 mb-1">
                    Document Content
                  </label>
                  <textarea
                    id="content"
                    value={uploadContent}
                    onChange={(e) => setUploadContent(e.target.value)}
                    placeholder="Paste your document content here..."
                    rows={8}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 resize-vertical"
                    required
                    disabled={uploading}
                  />
                  <p className="mt-1 text-sm text-gray-500">
                    {uploadContent.length} characters
                  </p>
                </div>

                {uploading && (
                  <div className="space-y-2">
                    <div className="flex justify-between text-sm text-gray-600">
                      <span>Uploading...</span>
                      <span>{uploadProgress}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                        style={{ width: `${uploadProgress}%` }}
                      ></div>
                    </div>
                  </div>
                )}

                <button
                  type="submit"
                  disabled={uploading || !uploadTitle.trim() || !uploadContent.trim()}
                  className={`w-full px-4 py-2 rounded-md text-white font-medium ${
                    uploading || !uploadTitle.trim() || !uploadContent.trim()
                      ? 'bg-gray-400 cursor-not-allowed'
                      : 'bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500'
                  }`}
                >
                  {uploading ? 'Uploading...' : 'Upload Document'}
                </button>
              </form>

              {uploadError && (
                <div className="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                  <p className="text-sm text-red-600">{uploadError}</p>
                </div>
              )}

              {uploadSuccess && (
                <div className="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                  <p className="text-sm text-green-600">{uploadSuccess}</p>
                </div>
              )}
            </div>
          </div>

          {/* Search */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div className="p-6">
              <h2 className="text-lg font-medium text-gray-900 mb-4">Search Knowledge Base</h2>
              <form onSubmit={handleSearch} className="space-y-4">
                <div className="flex space-x-4">
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Enter your search query..."
                    className="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    disabled={!isKnowledgeReady()}
                  />
                  <button
                    type="submit"
                    disabled={searching || !isKnowledgeReady()}
                    className={`px-4 py-2 rounded-md text-white font-medium ${
                      isKnowledgeReady()
                        ? 'bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500'
                        : 'bg-gray-400 cursor-not-allowed'
                    }`}
                  >
                    {searching ? 'Searching...' : 'Search'}
                  </button>
                </div>
                {!isKnowledgeReady() && (
                  <p className="text-sm text-gray-600">
                    Search is disabled until at least one document is ready for querying.
                  </p>
                )}
              </form>

              {error && (
                <div className="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                  <p className="text-sm text-red-600">{error}</p>
                </div>
              )}

              {searchResults.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-md font-medium text-gray-900 mb-3">Search Results</h3>
                  <div className="space-y-4">
                    {searchResults.map((result, index) => (
                      <div key={index} className="border border-gray-200 rounded-lg p-4">
                        <div className="flex justify-between items-start">
                          <div className="flex-1">
                            <p className="text-sm text-gray-900 mb-2">{result.content}</p>
                            <p className="text-xs text-gray-500">
                              From: {result.document_title} • Score: {result.score?.toFixed(3)}
                            </p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Documents List */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <h2 className="text-lg font-medium text-gray-900 mb-4">Documents</h2>
              {documents.length === 0 ? (
                <p className="text-gray-600">No documents uploaded yet.</p>
              ) : (
                <div className="space-y-4">
                  {documents.map((doc) => (
                    <div key={doc.id} className="border border-gray-200 rounded-lg p-4">
                      <div className="flex justify-between items-start">
                        <div className="flex-1">
                          <h3 className="text-md font-medium text-gray-900">{doc.title}</h3>
                          <p className="text-sm text-gray-600 mt-1">
                            Created: {new Date(doc.created_at).toLocaleDateString()}
                          </p>
                          <p className="text-sm text-gray-600">
                            Embeddings: {doc.knowledge_embeddings_count}
                          </p>
                        </div>
                        <div className="flex items-center space-x-2">
                          <div className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(getDocumentStatus(doc))}`}>
                            {getDocumentStatus(doc)}
                          </div>
                          <button
                            onClick={() => handleDelete(doc.id)}
                            className="px-3 py-1 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors"
                            title="Delete document"
                          >
                            Delete
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </main>
    </div>
  );
};

export default Knowledge;
