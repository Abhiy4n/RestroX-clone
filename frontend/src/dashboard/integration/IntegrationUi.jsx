import { useEffect, useId, useRef, useState } from 'react'
import Icon from '../Icon.jsx'

export function Modal({ open, title, description, onClose, lockDismiss = false, hideClose = false, className = '', children, footer }) {
  const dialogRef = useRef(null)
  const onCloseRef = useRef(onClose)
  const titleId = useId()

  useEffect(() => {
    onCloseRef.current = onClose
  }, [onClose])

  useEffect(() => {
    if (!open) return undefined

    const previousFocus = document.activeElement
    const focusTimer = window.setTimeout(() => {
      const autofocusTarget = dialogRef.current?.querySelector('[data-autofocus]:not(:disabled)')
        || dialogRef.current?.querySelector('input, select, button:not(:disabled)')
      autofocusTarget?.focus()
    }, 40)

    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        if (!lockDismiss) onCloseRef.current()
        return
      }

      if (event.key !== 'Tab') return
      const focusable = [...dialogRef.current.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), a[href]')]
      if (!focusable.length) return
      const first = focusable[0]
      const last = focusable.at(-1)
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }

    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', handleKeyDown)

    return () => {
      window.clearTimeout(focusTimer)
      document.removeEventListener('keydown', handleKeyDown)
      document.body.style.overflow = ''
      window.setTimeout(() => previousFocus?.focus(), 220)
    }
  }, [lockDismiss, open])

  return <div
    className={`integration-overlay${open ? ' open' : ''}`}
    aria-hidden={!open}
    inert={!open}
    onPointerDown={(event) => {
      if (event.target === event.currentTarget && !lockDismiss) onClose()
    }}
  >
    <section ref={dialogRef} className={`integration-modal ${className}`.trim()} role="dialog" aria-modal="true" aria-labelledby={titleId}>
      <header className="integration-modal-head">
        <div><h2 id={titleId}>{title}</h2>{description && <p>{description}</p>}</div>
        {!hideClose && <button className="integration-modal-close" type="button" onClick={onClose} aria-label="Close dialog"><Icon name="x" /></button>}
      </header>
      <div className="integration-modal-body">{children}</div>
      {footer && <footer className="integration-modal-footer">{footer}</footer>}
    </section>
  </div>
}

export function FilterSelect({ value, options, onChange, label }) {
  const [open, setOpen] = useState(false)
  const rootRef = useRef(null)
  const listId = useId()
  const selected = options.find((option) => option.value === value) || options[0]

  useEffect(() => {
    if (!open) return undefined

    function closeOnOutsidePointer(event) {
      if (!rootRef.current?.contains(event.target)) setOpen(false)
    }

    function closeOnEscape(event) {
      if (event.key === 'Escape') {
        setOpen(false)
        rootRef.current?.querySelector('.filter-select-button')?.focus()
      }
    }

    document.addEventListener('pointerdown', closeOnOutsidePointer)
    document.addEventListener('keydown', closeOnEscape)

    return () => {
      document.removeEventListener('pointerdown', closeOnOutsidePointer)
      document.removeEventListener('keydown', closeOnEscape)
    }
  }, [open])

  return <div className="filter-select" ref={rootRef}>
    <button className="filter-select-button" type="button" aria-label={label} aria-expanded={open} aria-controls={listId} onClick={() => setOpen((current) => !current)}>
      <span>{selected.label}</span><Icon name="chevron" />
    </button>
    <div className={`filter-select-menu${open ? ' open' : ''}`} id={listId} role="listbox" aria-label={label} aria-hidden={!open} inert={!open}>
      {options.map((option) => <button type="button" role="option" aria-selected={option.value === value} key={option.value || 'all'} onClick={() => { onChange(option.value); setOpen(false) }}>
        <span>{option.label}</span>{option.value === value && <Icon name="check" />}
      </button>)}
    </div>
  </div>
}

export function CopyButton({ value, label = 'Copy', onError }) {
  const [copied, setCopied] = useState(false)
  const timer = useRef(null)

  useEffect(() => () => window.clearTimeout(timer.current), [])

  async function copyValue() {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      window.clearTimeout(timer.current)
      timer.current = window.setTimeout(() => setCopied(false), 1500)
    } catch {
      onError?.('Clipboard access failed. Copy the value manually.')
    }
  }

  return <button className={`copy-button${copied ? ' copied' : ''}`} type="button" onClick={copyValue} aria-label={copied ? 'Copied' : label} title={copied ? 'Copied' : label}>
    <span className="copy-icon copy-icon-default"><Icon name="copy" /></span>
    <span className="copy-icon copy-icon-success"><Icon name="check" /></span>
  </button>
}

export function StatusBadge({ status, label }) {
  return <span className={`neutral-badge status-${status}`}><span className="status-dot" />{label || titleCase(status)}</span>
}

export function NeutralBadge({ children }) {
  return <span className="neutral-badge">{children}</span>
}

export function Pagination({ meta, onPage, always = false }) {
  if (!meta || (!always && meta.last_page <= 1)) return null

  return <div className="integration-pagination">
    <span>Showing {meta.from || 0}–{meta.to || 0} of {meta.total}</span>
    <div>
      <button type="button" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}><Icon name="arrowLeft" /> Previous</button>
      <span>Page {meta.current_page} of {meta.last_page}</span>
      <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Next <Icon name="arrowRight" /></button>
    </div>
  </div>
}

export function EmptyState({ icon, title, description, action }) {
  return <div className="integration-empty"><Icon name={icon} /><strong>{title}</strong><span>{description}</span>{action}</div>
}

export function LoadingTable({ columns = 6 }) {
  return <div className="integration-loading" aria-label="Loading" aria-busy="true">
    {[0, 1, 2, 3].map((row) => <div className="loading-row" style={{ '--loading-columns': columns }} key={row}>{Array.from({ length: Math.min(columns, 6) }, (_, column) => <span key={column} />)}</div>)}
  </div>
}

export function Notice({ notice }) {
  return <div className={`integration-toast${notice ? ' show' : ''}`} role="status" aria-live="polite">{notice}</div>
}

export function useNotice() {
  const [notice, setNotice] = useState('')
  const timer = useRef(null)

  useEffect(() => () => window.clearTimeout(timer.current), [])

  function showNotice(message) {
    setNotice(message)
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => setNotice(''), 2600)
  }

  return [notice, showNotice]
}

export function formatDate(value, fallback = '—') {
  if (!value) return fallback
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return fallback
  return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(date)
}

export function titleCase(value) {
  return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
