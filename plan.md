# Restro Sandbox Payment Gateway Plan

Last reviewed: 2026-10-23

This checklist is the source of truth for product scope and implementation progress. Update it in the same change that completes, removes, or materially changes a task. Mark an item complete only after its implementation and relevant tests are complete.

## Product goal

Restro is a sandbox payment gateway for simulated NPR payments. A developer creates a project, generates a secret API key, creates a payment from their backend, redirects the customer to Restro's hosted checkout, and verifies the result from their backend.

Restro is not intended to reproduce a complete wallet or banking application. Wallet and ledger code may remain internal infrastructure, but peer-to-peer transfers, real money, real cards, QR payments, subscriptions, and production payment processing are outside the initial product.

## Target integration flow

1. A developer creates a project in Restro.
2. The developer generates a sandbox API key.
3. The merchant backend creates a payment through the REST API or SDK.
4. Restro returns a payment ID and hosted checkout URL.
5. The merchant redirects its customer to Restro.
6. The customer signs in and confirms the dummy-money payment.
7. Restro records the result and redirects the customer back.
8. The merchant backend retrieves the payment status before marking its order paid.
9. Signed webhooks may automate step 8 after the basic flow is stable.

## Scope rules

### Initial release

- One sandbox environment.
- NPR amounts represented as integer minor units.
- One hosted dummy-balance payment method.
- Merchant-authenticated payment creation and retrieval.
- Customer-authenticated payment confirmation.
- A merchant payment dashboard.
- A small JavaScript/TypeScript server-side SDK.
- OpenAPI documentation and an AI integration skill.

### Not in the initial release

- Real money or banking connections.
- Public wallet-to-wallet transfers.
- Real or simulated card entry.
- QR checkout.
- Multiple currencies.
- Subscriptions, disputes, fees, or partial captures.
- Multi-language SDKs or an MCP server.

## Progress checklist

### Checkout mockup

- [x] Add a dedicated Mockups navigation section and checkout preview route.
- [x] Create a responsive hosted-checkout preview.
- [x] Preview direct sandbox-balance payment.
- [x] Preview simulated test-card payment without collecting or submitting card data.
- [x] Clearly label the experience as a non-functional preview.

### 1. Platform foundation

- [x] Email/password registration and login.
- [x] Google authentication and account linking.
- [x] Email verification and password reset.
- [x] Authenticated session management.
- [x] Active/inactive user controls.
- [x] Admin and developer roles.
- [x] Automatic sandbox wallet provisioning.
- [x] NPR wallet balance storage.
- [x] Double-entry ledger models and migrations.
- [x] Idempotent, transaction-safe dummy-money movement.
- [x] Wallet authorization and MySQL concurrency coverage.
- [ ] Remove public peer-to-peer send-money from the product flow after checkout replaces it.
- [ ] Reposition the wallet in the UI as an internal sandbox balance.

### 2. Projects and API credentials

The code may retain the `Merchant` name internally. User-facing copy should use **Project** where that is clearer for developers.

- [x] Merchant/project database model and ownership.
- [x] Project creation, listing, viewing, updating, and disabling.
- [x] Project ownership isolation.
- [x] Sandbox-only project environment.
- [x] Secret API-key generation.
- [x] Hashed key storage and one-time secret display.
- [x] Key listing, expiration, revocation, and last-used tracking.
- [x] Merchant API-key authentication middleware.
- [x] Merchant and API-key dashboard pages.
- [ ] Add return URL and optional cancel URL configuration rules.
- [ ] Add optional webhook URL configuration when webhooks are implemented.
- [ ] Change relevant dashboard language from Merchant to Project.

### 3. Core payment API

- [x] Finalize the payment request and response contract.
- [x] Add a `payments` table with public ID, project, customer, amount, currency, reference, status, checkout token, URLs, expiry, and completion timestamps.
- [x] Add the Payment model and relationships.
- [x] Define the initial statuses: `pending`, `succeeded`, `failed`, `cancelled`, and `expired`.
- [x] Validate positive minor-unit amounts, NPR currency, references, and redirect URLs.
- [x] Implement merchant-authenticated `POST /api/v1/payments`.
- [x] Implement merchant-authenticated `GET /api/v1/payments/{payment}`.
- [x] Scope payment access to the authenticated project.
- [x] Make payment creation retry-safe with merchant-scoped idempotency.
- [x] Generate an expiring, unguessable checkout token.
- [x] Add payment API resources and consistent errors.
- [x] Add feature tests for authentication, validation, isolation, idempotency, and expiration.

### 4. Hosted checkout and payment processing

- [ ] Add `GET /checkout/{token}` routing and checkout UI.
- [ ] Preserve the checkout destination through customer login.
- [ ] Display project name, reference, description, amount, and dummy balance.
- [ ] Add accessible confirm and cancel actions with pending states.
- [ ] Implement customer-authenticated payment confirmation.
- [ ] Lock the payment and affected balances during confirmation.
- [ ] Reject expired, cancelled, completed, and insufficient-balance payments.
- [ ] Move dummy money exactly once through the existing ledger.
- [ ] Attach the ledger transaction to the payment.
- [ ] Update payment status and balance movement atomically.
- [ ] Redirect to the configured return or cancel URL with a payment identifier.
- [ ] Ensure redirects alone cannot prove payment success.
- [ ] Cover success, duplicate confirmation, cancellation, expiration, and insufficient balance with tests.
- [ ] Verify keyboard access, responsive layouts, error states, and reduced-motion behavior.

### 5. Merchant payment dashboard

- [x] Replace the Payment Sessions placeholder with a payment list.
- [x] Add filtering by project, status, reference, payment ID, and description.
- [ ] Add date-range filtering when payment volume makes it useful.
- [x] Add payment detail with copyable IDs and timestamps.
- [x] Show loading, empty, error, and retry states.
- [ ] Update overview metrics to show payment count, successful volume, and recent payments.
- [ ] Remove Test Cards from navigation.
- [ ] Keep Refunds and Webhooks hidden until implemented.

### 6. OpenAPI and human documentation

- [ ] Add an OpenAPI specification for the stable payment API.
- [ ] Document API-key authentication and server-side secret handling.
- [ ] Document request and response schemas with examples.
- [ ] Document payment statuses and error responses.
- [ ] Document the hosted-checkout redirect and verification flow.
- [ ] Add a minimal end-to-end integration guide.
- [ ] Add a working example merchant application after the API is stable.

### 7. JavaScript/TypeScript SDK

- [ ] Create the SDK only after the core API contract is stable.
- [ ] Support server-side configuration through `RESTRO_SECRET_KEY`.
- [ ] Implement `payments.create()`.
- [ ] Implement `payments.retrieve()`.
- [ ] Provide typed responses and predictable API errors.
- [ ] Prevent or clearly warn against browser-side secret-key use.
- [ ] Add unit tests and one end-to-end example.
- [ ] Publish installation and version-compatibility instructions.

### 8. AI-assisted integration skill

- [ ] Create the skill only after the API, OpenAPI file, SDK, and example are stable.
- [ ] Teach the skill to inspect the merchant's framework and existing order flow.
- [ ] Teach it to install and configure the SDK server-side.
- [ ] Teach it to connect an existing Pay button to payment creation and redirect.
- [ ] Teach it to verify payment before updating an order.
- [ ] Require preservation of existing UI and unrelated payment behavior.
- [ ] Prohibit frontend secret exposure and redirect-only success handling.
- [ ] Include API, architecture, troubleshooting, and framework references.
- [ ] Require tests or an end-to-end sandbox verification before claiming completion.

### 9. Webhooks

- [ ] Start only after direct payment retrieval works end to end.
- [ ] Store a webhook URL per project.
- [ ] Define payment event payloads with unique event IDs.
- [ ] Sign payloads with timestamped signatures.
- [ ] Add delivery attempts, retry behavior, and delivery history.
- [ ] Add duplicate-event guidance for merchants.
- [ ] Add SDK signature verification.
- [ ] Test valid, invalid, duplicate, and retried deliveries.

### 10. Deferred capabilities

- [ ] Decide whether simple full refunds are needed after the first release.
- [ ] Consider configurable success/failure test scenarios.
- [ ] Consider additional SDK languages based on actual demand.
- [ ] Consider MCP tools only if they provide value beyond the SDK and skill.

## Today: first payment API slice

Today's goal is to let an authenticated project create a pending payment and retrieve it safely. Hosted checkout and balance movement come next.

- [x] Confirm and document the initial payment contract in this file.
- [x] Create the `payments` migration, model, factory, and relationships.
- [x] Add payment creation validation.
- [x] Implement `POST /api/v1/payments` behind merchant API-key authentication.
- [x] Implement `GET /api/v1/payments/{payment}` with project isolation.
- [x] Return a generated payment ID, pending status, checkout URL, and expiry.
- [x] Add focused feature tests for valid creation, invalid input, key authentication, project isolation, and retrieval.
- [x] Run the relevant backend test suite.
- [x] Update `docs/PROJECT_STRUCTURE.md` for every new project-owned file.

### Proposed initial payment contract

Request:

```json
{
  "merchant_reference": "ORDER-123",
  "amount_minor": 150000,
  "currency": "NPR",
  "description": "Order #123",
  "return_url": "https://merchant.example/orders/123/complete",
  "cancel_url": "https://merchant.example/orders/123"
}
```

Response:

```json
{
  "payment_id": "pay_...",
  "merchant_reference": "ORDER-123",
  "status": "pending",
  "amount_minor": 150000,
  "currency": "NPR",
  "checkout_url": "https://restro.example/checkout/...",
  "expires_at": "2026-10-23T12:30:00Z"
}
```

## Definition of the first usable release

The first release is complete when a separate example application can use a project API key to create a payment, redirect a signed-in customer to Restro, complete one dummy-money payment, retrieve a trustworthy `succeeded` status from its backend, and avoid processing the payment twice.
