// Get CSRF token from meta tag
function getCsrfToken() {
  const metaTag = document.querySelector('meta[name="csrf-token"]');
  return metaTag ? metaTag.getAttribute('content') : '';
}

// Detect if user is authenticated via web session
function hasWebSession() {
  // Check if we have a Laravel session cookie
  return document.cookie.includes('laravel_session') || 
         document.cookie.includes('XSRF-TOKEN');
}

// Secure API client that uses session-based authentication
export const secureApiClient = {
  async request(url, options = {}) {
    const defaultHeaders = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };

    // Add CSRF token for state-changing requests when using web session
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method?.toUpperCase())) {
      defaultHeaders['X-CSRF-TOKEN'] = getCsrfToken();
    }

    // Use credentials: 'include' to send session cookies
    const response = await fetch(url, {
      ...options,
      credentials: 'include', // Important: includes session cookies
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

// Enhanced error handler for secure API calls
export async function handleApiError(response, defaultMessage = 'Erreur inconnue') {
  let message = defaultMessage;
  
  if (response.status >= 500) {
    message = 'Une erreur technique est survenue. Veuillez réessayer plus tard.';
  } else if (response.status === 401) {
    message = 'Session expirée. Veuillez vous reconnecter.';
    // Optionally redirect to login page
    // window.location.href = '/login';
  } else if (response.status === 403) {
    message = 'Accès refusé. Vérifiez vos permissions.';
  } else if (response.status === 429) {
    try {
      const data = await response.json();
      if (data && data.message) {
        message = data.message;
      } else {
        message = 'Trop de requêtes. Veuillez patienter.';
      }
    } catch {
      message = 'Trop de requêtes. Veuillez patienter.';
    }
  } else {
    try {
      const data = await response.json();
      if (data && data.message) message = data.message;
    } catch {
      // Keep generic message for other errors
    }
  }
  
  throw new Error(message);
}