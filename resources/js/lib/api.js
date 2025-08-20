// Centralized API error handler for user-friendly frontend error messages
export async function handleApiError(response, defaultMessage = 'Erreur inconnue') {
  let message = defaultMessage;
  if (response.status >= 500) {
    message = 'Une erreur technique est survenue. Veuillez réessayer plus tard.';
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
