import { useEffect, useState } from 'react'
import { apiRequest } from '../../auth/api.js'
import Icon from '../Icon.jsx'
import { MerchantCredentials } from './ApiKeysPage.jsx'
import {
  CopyButton,
  EmptyState,
  FilterSelect,
  LoadingTable,
  Modal,
  NeutralBadge,
  Notice,
  Pagination,
  StatusBadge,
  formatDate,
  titleCase,
  useNotice,
} from './IntegrationUi.jsx'

const emptyForm = { name: '', website_url: '', integration_type: 'website' }

export default function MerchantsPage() {
  const [merchants, setMerchants] = useState([])
  const [meta, setMeta] = useState(null)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [selectedMerchantIds, setSelectedMerchantIds] = useState([])
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)
  const [selectedMerchant, setSelectedMerchant] = useState(null)
  const [detailTab, setDetailTab] = useState('overview')
  const [formMode, setFormMode] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [statusTarget, setStatusTarget] = useState(null)
  const [notice, showNotice] = useNotice()

  useEffect(() => {
    const controller = new AbortController()
    const timer = window.setTimeout(async () => {
      setLoading(true)
      setError('')
      const query = new URLSearchParams({ page: String(page) })
      if (search.trim()) query.set('search', search.trim())
      if (statusFilter) query.set('status', statusFilter)

      try {
        const result = await apiRequest(`/api/merchants?${query}`, { signal: controller.signal })
        setMerchants(result.merchants || [])
        setMeta(result.meta || null)
        setSelectedMerchant((current) => current
          ? result.merchants?.find((merchant) => merchant.merchant_id === current.merchant_id) || current
          : null)
      } catch (requestError) {
        if (requestError.name !== 'AbortError') setError(requestError.message)
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }, 180)

    return () => {
      window.clearTimeout(timer)
      controller.abort()
    }
  }, [page, reload, search, statusFilter])

  function refresh() {
    setReload((value) => value + 1)
  }

  function openCreate() {
    setForm(emptyForm)
    setFieldErrors({})
    setFormMode('create')
  }

  function openEdit(merchant) {
    setForm({ name: merchant.name, website_url: merchant.website_url || '', integration_type: merchant.integration_type })
    setFieldErrors({})
    setFormMode('edit')
  }

  function updateForm(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
    setFieldErrors((current) => ({ ...current, [name]: undefined }))
  }

  async function saveMerchant(event) {
    event.preventDefault()
    const errors = {}
    const name = form.name.trim()
    const websiteUrl = form.website_url.trim()

    if (!name) errors.name = ['Enter a merchant name.']
    if (name.length > 150) errors.name = ['The merchant name must not exceed 150 characters.']
    if (websiteUrl && !safeWebsiteUrl(websiteUrl)) errors.website_url = ['Enter a valid HTTP or HTTPS URL.']
    if (Object.keys(errors).length) {
      setFieldErrors(errors)
      return
    }

    setSaving(true)
    setFieldErrors({})
    try {
      const editing = formMode === 'edit'
      const result = await apiRequest(editing ? `/api/merchants/${selectedMerchant.merchant_id}` : '/api/merchants', {
        method: editing ? 'PATCH' : 'POST',
        body: JSON.stringify({ name, website_url: websiteUrl || null, integration_type: form.integration_type }),
      })
      setFormMode(null)
      if (editing) setSelectedMerchant(result.merchant)
      setPage(1)
      refresh()
      showNotice(editing ? 'Merchant updated.' : 'Merchant created.')
    } catch (requestError) {
      setFieldErrors(requestError.fields || {})
      if (!requestError.fields) showNotice(requestError.message)
    } finally {
      setSaving(false)
    }
  }

  async function updateStatus() {
    if (!statusTarget) return
    const nextStatus = statusTarget.status === 'active' ? 'disabled' : 'active'
    setSaving(true)
    try {
      const result = await apiRequest(`/api/merchants/${statusTarget.merchant_id}`, {
        method: 'PATCH',
        body: JSON.stringify({ status: nextStatus }),
      })
      setSelectedMerchant(result.merchant)
      setStatusTarget(null)
      refresh()
      showNotice(nextStatus === 'active' ? 'Merchant re-enabled.' : 'Merchant disabled.')
    } catch (requestError) {
      showNotice(requestError.message)
    } finally {
      setSaving(false)
    }
  }

  if (selectedMerchant) {
    return <>
      <button className="back-action" type="button" onClick={() => { setSelectedMerchant(null); setDetailTab('overview') }}><Icon name="arrowLeft" /> All merchants</button>
      <div className="integration-page-head detail-page-head">
        <div><h1>{selectedMerchant.name}</h1><p>Merchant ID <code>{selectedMerchant.merchant_id}</code> <CopyButton value={selectedMerchant.merchant_id} label="Copy merchant ID" onError={showNotice} /></p></div>
        <div className="integration-head-actions"><button className="secondary-action" type="button" onClick={() => openEdit(selectedMerchant)}><Icon name="pencil" /> Edit merchant</button><button className={selectedMerchant.status === 'active' ? 'danger-action' : 'primary-action'} type="button" onClick={() => setStatusTarget(selectedMerchant)}>{selectedMerchant.status === 'active' ? 'Disable' : 'Re-enable'}</button></div>
      </div>
      <div className="detail-tabs" role="tablist"><button type="button" role="tab" aria-selected={detailTab === 'overview'} className={detailTab === 'overview' ? 'active' : ''} onClick={() => setDetailTab('overview')}>Overview</button><button type="button" role="tab" aria-selected={detailTab === 'credentials'} className={detailTab === 'credentials' ? 'active' : ''} onClick={() => setDetailTab('credentials')}>API credentials</button></div>
      {detailTab === 'overview'
        ? <MerchantOverview merchant={selectedMerchant} onEdit={() => openEdit(selectedMerchant)} onCredentials={() => setDetailTab('credentials')} onCopyError={showNotice} />
        : <MerchantCredentials merchant={selectedMerchant} />}
      <MerchantFormModal open={Boolean(formMode)} mode={formMode} form={form} fieldErrors={fieldErrors} saving={saving} onChange={updateForm} onSubmit={saveMerchant} onClose={() => !saving && setFormMode(null)} />
      <StatusModal merchant={statusTarget} saving={saving} onClose={() => !saving && setStatusTarget(null)} onConfirm={updateStatus} />
      <Notice notice={notice} />
    </>
  }

  const merchantCount = meta?.total ?? 0
  const visibleMerchantIds = merchants.map((merchant) => merchant.merchant_id)
  const selectedVisibleCount = visibleMerchantIds.filter((merchantId) => selectedMerchantIds.includes(merchantId)).length
  const allVisibleSelected = Boolean(visibleMerchantIds.length) && selectedVisibleCount === visibleMerchantIds.length

  function toggleMerchantSelection(merchantId) {
    setSelectedMerchantIds((current) => current.includes(merchantId)
      ? current.filter((selectedId) => selectedId !== merchantId)
      : [...current, merchantId])
  }

  function toggleVisibleMerchants() {
    setSelectedMerchantIds((current) => allVisibleSelected
      ? current.filter((merchantId) => !visibleMerchantIds.includes(merchantId))
      : [...new Set([...current, ...visibleMerchantIds])])
  }

  return <div className="merchants-page">
    <div className="integration-page-head">
      <div><h1>Merchants</h1><p>Manage applications connected to your sandbox.</p></div>
      <button className="primary-action" type="button" onClick={openCreate}><Icon name="plus" /> Create merchant</button>
    </div>
    <section className="integration-panel" aria-labelledby="connected-merchants-title">
      <div className="merchant-panel-header">
        <div className="merchant-section-head">
          <h2 id="connected-merchants-title">Connected merchants</h2>
          <p>{merchantCount} {merchantCount === 1 ? 'merchant' : 'merchants'} registered</p>
        </div>
        <div className="integration-filters">
          <label className="filter-search"><Icon name="search" /><input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} placeholder="Search merchants…" aria-label="Search merchants" /></label>
          <FilterSelect label="Filter by merchant status" value={statusFilter} options={[{ value: '', label: 'All statuses' }, { value: 'active', label: 'Active' }, { value: 'disabled', label: 'Disabled' }]} onChange={(value) => { setStatusFilter(value); setPage(1) }} />
        </div>
      </div>
      {loading
        ? <LoadingTable columns={7} />
        : error
          ? <EmptyState icon="alert" title="Couldn't load merchants" description={error} action={<button className="secondary-action" type="button" onClick={refresh}>Try again</button>} />
          : merchants.length
            ? <><div className="integration-table-wrap"><table className="integration-table merchants-table"><thead><tr><th className="merchant-select-column"><input className={`merchant-checkbox${selectedVisibleCount && !allVisibleSelected ? ' partially-selected' : ''}`} type="checkbox" checked={allVisibleSelected} onChange={toggleVisibleMerchants} aria-label="Select all merchants on this page" aria-checked={selectedVisibleCount && !allVisibleSelected ? 'mixed' : allVisibleSelected} /></th><th>Merchant</th><th>Merchant ID</th><th>Status</th><th>Integration</th><th>Environment</th><th>Action</th></tr></thead><tbody>
              {merchants.map((merchant) => { const selected = selectedMerchantIds.includes(merchant.merchant_id); return <tr className={selected ? 'is-selected' : ''} key={merchant.merchant_id}><td className="merchant-select-column"><input className="merchant-checkbox" type="checkbox" checked={selected} onChange={() => toggleMerchantSelection(merchant.merchant_id)} aria-label={`Select ${merchant.name}`} /></td><td><div className="merchant-identity"><button className="merchant-name-action" type="button" title={merchant.name} onClick={() => setSelectedMerchant(merchant)}>{merchant.name}</button><WebsiteLink value={merchant.website_url} emptyLabel="No website provided" showLeadingIcon={false} /></div></td><td><span className="id-cell"><code title={merchant.merchant_id}>{merchant.merchant_id}</code><CopyButton value={merchant.merchant_id} label="Copy merchant ID" onError={showNotice} /></span></td><td><StatusBadge status={merchant.status} /></td><td><NeutralBadge>{titleCase(merchant.integration_type === 'server_api' ? 'API' : merchant.integration_type)}</NeutralBadge></td><td><span className="environment-badge">Sandbox</span></td><td><button className="view-detail-action" type="button" onClick={() => setSelectedMerchant(merchant)}>View details <Icon name="arrowUpRight" /></button></td></tr> })}
            </tbody></table></div><Pagination meta={meta} onPage={setPage} always /></>
            : <EmptyState icon="store" title="No merchants found" description="Create your first merchant or adjust your search and status filter." action={<button className="primary-action" type="button" onClick={openCreate}>Create merchant</button>} />}
    </section>
    <MerchantFormModal open={Boolean(formMode)} mode={formMode} form={form} fieldErrors={fieldErrors} saving={saving} onChange={updateForm} onSubmit={saveMerchant} onClose={() => !saving && setFormMode(null)} />
    <Notice notice={notice} />
  </div>
}

function MerchantOverview({ merchant, onEdit, onCredentials, onCopyError }) {
  return <div className="merchant-detail-grid">
    <section className="integration-panel detail-card"><div className="detail-card-head"><h3>Basic information</h3><button className="table-action" type="button" onClick={onEdit}>Edit</button></div><InfoRow label="Merchant name" value={merchant.name} /><InfoRow label="Merchant ID" value={<span className="id-cell"><code>{merchant.merchant_id}</code><CopyButton value={merchant.merchant_id} onError={onCopyError} /></span>} /><InfoRow label="Website / application URL" value={<WebsiteLink value={merchant.website_url} />} /><InfoRow label="Integration type" value={<NeutralBadge>{titleCase(merchant.integration_type === 'server_api' ? 'API' : merchant.integration_type)}</NeutralBadge>} /><InfoRow label="Created" value={formatDate(merchant.created_at)} /><InfoRow label="Last updated" value={formatDate(merchant.updated_at)} /></section>
    <section className="integration-panel detail-card"><h3>Sandbox settings</h3><InfoRow label="Environment" value={<NeutralBadge>Sandbox</NeutralBadge>} /><InfoRow label="Merchant status" value={<StatusBadge status={merchant.status} />} /><p className="detail-help">Disabling this merchant blocks its keys from authenticating while preserving credentials and history.</p><button className="secondary-action detail-credentials-action" type="button" onClick={onCredentials}>Manage API credentials <Icon name="arrowRight" /></button></section>
  </div>
}

function MerchantFormModal({ open, mode, form, fieldErrors, saving, onChange, onSubmit, onClose }) {
  const editing = mode === 'edit'
  return <Modal className="merchant-form-modal" open={open} onClose={onClose} title={editing ? 'Edit merchant' : 'Create merchant'} description={editing ? 'Update this sandbox integration.' : 'Register a new application for testing.'} footer={<><button className="secondary-action" type="button" onClick={onClose} disabled={saving}>Cancel</button><button className="primary-action" type="submit" form="merchant-form" disabled={saving}>{saving ? <><span className="compact-spinner" /> Saving…</> : editing ? 'Save changes' : 'Create merchant'}</button></>}>
    <form id="merchant-form" className="integration-form" onSubmit={onSubmit} noValidate>
      <FormField label="Merchant name" required error={fieldErrors.name?.[0]}><input data-autofocus name="name" value={form.name} onChange={onChange} maxLength="150" placeholder="e.g. Hotel Booking System" /></FormField>
      <div className="form-split"><FormField label="Website / application URL" optional error={fieldErrors.website_url?.[0]}><input name="website_url" type="url" value={form.website_url} onChange={onChange} maxLength="255" placeholder="https://example.com" /></FormField>
        <FormField label="Integration type" error={fieldErrors.integration_type?.[0]}><select name="integration_type" value={form.integration_type} onChange={onChange}><option value="website">Website</option><option value="mobile_app">Mobile Application</option><option value="server_api">API</option></select></FormField></div>
      <p className="quiet-note">This merchant operates in the sandbox environment. All transactions are simulated.</p>
    </form>
  </Modal>
}

function StatusModal({ merchant, saving, onClose, onConfirm }) {
  const disabling = merchant?.status === 'active'
  return <Modal open={Boolean(merchant)} onClose={onClose} title={disabling ? 'Disable merchant?' : 'Re-enable merchant?'} description={merchant?.name} footer={<><button className="secondary-action" type="button" onClick={onClose} disabled={saving}>Cancel</button><button className={disabling ? 'danger-action' : 'primary-action'} type="button" onClick={onConfirm} disabled={saving}>{saving ? <><span className="compact-spinner" /> Updating…</> : disabling ? 'Disable merchant' : 'Re-enable merchant'}</button></>}><p className="confirmation-copy">{disabling ? 'This merchant will no longer be able to authenticate sandbox API requests. Existing keys and history will be preserved.' : 'Active, nonexpired, and nonrevoked credentials will be eligible to authenticate again.'}</p></Modal>
}

function FormField({ label, optional = false, required = false, error, children }) {
  return <label className="integration-field"><span>{label}{required && <b className="required-mark"> *</b>} {optional && <small>(optional)</small>}</span>{children}{error && <small className="field-error">{error}</small>}</label>
}

function InfoRow({ label, value }) {
  return <div className="detail-info-row"><span>{label}</span><strong>{value}</strong></div>
}

function WebsiteLink({ value, emptyLabel = '—', showLeadingIcon = true }) {
  const href = safeWebsiteUrl(value)
  if (!href) return <span className="empty-value">{emptyLabel}</span>
  return <a className="merchant-website" href={href} target="_blank" rel="noopener noreferrer" title={value}>{showLeadingIcon && <Icon name="globe" />}<span>{value}</span><Icon name="arrowUpRight" className="external-arrow" /></a>
}

function safeWebsiteUrl(value) {
  if (!value) return null
  try {
    const url = new URL(value)
    return ['http:', 'https:'].includes(url.protocol) ? url.href : null
  } catch {
    return null
  }
}
