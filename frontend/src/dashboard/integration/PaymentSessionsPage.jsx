import { useEffect, useState } from 'react'
import { apiRequest } from '../../auth/api.js'
import Icon from '../Icon.jsx'
import {
  CopyButton,
  EmptyState,
  FilterSelect,
  LoadingTable,
  Modal,
  Pagination,
  StatusBadge,
  formatDate,
} from './IntegrationUi.jsx'

const emptySummary = { total: 0, succeeded: 0, volume_minor: 0 }
const statuses = ['pending', 'succeeded', 'failed', 'cancelled', 'expired']

export default function PaymentSessionsPage() {
  const [payments, setPayments] = useState([])
  const [merchants, setMerchants] = useState([])
  const [summary, setSummary] = useState(emptySummary)
  const [meta, setMeta] = useState(null)
  const [search, setSearch] = useState('')
  const [merchantFilter, setMerchantFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)
  const [selectedPayment, setSelectedPayment] = useState(null)

  useEffect(() => {
    const controller = new AbortController()
    const timer = window.setTimeout(async () => {
      setLoading(true)
      setError('')
      const query = new URLSearchParams({ page: String(page) })
      if (search.trim()) query.set('search', search.trim())
      if (merchantFilter) query.set('merchant_id', merchantFilter)
      if (statusFilter) query.set('status', statusFilter)

      try {
        const result = await apiRequest(`/api/payments?${query}`, { signal: controller.signal })
        setPayments(result.payments || [])
        setMerchants(result.merchants || [])
        setSummary(result.summary || emptySummary)
        setMeta(result.meta || null)
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
  }, [merchantFilter, page, reload, search, statusFilter])

  const successRate = summary.total ? `${Math.round((summary.succeeded / summary.total) * 100)}%` : '0%'

  return <div className="payments-page">
    <div className="integration-page-head">
      <div><h1>Payment Sessions</h1><p>Track sandbox checkout sessions created by your projects.</p></div>
      <button className="secondary-action" type="button" onClick={() => setReload((value) => value + 1)} disabled={loading}><Icon name="refresh" /> Refresh</button>
    </div>

    <div className="integration-stats">
      <SummaryCard icon="payment-sessions" label="Total payments" value={summary.total} description="Across all projects" />
      <SummaryCard icon="active" label="Success rate" value={successRate} description={`${summary.succeeded} succeeded`} />
      <SummaryCard icon="transactions" label="Successful volume" value={formatMoney(summary.volume_minor)} description="Completed sandbox payments" />
    </div>

    <section className="integration-panel" aria-labelledby="payment-sessions-title">
      <div className="payment-panel-header">
        <div className="payment-section-head"><h2 id="payment-sessions-title">Payment sessions</h2><p>{meta?.total ?? 0} {(meta?.total ?? 0) === 1 ? 'payment' : 'payments'} found</p></div>
        <div className="integration-filters">
          <label className="filter-search"><Icon name="search" /><input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} placeholder="Search payments…" aria-label="Search payments" /></label>
          <FilterSelect label="Filter by project" value={merchantFilter} options={[{ value: '', label: 'All projects' }, ...merchants.map((merchant) => ({ value: merchant.merchant_id, label: merchant.name }))]} onChange={(value) => { setMerchantFilter(value); setPage(1) }} />
          <FilterSelect label="Filter by payment status" value={statusFilter} options={[{ value: '', label: 'All statuses' }, ...statuses.map((status) => ({ value: status, label: status[0].toUpperCase() + status.slice(1) }))]} onChange={(value) => { setStatusFilter(value); setPage(1) }} />
        </div>
      </div>

      {loading && <LoadingTable columns={7} />}
      {!loading && error && <EmptyState icon="alert" title="Couldn't load payments" description={error} action={<button className="secondary-action" type="button" onClick={() => setReload((value) => value + 1)}>Try again</button>} />}
      {!loading && !error && !payments.length && <EmptyState icon="payment-sessions" title="No payments found" description="Create a payment through a project API key or adjust your filters." />}
      {!loading && !error && payments.length > 0 && <>
        <div className="integration-table-wrap"><table className="integration-table payments-table"><thead><tr><th>Payment</th><th>Reference</th><th>Project</th><th>Amount</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>{payments.map((payment) => <tr key={payment.payment_id}>
            <td className="id-cell"><code>{payment.payment_id}</code></td><td><strong>{payment.merchant_reference}</strong></td><td>{payment.merchant_name}</td><td className="payment-amount">{formatMoney(payment.amount_minor, payment.currency)}</td><td><StatusBadge status={payment.status} /></td><td>{formatDate(payment.created_at)}</td><td><button className="table-action" type="button" onClick={() => setSelectedPayment(payment)}>View</button></td>
          </tr>)}</tbody>
        </table></div>
        <Pagination meta={meta} onPage={setPage} always />
      </>}
    </section>

    <Modal open={Boolean(selectedPayment)} onClose={() => setSelectedPayment(null)} title="Payment details" description={selectedPayment?.merchant_reference}>
      {selectedPayment && <div className="payment-detail-list">
        <DetailRow label="Payment ID"><span className="detail-copy-value"><code>{selectedPayment.payment_id}</code><CopyButton value={selectedPayment.payment_id} label="Copy payment ID" /></span></DetailRow>
        <DetailRow label="Project"><strong>{selectedPayment.merchant_name}</strong></DetailRow>
        <DetailRow label="Status"><StatusBadge status={selectedPayment.status} /></DetailRow>
        <DetailRow label="Amount"><strong>{formatMoney(selectedPayment.amount_minor, selectedPayment.currency)}</strong></DetailRow>
        <DetailRow label="Description">{selectedPayment.description || '—'}</DetailRow>
        <DetailRow label="Created">{formatDate(selectedPayment.created_at)}</DetailRow>
        <DetailRow label="Expires">{formatDate(selectedPayment.expires_at)}</DetailRow>
        <DetailRow label="Paid">{formatDate(selectedPayment.paid_at)}</DetailRow>
      </div>}
    </Modal>
  </div>
}

function SummaryCard({ icon, label, value, description }) {
  return <article className="integration-stat"><span className="integration-stat-icon"><Icon name={icon} /></span><span>{label}</span><strong>{value}</strong><small>{description}</small></article>
}

function DetailRow({ label, children }) {
  return <div className="detail-info-row"><span>{label}</span><div>{children}</div></div>
}

function formatMoney(amountMinor, currency = 'NPR') {
  return `${currency} ${(Number(amountMinor || 0) / 100).toFixed(2)}`
}
