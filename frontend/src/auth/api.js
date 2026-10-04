function readXsrfToken() {
  const cookie = document.cookie
    .split('; ')
    .find((part) => part.startsWith('XSRF-TOKEN='))

  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : ''
}

export async function getCsrfCookie() {
  const response = await fetch('/sanctum/csrf-cookie', {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) throw new Error('Could not start a secure session. Please retry.')
}

export async function apiRequest(path, options = {}) {
  const response = await fetch(path, {
    credentials: 'include',
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(readXsrfToken() ? { 'X-XSRF-TOKEN': readXsrfToken() } : {}),
      ...options.headers,
    },
  })

  const payload = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    const error = new Error(payload?.message || 'The request could not be completed.')
    error.fields = payload?.errors || {}
    error.status = response.status
    throw error
  }

  return payload
}
