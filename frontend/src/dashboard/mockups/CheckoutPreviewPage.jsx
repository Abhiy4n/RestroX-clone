import { useState } from 'react'
import Icon from '../Icon.jsx'
import './checkout-preview.css'

const methods = [
  { id: 'direct', label: 'Direct payment', icon: 'wallet' },
  { id: 'card', label: 'Test card', icon: 'test-cards' },
]

export default function CheckoutPreviewPage() {
  const [method, setMethod] = useState('direct')
  const [notice, setNotice] = useState('')

  function previewAction(message) {
    setNotice(message)
    window.setTimeout(() => setNotice(''), 2200)
  }

  return <div className="checkout-preview-page">
    <div className="preview-page-head">
      <div><span>UI MOCKUP</span><h1>Hosted Checkout Preview</h1><p>Preview the payment experience customers will see after a merchant redirects them to Restro.</p></div>
      <span className="preview-only-badge"><Icon name="alert" /> Preview only</span>
    </div>

    <div className="preview-workspace">
      <aside className="preview-controls" aria-label="Checkout preview controls">
        <div><span className="preview-control-label">Payment method</span><p>Switch between the first proposed checkout methods.</p></div>
        <div className="preview-method-list">
          {methods.map((item) => <button key={item.id} className={method === item.id ? 'active' : ''} type="button" aria-pressed={method === item.id} onClick={() => setMethod(item.id)}><span><Icon name={item.icon} /></span><div><strong>{item.label}</strong><small>{item.id === 'direct' ? 'Pay from sandbox balance' : 'Use simulated card details'}</small></div><Icon name="chevron" /></button>)}
        </div>
        <div className="preview-note"><Icon name="alert" /><p>This preview does not create a payment or collect card information.</p></div>
      </aside>

      <section className="checkout-stage" aria-label={`${methods.find((item) => item.id === method)?.label} checkout preview`}>
        <div className="checkout-browser-bar" aria-hidden="true"><span /><span /><span /><div><Icon name="lock" /> checkout.restro.test</div></div>
        <div className="checkout-shell">
          <header className="checkout-brand"><span className="checkout-logo">R</span><div><strong>Restro Pay</strong><small>Secure sandbox checkout</small></div><span className="checkout-test-mode">Test mode</span></header>

          <div className="checkout-layout">
            <section className="checkout-main">
              <div className="checkout-merchant"><span className="checkout-merchant-logo">HB</span><div><small>Paying</small><strong>Hotel Booking</strong></div></div>
              <div className="checkout-title"><h2>Complete your payment</h2><p>Choose how you want to complete this simulated transaction.</p></div>

              <div className="checkout-method-tabs" role="tablist" aria-label="Payment method">
                {methods.map((item) => <button key={item.id} type="button" role="tab" aria-selected={method === item.id} className={method === item.id ? 'active' : ''} onClick={() => setMethod(item.id)}><Icon name={item.icon} />{item.label}</button>)}
              </div>

              {method === 'direct' ? <DirectPayment /> : <CardPayment />}

              <button className="checkout-pay-button" type="button" onClick={() => previewAction(method === 'direct' ? 'Direct payment preview confirmed.' : 'Test card preview confirmed.')}>
                Pay NPR 1,500.00 <Icon name="arrowRight" />
              </button>
              <button className="checkout-cancel-button" type="button" onClick={() => previewAction('Payment preview cancelled.')}>Cancel and return to merchant</button>
            </section>

            <aside className="checkout-summary">
              <span>Order summary</span><div className="summary-product"><div><strong>Hotel reservation</strong><small>Booking #BOOKING-42</small></div><strong>NPR 1,500.00</strong></div>
              <div className="summary-line"><span>Subtotal</span><span>NPR 1,500.00</span></div>
              <div className="summary-line"><span>Sandbox fee</span><span>NPR 0.00</span></div>
              <div className="summary-total"><span>Total due</span><strong>NPR 1,500.00</strong></div>
              <p><Icon name="lock" /> Protected sandbox transaction. No real money will be charged.</p>
            </aside>
          </div>
        </div>
      </section>
    </div>

    <div className={`preview-toast${notice ? ' show' : ''}`} role="status" aria-live="polite">{notice}</div>
  </div>
}

function DirectPayment() {
  return <div className="checkout-method-panel">
    <div className="direct-account"><span className="direct-avatar">DD</span><div><small>Paying as</small><strong>Demo Developer</strong><span>demo@example.com</span></div><Icon name="check" /></div>
    <div className="direct-balance"><div><span>Sandbox balance</span><strong>NPR 10,000.00</strong></div><div><span>Balance after payment</span><strong>NPR 8,500.00</strong></div></div>
    <p className="method-help"><Icon name="active" /> The amount will be deducted once you confirm.</p>
  </div>
}

function CardPayment() {
  return <div className="checkout-method-panel card-fields">
    <label><span>Test card number</span><div className="mock-input"><Icon name="test-cards" /><span>4242 4242 4242 4242</span></div></label>
    <div><label><span>Expiry</span><div className="mock-input"><span>12 / 30</span></div></label><label><span>CVC</span><div className="mock-input"><span>123</span></div></label></div>
    <label><span>Name on card</span><div className="mock-input"><span>DEMO CUSTOMER</span></div></label>
    <p className="method-help"><Icon name="alert" /> Use test details only. Real card information is never accepted.</p>
  </div>
}
