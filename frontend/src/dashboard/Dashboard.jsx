import { useEffect, useRef, useState } from 'react'
import { apiRequest } from '../auth/api.js'
import '../styles/motion.css'
import './dashboard.css'

const navigation = [
  { label: 'Workspace', items: [{ id: 'overview', label: 'Overview', path: '/dashboard' }, { id: 'wallet', label: 'Wallet', path: '/dashboard/wallet' }, { id: 'transactions', label: 'Transactions', path: '/dashboard/transactions' }] },
  { label: 'Integration', items: [{ id: 'merchants', label: 'Merchants', path: '/dashboard/merchants' }, { id: 'api-keys', label: 'API Keys', path: '/dashboard/api-keys' }, { id: 'webhooks', label: 'Webhooks', path: '/dashboard/webhooks' }] },
  { label: 'Payments', items: [{ id: 'payment-sessions', label: 'Payment Sessions', path: '/dashboard/payment-sessions' }, { id: 'refunds', label: 'Refunds', path: '/dashboard/refunds' }, { id: 'test-cards', label: 'Test Cards', path: '/dashboard/test-cards' }] },
]

const navigationItems = navigation.flatMap((group) => group.items)
const overviewItem = navigationItems[0]

const pages = {
  overview: ['Sandbox overview', 'Your virtual payment workspace at a glance'],
  wallet: ['Virtual wallet', 'Inspect your simulated NPR balance'],
  transactions: ['Transactions', 'Review wallet money movements and their status'],
  merchants: ['Merchants', 'Manage applications that use the gateway'],
  'api-keys': ['API keys', 'Credentials for server-to-server integrations'],
  webhooks: ['Webhooks', 'Configure signed payment event delivery'],
  'payment-sessions': ['Payment sessions', 'Track hosted checkouts and payment states'],
  refunds: ['Refunds', 'Issue simulated payment reversals'],
  'test-cards': ['Test cards', 'Simulate card payment outcomes'],
}

export default function Dashboard({ user, onLogout, isLoggingOut }) {
  const [activePage, setActivePage] = useState(() => navigationItems.find((item) => item.path === window.location.pathname)?.id || overviewItem.id)
  const [accountOpen, setAccountOpen] = useState(false)
  const [workspaceOpen, setWorkspaceOpen] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [searchOpen, setSearchOpen] = useState(false)
  const [searchTyping, setSearchTyping] = useState(false)
  const [highlightedResult, setHighlightedResult] = useState(0)
  const [query, setQuery] = useState('')
  const [wallet, setWallet] = useState(null)
  const [transactions, setTransactions] = useState([])
  const [dataError, setDataError] = useState('')
  const accountButton = useRef(null)
  const accountMenu = useRef(null)
  const workspaceButton = useRef(null)
  const workspaceMenu = useRef(null)
  const searchPanel = useRef(null)
  const searchInput = useRef(null)
  const searchTypingTimer = useRef(null)
  const routeHeading = useRef(null)
  const hasSearchQuery = Boolean(query.trim())
  const searchResults = hasSearchQuery
    ? navigationItems.filter((item) => item.label.toLowerCase().includes(query.trim().toLowerCase()))
    : []
  const showSearchResults = searchOpen && hasSearchQuery
  const [title, description] = pages[activePage]

  function stopSearchTyping() {
    clearTimeout(searchTypingTimer.current)
    setSearchTyping(false)
  }

  useEffect(() => {
    Promise.all([apiRequest('/api/wallet'), apiRequest('/api/wallet/transactions')])
      .then(([walletResult, transactionResult]) => {
        setWallet(walletResult.wallet)
        setTransactions(transactionResult.data || [])
      })
      .catch((error) => setDataError(error.message))
  }, [])

  useEffect(() => {
    const currentItem = navigationItems.find((item) => item.path === window.location.pathname)
    if (!currentItem) window.history.replaceState({}, '', overviewItem.path)

    function closePopovers(event) {
      if (!accountButton.current?.contains(event.target) && !accountMenu.current?.contains(event.target)) setAccountOpen(false)
      if (!workspaceButton.current?.contains(event.target) && !workspaceMenu.current?.contains(event.target)) setWorkspaceOpen(false)
      if (!searchPanel.current?.contains(event.target)) {
        setSearchOpen(false)
        stopSearchTyping()
      }
    }
    function handleShortcut(event) {
      const typing = ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable
      if ((event.key === '/' && !typing) || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k')) {
        event.preventDefault()
        setSidebarOpen(true)
        setSearchOpen(true)
        setAccountOpen(false)
        setWorkspaceOpen(false)
        requestAnimationFrame(() => searchInput.current?.focus())
      }
      if (event.key === 'Escape') {
        if (accountMenu.current?.classList.contains('open')) accountButton.current?.focus()
        if (workspaceMenu.current?.classList.contains('open')) workspaceButton.current?.focus()
        setAccountOpen(false)
        setWorkspaceOpen(false)
        setSidebarOpen(false)
        setSearchOpen(false)
        stopSearchTyping()
        setHighlightedResult(0)
        setQuery('')
      }
    }
    function handleHistoryNavigation() {
      const item = navigationItems.find((navigationItem) => navigationItem.path === window.location.pathname) || overviewItem
      setActivePage(item.id)
      setSidebarOpen(false)
      setSearchOpen(false)
      stopSearchTyping()
      setAccountOpen(false)
      setWorkspaceOpen(false)
      setHighlightedResult(0)
      setQuery('')
    }

    document.addEventListener('pointerdown', closePopovers)
    document.addEventListener('keydown', handleShortcut)
    window.addEventListener('popstate', handleHistoryNavigation)
    return () => {
      document.removeEventListener('pointerdown', closePopovers)
      document.removeEventListener('keydown', handleShortcut)
      window.removeEventListener('popstate', handleHistoryNavigation)
      clearTimeout(searchTypingTimer.current)
    }
  }, [])

  function selectPage(page) {
    const item = navigationItems.find((navigationItem) => navigationItem.id === page)
    if (!item) return

    if (window.location.pathname !== item.path) window.history.pushState({}, '', item.path)
    setActivePage(item.id)
    setQuery('')
    setSearchOpen(false)
    stopSearchTyping()
    setAccountOpen(false)
    setWorkspaceOpen(false)
    setHighlightedResult(0)
    setSidebarOpen(false)
    requestAnimationFrame(() => routeHeading.current?.focus())
  }

  function updateSearch(event) {
    const nextQuery = event.target.value

    setQuery(nextQuery)
    setSearchOpen(true)
    setHighlightedResult(0)
    clearTimeout(searchTypingTimer.current)

    if (nextQuery) {
      setSearchTyping(true)
      searchTypingTimer.current = setTimeout(() => setSearchTyping(false), 240)
    } else {
      setSearchTyping(false)
    }
  }

  function toggleAccount() {
    setAccountOpen((open) => {
      if (!open) setWorkspaceOpen(false)

      return !open
    })
  }

  function toggleWorkspace() {
    setWorkspaceOpen((open) => {
      if (!open) setAccountOpen(false)

      return !open
    })
  }

  function handleSearchKeyDown(event) {
    if (!showSearchResults || !searchResults.length) return

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setHighlightedResult((current) => (current + 1) % searchResults.length)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlightedResult((current) => (current - 1 + searchResults.length) % searchResults.length)
    } else if (event.key === 'Enter') {
      event.preventDefault()
      selectPage(searchResults[highlightedResult]?.id)
    }
  }

  return <main className="dashboard-canvas"><div className="dashboard-shell">
    <header className="dashboard-topbar">
      <div className="account-control">
        <button ref={accountButton} className="account-button motion-action" type="button" aria-expanded={accountOpen} onClick={toggleAccount}>
          <span className="avatar">{user.name?.charAt(0).toUpperCase()}</span><span className="account-email">{user.email}</span><span className="plan-badge">Sandbox</span><Icon name="chevron" className="motion-chevron" />
        </button>
        <div ref={accountMenu} className={`account-menu motion-dropdown${accountOpen ? ' open' : ''}`} aria-hidden={!accountOpen} inert={!accountOpen}>
          <a className="menu-action motion-action" href="/auth/google/link" style={{ '--item-index': 0 }}><Icon name="link" /> Connect Google</a>
          <button className="menu-action motion-action" type="button" disabled={isLoggingOut} onClick={onLogout} style={{ '--item-index': 1 }}>{isLoggingOut ? <span className="compact-spinner" aria-hidden="true" /> : <Icon name="logout" />} {isLoggingOut ? 'Signing out…' : 'Sign out'}</button>
        </div>
      </div>
      <span className="topbar-slash topbar-secondary-slash">/</span>
      <div className="workspace-control">
        <button ref={workspaceButton} className="workspace-pill workspace-button motion-action" type="button" aria-expanded={workspaceOpen} onClick={toggleWorkspace}><Icon name="layers" /><span>Restro Gateway</span><Icon name="chevron" className="motion-chevron" /></button>
        <div ref={workspaceMenu} className={`workspace-menu motion-dropdown${workspaceOpen ? ' open' : ''}`} aria-hidden={!workspaceOpen} inert={!workspaceOpen}>
          <button className="menu-action motion-action" type="button" aria-current="true" style={{ '--item-index': 0 }} onClick={() => { setWorkspaceOpen(false); workspaceButton.current?.focus() }}><Icon name="check" /> Restro Gateway</button>
        </div>
      </div>
      <button className="round-button mobile-menu-button motion-action" type="button" onClick={() => setSidebarOpen((open) => !open)} aria-label="Toggle navigation"><Icon name="menu" /></button>
      <div className="topbar-spacer" /><span className="environment-status"><span /> Sandbox online</span>
    </header>

    <div className="dashboard-layout">
      <aside className={`dashboard-sidebar${sidebarOpen ? ' show' : ''}`}>
        <div ref={searchPanel} className="search-panel">
          <div className="nav-search motion-search"><span className={`search-leading${searchTyping ? ' is-typing' : ''}`} aria-hidden="true"><Icon name="search" className="search-icon" /><span className="search-typing-spinner" /></span><input ref={searchInput} value={query} onChange={updateSearch} onFocus={() => setSearchOpen(true)} onKeyDown={handleSearchKeyDown} placeholder="Search navigation…" aria-label="Search navigation" role="combobox" aria-autocomplete="list" aria-expanded={showSearchResults} aria-controls="navigation-results" aria-activedescendant={showSearchResults && searchResults.length ? `navigation-result-${searchResults[highlightedResult]?.id}` : undefined} /><kbd>/</kbd></div>
          <div id="navigation-results" className={`search-results motion-results${showSearchResults ? ' open' : ''}`} role="listbox" aria-label="Navigation results" aria-hidden={!showSearchResults} inert={!showSearchResults}>{searchResults.length ? searchResults.map((item, index) => <button id={`navigation-result-${item.id}`} key={item.id} className={highlightedResult === index ? 'highlighted' : ''} type="button" role="option" aria-selected={highlightedResult === index} style={{ '--result-index': index }} onPointerMove={() => setHighlightedResult(index)} onClick={() => selectPage(item.id)}><Icon name={item.id} /> {item.label}</button>) : <p>No matching pages</p>}</div>
        </div>
        {navigation.map((group) => <nav className="nav-group" aria-label={group.label} key={group.label}>
          <p>{group.label}</p>
          {group.items.map((item) => <button key={item.id} className={`nav-item motion-nav-item${activePage === item.id ? ' active' : ''}`} type="button" onClick={() => selectPage(item.id)}><Icon name={item.id} /><span>{item.label}</span></button>)}
        </nav>)}
      </aside>
      <button className={`dashboard-overlay${sidebarOpen ? ' show' : ''}`} type="button" onClick={() => setSidebarOpen(false)} aria-label="Close navigation" />
      <section className="dashboard-content">
        <div className="motion-route-content" key={activePage}>
          <div className="content-heading"><div><p className="content-eyebrow">RESTRO / SANDBOX</p><h1 ref={routeHeading} tabIndex="-1">{title}</h1><p>{description}</p></div><span className="mode-chip"><span /> Test mode</span></div>
          {dataError && <p className="dashboard-notice" role="alert">{dataError}</p>}
          {activePage === 'overview' && <Overview wallet={wallet} transactions={transactions} onNavigate={selectPage} />}
          {activePage === 'wallet' && <WalletPanel wallet={wallet} />}
          {activePage === 'transactions' && <TransactionList transactions={transactions} />}
          {!['overview', 'wallet', 'transactions'].includes(activePage) && <UpcomingPanel title={title} />}
        </div>
      </section>
    </div>
  </div></main>
}

function Overview({ wallet, transactions, onNavigate }) {
  return <><div className="metric-grid">
    <article className="metric-card"><span>Available balance</span><strong>{wallet ? `${wallet.currency} ${wallet.balance}` : 'Loading…'}</strong><small>Virtual funds only</small></article>
    <article className="metric-card"><span>Recent transactions</span><strong>{transactions.length}</strong><small>Latest wallet activity</small></article>
    <article className="metric-card"><span>Gateway status</span><strong className="healthy">Operational</strong><small>Sandbox environment</small></article>
  </div><section className="dashboard-panel"><div className="panel-heading"><div><h2>Recent transactions</h2><p>Your newest simulated money movements</p></div><button className="motion-action" type="button" onClick={() => onNavigate('transactions')}>View all</button></div><TransactionList transactions={transactions.slice(0, 5)} /></section></>
}

function WalletPanel({ wallet }) {
  return <section className="dashboard-panel centered-panel"><span className="large-icon"><Icon name="wallet" /></span><p>Available sandbox balance</p><h2>{wallet ? `${wallet.currency} ${wallet.balance}` : 'Loading…'}</h2><small>Balance changes are recorded through the double-entry ledger.</small></section>
}

function TransactionList({ transactions }) {
  if (!transactions.length) return <div className="empty-state"><Icon name="transactions" /><strong>No transactions yet</strong><span>Your sandbox wallet activity will appear here.</span></div>
  return <div className="transaction-list">{transactions.map((transaction) => <article className="transaction-row" key={transaction.reference}>
    <span className="transaction-icon"><Icon name="transactions" /></span><div><strong>{transaction.type.replaceAll('_', ' ')}</strong><small>{new Date(transaction.posted_at).toLocaleString()}</small></div><span className="transaction-status">{transaction.status}</span><strong className={`transaction-amount ${transaction.direction || ''}`}>{transaction.direction === 'credit' ? '+' : '-'} {transaction.currency} {(transaction.amount_minor / 100).toFixed(2)}</strong>
  </article>)}</div>
}

function UpcomingPanel({ title }) {
  return <section className="dashboard-panel centered-panel"><span className="large-icon"><Icon name="layers" /></span><p>Backend milestone</p><h2>{title} is coming next</h2><small>This workspace is ready once its secure backend endpoint is implemented.</small></section>
}

function Icon({ name, className = '' }) {
  const paths = { overview: 'M3 11.5 12 4l9 7.5M5.5 10v10h13V10M9 20v-6h6v6', wallet: 'M4 6.5h14a2 2 0 0 1 2 2V18H4a2 2 0 0 1-2-2V6.5Zm0 0V5a2 2 0 0 1 2-2h11v3.5M16 12h4', transactions: 'M7 7h11m0 0-3-3m3 3-3 3M17 17H6m0 0 3 3m-3-3 3-3', merchants: 'M4 10v10h16V10M3 10l2-6h14l2 6M8 20v-6h5v6', 'api-keys': 'M14 8a4 4 0 1 1-7.5 2L2 14.5V18h3v-2h2v-2h2l1-1', webhooks: 'M7 7a5 5 0 0 1 9-1M17 17a5 5 0 0 1-8 1M4 15a5 5 0 0 1 0-9m12 0h-3m3 0v3M9 18H6m3 0v-3', 'payment-sessions': 'M4 5h16v14H4zM4 9h16M8 15h3', refunds: 'M5 8h11a4 4 0 0 1 0 8H9M5 8l3-3M5 8l3 3', 'test-cards': 'M3 6h18v12H3zM3 10h18M7 15h3', search: 'm20 20-4.5-4.5M18 11a7 7 0 1 1-14 0 7 7 0 0 1 14 0', chevron: 'm8 10 4 4 4-4', menu: 'M4 7h16M4 12h16M4 17h16', logout: 'M10 5H5v14h5M14 8l4 4-4 4m4-4H9', link: 'M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 1-7 7l-2-2a5 5 0 0 1 7-7l1 1', layers: 'm12 3 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 17l9 5 9-5', check: 'm5 12 4 4L19 6' }
  return <svg className={className} aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d={paths[name] || paths.overview} /></svg>
}
