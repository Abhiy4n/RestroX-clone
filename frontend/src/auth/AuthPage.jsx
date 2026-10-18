import { useEffect, useState } from 'react'
import { apiRequest, getCsrfCookie } from './api.js'
import PhoneCountryInput from './PhoneCountryInput.jsx'
import loginIllustration from '../assets/login.svg'
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
  const [remember, setRemember] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [resetToken, setResetToken] = useState('')

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const oauthStatus = params.get('oauth')
    const passwordResetToken = params.get('reset_token')

    if (passwordResetToken) {
      setMode('reset')
      setResetToken(passwordResetToken)
      setForm((current) => ({ ...current, email: params.get('email') || '' }))
      window.history.replaceState({}, '', window.location.pathname)
    }

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
        : mode === 'login'
          ? { email: form.email, password: form.password, remember }
          : mode === 'forgot'
            ? { email: form.email }
            : { token: resetToken, email: form.email, password: form.password, password_confirmation: form.password_confirmation }
      const endpoint = {
        register: '/api/register',
        login: '/api/login',
        forgot: '/api/forgot-password',
        reset: '/api/reset-password',
      }[mode]
      const result = await apiRequest(endpoint, {
        method: 'POST',
        body: JSON.stringify(body),
      })
      if (mode === 'register') {
        setMessage(result.message)
        setForm({ ...emptyForm, email: form.email })
        setMode('login')
      } else if (mode === 'login') {
        setUser(result.user)
      } else {
        setMessage(result.message)
        setForm({ ...emptyForm, email: form.email })
        setMode('login')
        setResetToken('')
      }
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
    setShowPassword(false)
    setMessage('')
    setFieldErrors({})
  }

  if (loading) {
    return <main className="auth-shell"><div className="loading-card">Loading your account…</div></main>
  }

  return (
    <main className="auth-shell">
      <section className={`auth-card${user ? ' auth-card--account' : ''}`} aria-labelledby="auth-title">
        <div className="auth-art-panel" aria-hidden="true">
          <img src={loginIllustration} alt="" className="auth-illustration" />
        </div>

        <div className="auth-content">
          {user ? (
            <div className="account-view">
              <div className="brand-mark" aria-hidden="true">R</div>
              <p className="eyebrow">RESTRO ACCOUNT</p>
              <h1 id="auth-title">Welcome, {user.name}</h1>
              <p className="subtitle">You’re signed in as {user.email}.</p>
              {message && <p className="notice" role="status">{message}</p>}
              <div className="account-actions">
                <a className="button button-google" href="/auth/google/link">
                  <GoogleIcon /> Connect Google account
                </a>
                <button className="button button-primary" onClick={logout} disabled={submitting}>
                  {submitting ? 'Signing out…' : 'Sign out'}
                </button>
              </div>
            </div>
          ) : (
            <>
              <div className="auth-heading">
                <p className="eyebrow">RESTRO ACCOUNT</p>
                <h1 id="auth-title">{mode === 'register' ? 'Create your account' : mode === 'forgot' ? 'Reset your password' : mode === 'reset' ? 'Choose a new password' : 'Welcome back!'}</h1>
                <p className="subtitle">{mode === 'register' ? 'Create an account to get started' : mode === 'forgot' ? 'We’ll email a secure reset link if an account exists' : mode === 'reset' ? 'Enter your new password below' : 'Please enter your details'}</p>
              </div>

              <form className={`auth-form${mode === 'register' ? ' auth-form--register' : ''}`} onSubmit={submit} noValidate>
                {mode === 'register' && (
                  <Field label="Full name" name="name" value={form.name} onChange={updateField} error={fieldErrors.name?.[0]} autoComplete="name" />
                )}
                <Field label="Email" name="email" type="email" value={form.email} onChange={updateField} error={fieldErrors.email?.[0]} autoComplete="email" />
                {mode === 'register' && (
                  <PhoneCountryInput
                    value={form.phone_e164}
                    onChange={updatePhone}
                    error={fieldErrors.phone_e164?.[0] || fieldErrors.phone_country_iso2?.[0]}
                  />
                )}
                {mode !== 'forgot' && <Field
                  label="Password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  value={form.password}
                  onChange={updateField}
                  error={fieldErrors.password?.[0]}
                  autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
                  trailing={mode !== 'login' && (
                    <button
                      className="password-toggle"
                      type="button"
                      aria-label={showPassword ? 'Hide password' : 'Show password'}
                      onClick={() => setShowPassword((visible) => !visible)}
                    >
                      <EyeIcon crossed={!showPassword} />
                    </button>
                  )}
                />}
                {['register', 'reset'].includes(mode) && (
                  <Field label="Confirm password" name="password_confirmation" type="password" value={form.password_confirmation} onChange={updateField} error={fieldErrors.password_confirmation?.[0]} autoComplete="new-password" />
                )}

                {mode === 'login' && (
                  <div className="login-options">
                    <label className="remember-option">
                      <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} />
                      <span>Remember for 30 days</span>
                    </label>
                    <button className="text-action" type="button" onClick={() => { setMode('forgot'); setMessage('') }}>Forgot password?</button>
                  </div>
                )}

                {message && <p className="notice" role="alert">{message}</p>}

                <button className="button button-primary" type="submit" disabled={submitting}>
                  {submitting ? 'Please wait…' : mode === 'register' ? 'Sign Up' : mode === 'forgot' ? 'Send reset link' : mode === 'reset' ? 'Reset password' : 'Log In'}
                </button>
              </form>

              {['login', 'register'].includes(mode) && <>
                <div className="divider"><span>or continue with</span></div>
                <a className="button button-google" href="/auth/google/redirect">
                  <GoogleIcon /> Continue with Google
                </a>
              </>}

              <p className="switch-mode">
                {mode === 'register' ? 'Already have an account?' : mode === 'login' ? "Don't have an account?" : 'Remember your password?'}{' '}
                <button onClick={() => { setMode(mode === 'register' || mode === 'forgot' || mode === 'reset' ? 'login' : 'register'); setMessage('') }}>
                  {mode === 'register' || mode === 'forgot' || mode === 'reset' ? 'Log In' : 'Sign Up'}
                </button>
              </p>
            </>
          )}
        </div>
      </section>
    </main>
  )
}

function Field({ label, name, error, trailing, ...props }) {
  return (
    <div className="field">
      <span>{label}</span>
      <div className="field-input-wrap">
        <input id={name} name={name} aria-invalid={Boolean(error)} aria-describedby={error ? `${name}-error` : undefined} required {...props} />
        {trailing}
      </div>
      {error && <small id={`${name}-error`} className="field-error">{error}</small>}
    </div>
  )
}

function EyeIcon({ crossed }) {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="eye-icon">
      <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" strokeWidth="1.7" />
      <circle cx="12" cy="12" r="2.6" stroke="currentColor" strokeWidth="1.7" />
      {crossed && <path d="m4 4 16 16" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />}
    </svg>
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
