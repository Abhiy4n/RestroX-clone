import { useEffect, useRef, useState } from 'react'
import { apiRequest } from '../auth/api.js'
import '../styles/motion.css'
import ApiKeysPage from './integration/ApiKeysPage.jsx'
import MerchantsPage from './integration/MerchantsPage.jsx'
import Icon from './Icon.jsx'
import './dashboard.css'
import './integration/integration.css'

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
  const [themePreference, setThemePreference] = useState(() => window.localStorage.getItem('restro-dashboard-theme') || 'system')
  const [systemTheme, setSystemTheme] = useState(() => window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
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
  const isIntegrationPage = ['merchants', 'api-keys'].includes(activePage)
  const [title, description] = pages[activePage]
  const theme = themePreference === 'system' ? systemTheme : themePreference

  function stopSearchTyping() {
    clearTimeout(searchTypingTimer.current)
    setSearchTyping(false)
  }

  useEffect(() => {
    const colorScheme = window.matchMedia('(prefers-color-scheme: dark)')
    const updateSystemTheme = (event) => setSystemTheme(event.matches ? 'dark' : 'light')
    colorScheme.addEventListener('change', updateSystemTheme)
    return () => colorScheme.removeEventListener('change', updateSystemTheme)
  }, [])

  useEffect(() => {
    if (themePreference === 'system') window.localStorage.removeItem('restro-dashboard-theme')
    else window.localStorage.setItem('restro-dashboard-theme', themePreference)
  }, [themePreference])

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

  return <main className="dashboard-canvas" data-theme={theme}><div className="dashboard-shell">
    <header className="dashboard-topbar">
      <div className="account-control">
        <button ref={accountButton} className="account-button motion-action" type="button" aria-expanded={accountOpen} onClick={toggleAccount}>
          <span className="avatar">{user.name?.charAt(0).toUpperCase()}</span><span className="account-email">{user.email}</span><span className="plan-badge">Sandbox</span><Icon name="chevron" className="motion-chevron" />
        </button>
        <div ref={accountMenu} className={`account-menu motion-dropdown${accountOpen ? ' open' : ''}`} aria-hidden={!accountOpen} inert={!accountOpen}>
          <a className="menu-action motion-action" href="/auth/google/link" style={{ '--item-index': 0 }}><Icon name="link" /> Connect Google</a>
          <button className="menu-action theme-menu-action motion-action" type="button" role="switch" aria-checked={theme === 'dark'} onClick={() => setThemePreference(theme === 'dark' ? 'light' : 'dark')} style={{ '--item-index': 1 }}><Icon name={theme === 'dark' ? 'sun' : 'moon'} /><span>{theme === 'dark' ? 'Light mode' : 'Dark mode'}</span><span className="theme-toggle" aria-hidden="true"><span /></span></button>
          <button className="menu-action motion-action" type="button" disabled={isLoggingOut} onClick={onLogout} style={{ '--item-index': 2 }}>{isLoggingOut ? <span className="compact-spinner" aria-hidden="true" /> : <Icon name="logout" />} {isLoggingOut ? 'Signing out…' : 'Sign out'}</button>
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
      <section className={`dashboard-content${isIntegrationPage ? ' integration-content' : ''}`}>
        <div className="motion-route-content" key={activePage}>
          {!isIntegrationPage && <div className="content-heading"><div><p className="content-eyebrow">RESTRO / SANDBOX</p><h1 ref={routeHeading} tabIndex="-1">{title}</h1><p>{description}</p></div><span className="mode-chip"><span /> Test mode</span></div>}
          {dataError && <p className="dashboard-notice" role="alert">{dataError}</p>}
          {activePage === 'overview' && <Overview wallet={wallet} transactions={transactions} onNavigate={selectPage} />}
          {activePage === 'wallet' && <WalletPanel wallet={wallet} />}
          {activePage === 'transactions' && <TransactionList transactions={transactions} />}
          {activePage === 'merchants' && <MerchantsPage />}
          {activePage === 'api-keys' && <ApiKeysPage />}
          {!['overview', 'wallet', 'transactions', 'merchants', 'api-keys'].includes(activePage) && <UpcomingPanel title={title} />}
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
