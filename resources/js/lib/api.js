// API Token for test user
const API_TOKEN = '2|m3MIjuDNArKMB0Jsi2J9VUd4Qq7vRUF0Qo9mYFDS05701683';

// Get CSRF token from meta tag
function getCsrfToken() {
  const metaTag = document.querySelector('meta[name="csrf-token"]');
  return metaTag ? metaTag.getAttribute('content') : '';
}

// Centralized API client
export const apiClient = {
  async request(url, options = {}) {
    const defaultHeaders = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Authorization': `Bearer ${API_TOKEN}`,
    };

    // Add CSRF token for state-changing requests
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method?.toUpperCase())) {
      defaultHeaders['X-CSRF-TOKEN'] = getCsrfToken();
    }

    const response = await fetch(url, {
      ...options,
      headers: {
        ...defaultHeaders,
        ...options.headers,
      },
    });

    if (!response.ok) {
      await handleApiError(response);
    }

    return response.json();
  },

  get(url, options = {}) {
    return this.request(url, { ...options, method: 'GET' });
  },

  post(url, data, options = {}) {
    return this.request(url, {
      ...options,
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  put(url, data, options = {}) {
    return this.request(url, {
      ...options,
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  delete(url, options = {}) {
    return this.request(url, { ...options, method: 'DELETE' });
  },
};

// Centralized API error handler for user-friendly frontend error messages
export async function handleApiError(response, defaultMessage = 'Erreur inconnue') {
  let message = defaultMessage;
  if (response.status >= 500) {
    message = 'Une erreur technique est survenue. Veuillez réessayer plus tard.';
  } else if (response.status === 401) {
    message = 'Session expirée. Veuillez vous reconnecter.';
  } else {
    try {
      const data = await response.json();
      if (data && data.message) message = data.message;
    } catch {
      // Ignore backend text for non-500 errors, keep generic message
    }
  }
  throw new Error(message);
}
