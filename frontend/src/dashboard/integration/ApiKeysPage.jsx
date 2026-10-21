import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../auth/api.js'
import Icon from '../Icon.jsx'
import {
  CopyButton,
  EmptyState,
  FilterSelect,
  LoadingTable,
  Modal,
  Notice,
  Pagination,
  StatusBadge,
  formatDate,
  useNotice,
} from './IntegrationUi.jsx'

const emptySummary = { total: 0, active: 0, inactive: 0 }
const earliestExpirationDate = new Date().toISOString().slice(0, 10)

export default function ApiKeysPage() {
  return <ApiKeyManager />
}

export function MerchantCredentials({ merchant }) {
  return <ApiKeyManager fixedMerchant={merchant} compact />
}

function ApiKeyManager({ fixedMerchant = null, compact = false }) {
  const [apiKeys, setApiKeys] = useState([])
  const [merchants, setMerchants] = useState(fixedMerchant ? [fixedMerchant] : [])
  const [summary, setSummary] = useState(emptySummary)
  const [meta, setMeta] = useState(null)
  const [search, setSearch] = useState('')
  const [merchantFilter, setMerchantFilter] = useState(fixedMerchant?.merchant_id || '')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)
  const [generateOpen, setGenerateOpen] = useState(false)
  const [keyForm, setKeyForm] = useState({ merchant_id: fixedMerchant?.merchant_id || '', name: '', expiration: 'none', custom_expiration: '' })
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [secret, setSecret] = useState(null)
  const [secretSaved, setSecretSaved] = useState(false)
  const [revokeTarget, setRevokeTarget] = useState(null)
  const [notice, showNotice] = useNotice()

  const activeMerchants = useMemo(() => merchants.filter((merchant) => merchant.status === 'active'), [merchants])

  useEffect(() => {
    const controller = new AbortController()
    const timer = window.setTimeout(async () => {
      setLoading(true)
      setError('')
      const query = new URLSearchParams({ page: String(page) })
      if (!compact && search.trim()) query.set('search', search.trim())
      if (!compact && merchantFilter) query.set('merchant_id', merchantFilter)
      if (!compact && statusFilter) query.set('status', statusFilter)
      const endpoint = compact
        ? `/api/merchants/${fixedMerchant.merchant_id}/api-keys?${query}`
        : `/api/api-keys?${query}`

      try {
        const result = await apiRequest(endpoint, { signal: controller.signal })
        setApiKeys(result.api_keys || [])
        setMeta(result.meta || null)
        if (!compact) {
          setSummary(result.summary || emptySummary)
          setMerchants(result.merchants || [])
        }
      } catch (requestError) {
        if (requestError.name !== 'AbortError') setError(requestError.message)
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }, compact ? 0 : 180)

    return () => {
      window.clearTimeout(timer)
      controller.abort()
    }
  }, [compact, fixedMerchant, merchantFilter, page, reload, search, statusFilter])

  function refresh() {
    setReload((value) => value + 1)
  }

  function openGenerate() {
    const defaultMerchant = fixedMerchant?.status === 'active'
      ? fixedMerchant.merchant_id
      : activeMerchants[0]?.merchant_id || ''

    if (!defaultMerchant) {
      showNotice('Enable or create an active merchant before generating a key.')
      return
    }

    setKeyForm({ merchant_id: defaultMerchant, name: '', expiration: 'none', custom_expiration: '' })
    setFieldErrors({})
    setGenerateOpen(true)
  }

  function updateKeyForm(event) {
    const { name, value } = event.target
    setKeyForm((current) => ({ ...current, [name]: value }))
    setFieldErrors((current) => ({ ...current, [name]: undefined, expires_at: undefined }))
  }

  async function generateKey(event) {
    event.preventDefault()
    const errors = {}
    const name = keyForm.name.trim()
    if (!keyForm.merchant_id) errors.merchant_id = ['Select an active merchant.']
    if (!name) errors.name = ['Enter a key name.']
    if (name.length > 100) errors.name = ['The key name must not exceed 100 characters.']

    let expiresAt = null
    if (keyForm.expiration === 'custom') {
      const customDate = new Date(`${keyForm.custom_expiration}T23:59:59`)
      if (!keyForm.custom_expiration || Number.isNaN(customDate.getTime()) || customDate <= new Date()) {
        errors.expires_at = ['Choose a future expiration date.']
      } else {
        expiresAt = customDate.toISOString()
      }
    } else if (keyForm.expiration !== 'none') {
      expiresAt = new Date(Date.now() + Number(keyForm.expiration) * 86400000).toISOString()
    }

    if (Object.keys(errors).length) {
      setFieldErrors(errors)
      return
    }

    setSaving(true)
    setFieldErrors({})
    try {
      const result = await apiRequest('/api/api-keys', {
        method: 'POST',
        body: JSON.stringify({ merchant_id: keyForm.merchant_id, name, expires_at: expiresAt }),
      })
      const merchant = merchants.find((item) => item.merchant_id === keyForm.merchant_id) || fixedMerchant
      setGenerateOpen(false)
      setSecretSaved(false)
      setSecret({ value: result.secret, apiKey: result.api_key, merchantName: merchant?.name || result.api_key.merchant_name })
      refresh()
    } catch (requestError) {
      setFieldErrors(requestError.fields || {})
      if (!requestError.fields) showNotice(requestError.message)
    } finally {
      setSaving(false)
    }
  }

  async function revokeKey() {
    if (!revokeTarget) return
    setSaving(true)
    try {
      await apiRequest(`/api/api-keys/${revokeTarget.id}/revoke`, { method: 'POST' })
      setRevokeTarget(null)
      refresh()
      showNotice('API key revoked.')
    } catch (requestError) {
      showNotice(requestError.message)
    } finally {
      setSaving(false)
    }
  }

  const table = <ApiKeyTable
    apiKeys={apiKeys}
    loading={loading}
    error={error}
    meta={meta}
    onPage={setPage}
    onRetry={refresh}
    onGenerate={openGenerate}
    onRevoke={setRevokeTarget}
    alwaysPaginate={!compact}
  />

  return <>
    {!compact && <div className="api-keys-page">
      <div className="integration-page-head">
        <div><h1>API Keys</h1><p>Manage credentials used to connect applications to Restro Gateway.</p></div>
        <button className="primary-action" type="button" onClick={openGenerate}><Icon name="plus" /> Generate key</button>
      </div>
      <div className="integration-stats">
        <SummaryCard icon="key" label="Total keys" value={summary.total} description="Across all merchants" />
        <SummaryCard icon="active" label="Active keys" value={summary.active} description="Available for authentication" />
        <SummaryCard icon="disabled" label="Revoked / expired" value={summary.inactive} description="No longer valid" />
      </div>
      <section className="integration-panel" aria-labelledby="api-credentials-title">
        <div className="api-key-panel-header">
          <div className="api-key-section-head"><h2 id="api-credentials-title">API credentials</h2><p>{meta?.total ?? 0} {(meta?.total ?? 0) === 1 ? 'key' : 'keys'} issued</p></div>
          <div className="integration-filters">
            <label className="filter-search"><Icon name="search" /><input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} placeholder="Search API keys…" aria-label="Search API keys" /></label>
            <FilterSelect label="Filter by merchant" value={merchantFilter} options={[{ value: '', label: 'All merchants' }, ...merchants.map((merchant) => ({ value: merchant.merchant_id, label: merchant.name }))]} onChange={(value) => { setMerchantFilter(value); setPage(1) }} />
            <FilterSelect label="Filter by key status" value={statusFilter} options={[{ value: '', label: 'All statuses' }, { value: 'active', label: 'Active' }, { value: 'expired', label: 'Expired' }, { value: 'revoked', label: 'Revoked' }]} onChange={(value) => { setStatusFilter(value); setPage(1) }} />
          </div>
        </div>
        {table}
      </section>
    </div>}

    {compact && <section className="credentials-section">
      <div className="credentials-heading"><div><h3>API credentials</h3><p>Keys issued only for {fixedMerchant.name}.</p></div><button className="primary-action" type="button" onClick={openGenerate} disabled={fixedMerchant.status !== 'active'}><Icon name="plus" /> Generate key</button></div>
      <div className="integration-panel">{table}</div>
    </section>}

    <Modal
      open={generateOpen}
      onClose={() => !saving && setGenerateOpen(false)}
      title="Generate API key"
      description="Create a secret sandbox credential for one merchant."
      footer={<><button className="secondary-action" type="button" onClick={() => setGenerateOpen(false)} disabled={saving}>Cancel</button><button className="primary-action" type="submit" form="generate-key-form" disabled={saving}>{saving ? <><span className="compact-spinner" /> Generating…</> : 'Generate key'}</button></>}
    >
      <form id="generate-key-form" className="integration-form" onSubmit={generateKey} noValidate>
        <FormField label="Merchant" error={fieldErrors.merchant_id?.[0]}>
          <select data-autofocus={!fixedMerchant || undefined} name="merchant_id" value={keyForm.merchant_id} onChange={updateKeyForm} disabled={Boolean(fixedMerchant)}>
            {activeMerchants.map((merchant) => <option value={merchant.merchant_id} key={merchant.merchant_id}>{merchant.name}</option>)}
          </select>
        </FormField>
        <FormField label="Key name" error={fieldErrors.name?.[0]} hint="Use a name that identifies where this key will be used.">
          <input data-autofocus={Boolean(fixedMerchant) || undefined} name="name" value={keyForm.name} onChange={updateKeyForm} maxLength="100" placeholder="e.g. HMS Development" />
        </FormField>
        <div className="form-split">
          <FormField label="Expiration" error={fieldErrors.expires_at?.[0]}>
            <select name="expiration" value={keyForm.expiration} onChange={updateKeyForm}>
              <option value="none">No expiration</option><option value="7">7 days</option><option value="30">30 days</option><option value="90">90 days</option><option value="custom">Custom date</option>
            </select>
          </FormField>
          {keyForm.expiration === 'custom' && <FormField label="Custom date" error={fieldErrors.expires_at?.[0]}><input type="date" name="custom_expiration" min={earliestExpirationDate} value={keyForm.custom_expiration} onChange={updateKeyForm} /></FormField>}
        </div>
        <p className="quiet-note">Your secret is shown only once. Copy it before closing the next dialog.</p>
      </form>
    </Modal>

    <Modal
      open={Boolean(secret)}
      onClose={() => {}}
      lockDismiss
      hideClose
      title="API key created"
      description="Your new secret key is ready."
      footer={<button className="primary-action" type="button" disabled={!secretSaved} onClick={() => { setSecret(null); setSecretSaved(false); showNotice('API key created.') }}>I've saved the key</button>}
    >
      <div className="secret-context"><span>Merchant</span><strong>{secret?.merchantName}</strong><span>Key name</span><strong>{secret?.apiKey.name}</strong></div>
      <div className="secret-warning">This secret is shown only once. Copy and store it securely before closing.</div>
      <div className="secret-value"><code>{secret?.value}</code>{secret && <CopyButton value={secret.value} label="Copy secret key" onError={showNotice} />}</div>
      <label className="secret-acknowledgment"><input type="checkbox" checked={secretSaved} onChange={(event) => setSecretSaved(event.target.checked)} /> <span>I have securely saved this key.</span></label>
    </Modal>

    <Modal
      open={Boolean(revokeTarget)}
      onClose={() => !saving && setRevokeTarget(null)}
      title="Revoke API key?"
      description={revokeTarget ? `${revokeTarget.name} · ${revokeTarget.merchant_name}` : ''}
      footer={<><button className="secondary-action" type="button" onClick={() => setRevokeTarget(null)} disabled={saving}>Cancel</button><button className="danger-action" type="button" onClick={revokeKey} disabled={saving}>{saving ? <><span className="compact-spinner" /> Revoking…</> : 'Revoke key'}</button></>}
    >
      <p className="confirmation-copy">This credential will immediately stop authenticating API requests. Revocation cannot be undone; create a replacement key if access is needed again.</p>
    </Modal>

    <Notice notice={notice} />
  </>
}

function ApiKeyTable({ apiKeys, loading, error, meta, onPage, onRetry, onGenerate, onRevoke, alwaysPaginate }) {
  if (loading) return <LoadingTable columns={9} />
  if (error) return <EmptyState icon="alert" title="Couldn't load API keys" description={error} action={<button className="secondary-action" type="button" onClick={onRetry}>Try again</button>} />
  if (!apiKeys.length) return <EmptyState icon="key" title="No API keys found" description="Generate a sandbox API key or adjust your filters." action={<button className="primary-action" type="button" onClick={onGenerate}>Generate key</button>} />

  return <>
    <div className="integration-table-wrap"><table className="integration-table keys-table"><thead><tr><th>SN</th><th>Key name</th><th>Merchant</th><th>Safe key hint</th><th>Status</th><th>Created</th><th>Expiration</th><th>Last used</th><th>Actions</th></tr></thead>
      <tbody>{apiKeys.map((apiKey, index) => <tr key={apiKey.id}>
        <td>{(meta?.from || 1) + index}</td><td><strong>{apiKey.name}</strong></td><td>{apiKey.merchant_name}</td><td><code>{apiKey.key_hint}</code></td><td><StatusBadge status={apiKey.status} /></td><td>{formatDate(apiKey.created_at)}</td><td>{formatDate(apiKey.expires_at)}</td><td>{formatDate(apiKey.last_used_at, 'Never')}</td><td>{apiKey.status !== 'revoked' ? <button className="table-action danger-text" type="button" onClick={() => onRevoke(apiKey)}>Revoke</button> : <span>—</span>}</td>
      </tr>)}</tbody>
    </table></div>
    <Pagination meta={meta} onPage={onPage} always={alwaysPaginate} />
  </>
}

function SummaryCard({ icon, label, value, description }) {
  return <article className="integration-stat"><span className="integration-stat-icon"><Icon name={icon} /></span><span>{label}</span><strong>{value}</strong><small>{description}</small></article>
}

function FormField({ label, error, hint, children }) {
  return <label className="integration-field"><span>{label}</span>{children}{hint && <small>{hint}</small>}{error && <small className="field-error">{error}</small>}</label>
}
