import { useEffect, useState } from 'react'
import { apiRequest, getCsrfCookie } from './api.js'
import PhoneCountryInput from './PhoneCountryInput.jsx'
import './auth.css'

const oauthMessages = {
  link_required: 'That Google email already has an account. Sign in with that account, then connect Google from your account page.',
  linked: 'Google sign-in is now connected to your account.',
  login_required: 'Sign in first, then connect your Google account.',
  email_in_use: 'That Google email belongs to another account. Sign in to that account before linking.',
  account_already_linked: 'That Google account is already connected to another user.',
  provider_already_linked: 'A Google account is already connected to this user.',
  unverified_email: 'Google did not provide a verified email address. Use another sign-in method.',
  failed: 'Google sign-in could not be completed. Please try again.',
}

const emptyForm = {
  name: '',
  email: '',
  phone_e164: '',
  phone_country_iso2: 'NP',
  password: '',
  password_confirmation: '',
}

export default function AuthPage() {
  const [user, setUser] = useState(null)
  const [mode, setMode] = useState('login')
  const [form, setForm] = useState(emptyForm)
  const [fieldErrors, setFieldErrors] = useState({})
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const oauthStatus = params.get('oauth')

    if (oauthStatus) {
      setMessage(oauthMessages[oauthStatus] || 'Google sign-in returned. Refresh your account status if needed.')
      window.history.replaceState({}, '', `${window.location.pathname}${window.location.hash}`)
    }

    apiRequest('/api/user')
      .then((result) => setUser(result.user))
      .catch(() => setUser(null))
      .finally(() => setLoading(false))
  }, [])

  function updateField(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
    setFieldErrors((current) => ({ ...current, [name]: undefined }))
  }

  function updatePhone(phone, countryIso2) {
    setForm((current) => ({
      ...current,
      phone_e164: phone,
      phone_country_iso2: countryIso2,
    }))
    setFieldErrors((current) => ({
      ...current,
      phone_e164: undefined,
      phone_country_iso2: undefined,
    }))
  }

  async function submit(event) {
    event.preventDefault()
    setSubmitting(true)
    setMessage('')
    setFieldErrors({})

    try {
      await getCsrfCookie()
      const hasPhoneNumber = Boolean(form.phone_e164)
      const body = mode === 'register'
        ? {
            ...form,
            phone_e164: hasPhoneNumber ? form.phone_e164 : null,
            phone_country_iso2: hasPhoneNumber ? form.phone_country_iso2 : null,
          }
        : { email: form.email, password: form.password }
      const result = await apiRequest(`/api/${mode === 'register' ? 'register' : 'login'}`, {
        method: 'POST',
        body: JSON.stringify(body),
      })
      setUser(result.user)
    } catch (error) {
      setFieldErrors(error.fields || {})
      setMessage(error.fields ? '' : error.message)
    } finally {
      setSubmitting(false)
    }
  }

  async function logout() {
    setSubmitting(true)

    try {
      await getCsrfCookie()
      await apiRequest('/api/logout', { method: 'POST' })
      setUser(null)
      setForm(emptyForm)
      setMessage('You have been signed out.')
    } catch (error) {
      setMessage(error.message)
    } finally {
      setSubmitting(false)
    }
  }

  function changeMode(nextMode) {
    setMode(nextMode)
    setMessage('')
    setFieldErrors({})
  }

  if (loading) {
    return <main className="auth-shell"><div className="loading-card">Loading your account…</div></main>
  }

  return (
    <main className="auth-shell">
      <section className="auth-card" aria-labelledby="auth-title">
        <div className="brand-mark" aria-hidden="true">R</div>
        <p className="eyebrow">RESTRO ACCOUNT</p>

        {user ? (
          <>
            <h1 id="auth-title">Welcome, {user.name}</h1>
            <p className="subtitle">You’re signed in as {user.email}.</p>
            {message && <p className="notice" role="status">{message}</p>}
            <div className="account-actions">
              <a className="button button-google" href="/auth/google/link">
                <GoogleIcon /> Connect Google account
              </a>
              <button className="button button-secondary" onClick={logout} disabled={submitting}>
                {submitting ? 'Signing out…' : 'Sign out'}
              </button>
            </div>
          </>
        ) : (
          <>
            <h1 id="auth-title">{mode === 'register' ? 'Create your account' : 'Welcome back'}</h1>
            <p className="subtitle">
              {mode === 'register' ? 'Get started with your Restro account.' : 'Sign in to continue to Restro.'}
            </p>

            <a className="button button-google" href="/auth/google/redirect">
              <GoogleIcon /> Continue with Google
            </a>

            <div className="divider"><span>or continue with email</span></div>

            <form className="auth-form" onSubmit={submit} noValidate>
              {mode === 'register' && (
                <Field label="Full name" name="name" value={form.name} onChange={updateField} error={fieldErrors.name?.[0]} autoComplete="name" />
              )}
              <Field label="Email address" name="email" type="email" value={form.email} onChange={updateField} error={fieldErrors.email?.[0]} autoComplete="email" />
              {mode === 'register' && (
                <PhoneCountryInput
                  value={form.phone_e164}
                  onChange={updatePhone}
                  error={fieldErrors.phone_e164?.[0] || fieldErrors.phone_country_iso2?.[0]}
                />
              )}
              <Field label="Password" name="password" type="password" value={form.password} onChange={updateField} error={fieldErrors.password?.[0]} autoComplete={mode === 'register' ? 'new-password' : 'current-password'} />
              {mode === 'register' && (
                <Field label="Confirm password" name="password_confirmation" type="password" value={form.password_confirmation} onChange={updateField} error={fieldErrors.password_confirmation?.[0]} autoComplete="new-password" />
              )}

              {message && <p className="notice" role="alert">{message}</p>}

              <button className="button button-primary" type="submit" disabled={submitting}>
                {submitting ? 'Please wait…' : mode === 'register' ? 'Create account' : 'Sign in'}
              </button>
            </form>

            <p className="switch-mode">
              {mode === 'register' ? 'Already have an account?' : 'New to Restro?'}{' '}
              <button onClick={() => changeMode(mode === 'register' ? 'login' : 'register')}>
                {mode === 'register' ? 'Sign in' : 'Create an account'}
              </button>
            </p>
          </>
        )}

        <p className="security-note">Your account is protected with secure, server-managed sessions.</p>
      </section>
    </main>
  )
}

function Field({ label, name, error, ...props }) {
  return (
    <label className="field" htmlFor={name}>
      <span>{label}</span>
      <input id={name} name={name} aria-invalid={Boolean(error)} aria-describedby={error ? `${name}-error` : undefined} required {...props} />
      {error && <small id={`${name}-error`} className="field-error">{error}</small>}
    </label>
  )
}

function GoogleIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" className="google-icon">
      <path fill="#4285F4" d="M19.6 10.23c0-.68-.06-1.36-.18-2.02H10v3.83h5.38a4.6 4.6 0 0 1-2 3.02v2.48h3.23c1.9-1.75 2.99-4.34 2.99-7.31Z" />
      <path fill="#34A853" d="M10 20c2.7 0 4.97-.9 6.62-2.46l-3.23-2.48c-.9.6-2.05.96-3.39.96-2.6 0-4.8-1.75-5.58-4.1H1.08v2.56A10 10 0 0 0 10 20Z" />
      <path fill="#FBBC05" d="M4.42 11.92a6.02 6.02 0 0 1 0-3.84V5.52H1.08a10 10 0 0 0 0 8.96l3.34-2.56Z" />
      <path fill="#EA4335" d="M10 3.98c1.47 0 2.79.5 3.82 1.5l2.86-2.86C14.96.99 12.7 0 10 0a10 10 0 0 0-8.92 5.52l3.34 2.56C5.2 5.73 7.4 3.98 10 3.98Z" />
    </svg>
  )
}
