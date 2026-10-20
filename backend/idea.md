Project Context — Virtual Payment Gateway & Sandbox Payment System
1. Project Overview
We are developing a reusable virtual payment gateway similar in workflow to eSewa, Khalti, or Stripe, but exclusively for testing payments using simulated money.
The purpose of this platform is to let developers integrate payment functionality into their existing or future applications without connecting real banks, payment providers, or payment cards.
For example, if a developer builds an e-commerce website, hotel booking system, restaurant management system, mobile application, or subscription platform, they should be able to connect our payment gateway using API credentials and a small amount of integration code.
Our payment platform should manage the entire simulated payment process, including checkout, authentication, payment confirmation, wallet balance updates, transaction recording, refunds, and notifying the connected application.
The goal is to build an independent sandbox payment service, not a wallet feature limited to one project.
2. Core Project Objectives
The platform must eventually provide:
Virtual wallets with administrator-issued test money.
Simulated wallet-to-wallet payments.
QR code payments.
Simulated debit/credit card payments.
A reusable payment API for external applications.
Merchant registration and API credentials.
Hosted payment checkout pages.
Payment status verification.
Webhooks to notify integrated applications of payment results.
Complete transaction history, failed-payment simulation, refunds, and security controls.
All money is virtual and has no real-world monetary value.
Real banking connections, actual card processing, and regulated payment services are outside the scope of this project.
3. Example User Experience
Suppose a developer builds an e-commerce application.
Step 1: Integrate our gateway
The developer registers their application in our payment platform, receives a sandbox API key, and configures payment integration.
Step 2: Customer checks out
A customer purchases an item worth NPR 1,500.
The e-commerce backend sends a payment request to our payment gateway.
Step 3: Our platform handles payment
Our platform creates a payment session and redirects the customer to our hosted checkout.
The customer chooses one of three methods:
Wallet payment
QR payment
Virtual card payment
Step 4: Complete payment
Our platform validates the payment and processes it using virtual money.
Step 5: Notify the integrated application
Our platform updates the payment status and sends a signed webhook to the e-commerce backend.
The e-commerce application verifies the result and marks the order as paid.
The developer should not need to implement wallet accounting, QR generation, card simulation, or financial transaction processing inside their application.
Those responsibilities belong to our payment gateway.
4. Existing Technical Architecture
We already have a Laravel backend.
Current architecture:
Backend: Laravel / PHP
Database: MySQL in the current runtime
Authentication: Existing Laravel login, registration and Google OAuth
Frontend: Existing frontend, which should remain unchanged for now
Wallet system: Eloquent Wallet model and database migration
Accounting foundation: Ledger models, migrations and services
Use the existing Laravel project rather than creating another backend.
Build the platform as a modular Laravel application with a clean separation between controllers, services, models and routes.
Existing components
Authentication
User.php
RegisterUser.php
SignInWithGoogle.php
GoogleAuthController.php
Wallet
Wallet.php
Wallet database migration
Accounting
LedgerAccount.php
LedgerTransaction.php
LedgerEntry.php
Services
WalletSetup.php
LedgerService.php
The ledger tables and models have already been created.
WalletSetup.php creates the accounting accounts associated with wallets and the platform's virtual-money issuance account.
LedgerService.php is intended to handle virtual-money issuance and wallet transfers while recording balanced transactions.
Before treating any component as fully implemented, inspect the current repository files and verify their actual state. Previous planning discussions do not guarantee that all changes have been applied.
5. Virtual Wallet System
Every registered user receives a wallet.
Initial wallet structure:
ID
User ID
Balance
Currency
Created timestamp
Updated timestamp
Initially, we support NPR only.
New wallets start with a zero balance.
Wallets are automatically created during normal registration and new Google OAuth registration.
The user-facing wallet balance is stored as an exact decimal value.
Financial transactions should use integer minor units. For example:
NPR 100 = 10,000 paisa
NPR 500 = 50,000 paisa
NPR 1,500 = 150,000 paisa
Never use floating-point arithmetic for money.
The ledger must record every balance-changing operation, and cached wallet balances must remain consistent with those records.
Wallet balances must not be directly editable through public APIs.
6. Authentication and Roles
For now, the system supports two roles:
Admin
The administrator's only special payment capability at this stage is issuing virtual money to wallets.
The initial admin is created through a Laravel seeder with credentials stored outside source control.
Developer
Normal user accounts receive the developer role automatically.
Developers can use their own wallets, perform permitted transfers, and eventually register applications to use the gateway.
Normal email/password registration and Google OAuth should continue working without frontend changes.
Do not introduce a complex role/permission package unless genuinely necessary.
Future merchant/project permissions should remain separate from platform administrator permissions.
7. Ledger and Transaction Processing
We already have the following accounting structure:
ledger_accounts
ledger_transactions
ledger_entries
The payment platform must use a double-entry ledger.
For example, when a user transfers NPR 500:
The sender's wallet liability account is debited.
The receiver's wallet liability account is credited.
Both entries belong to the same transaction.
Both wallet balances update atomically.
The system must prevent:
Negative wallet balances.
Duplicate money movements.
Invalid transaction amounts.
Unauthorized transfers.
Currency mismatches.
Partial transactions.
Concurrent requests causing incorrect balances.
Tampering with posted ledger records.
The existing LedgerService.php should remain responsible for core money movements.
Use understandable methods such as:
addMoney()
sendMoney()
checkBalance()
saveTransaction()
updateBalances()
Separate responsibilities into small, easy-to-debug methods.
Important: Inspect the current database connection and replace any PostgreSQL-specific locking logic if the application is running MySQL.
8. Merchant and External Project Integration
This is one of the most important parts of the project.
The platform must support multiple external applications using the same payment infrastructure.
A developer should be able to:
Register a merchant/project.
Generate its API credentials.
Configure callback and webhook URLs.
Select allowed sandbox payment methods.
Integrate the gateway into their application.
Test payments without real money.
View payments, errors and transaction history.
Merchant API credentials must be generated securely, scoped to the correct merchant, stored securely, and revocable.
Secret API credentials must only be used on a merchant's server, never embedded in public frontend code.
Merchant credentials authenticate the external application; they must not grant access to platform administration or arbitrary user wallets.
9. Payment Methods
We want three main checkout methods, initially built as simulations.
A. Wallet / Direct Payment
The customer selects wallet payment.
Our gateway authenticates the customer, displays the merchant and payable amount, and asks the customer to confirm the payment.
The system validates the available virtual balance and processes the transaction through the ledger.
B. QR Payment
Our gateway generates a unique QR code associated with a payment session.
The QR should:
Identify a specific payment.
Contain a secure checkout URL or reference.
Expire after a configurable period.
Prevent duplicate payment processing.
Allow the customer to confirm payment through our platform.
The QR must not directly modify balances merely because it was scanned.
C. Virtual Card Payment
We will create simulated cards for testing card checkout.
Virtual cards may include:
Test card number
Expiration date
Simulated spending limit or linked virtual balance
Card status
Simulated authorization result
The system should support:
Successful payments
Declined payments
Expired cards
Insufficient funds
Invalid test card details
Payment timeout
Use only clearly identifiable test cards. Do not collect, store, or process real card information.
All three payment methods should reuse the same core payment engine and ledger, rather than duplicating transaction-processing logic.
10. Payment API and Hosted Checkout
The platform should eventually provide endpoints similar to:
POST /api/v1/payments — Create a payment session.
GET /api/v1/payments/{id} — Check payment status.
POST /api/v1/payments/{id}/refund — Request an authorized refund.
GET /checkout/{session} — Display hosted checkout.
These are proposed interfaces and should be finalized during implementation.
The initial payment request should include:
Merchant order reference
Amount in minor units
Currency
Optional description
Authorized return/cancel configuration
The gateway should return a payment ID and hosted checkout URL.
Payment confirmation must be verified server-to-server. A successful browser redirect alone is not proof of payment.
Use signed webhooks, retry-safe delivery, and unique event IDs so merchants can safely process notifications.
11. Payment Lifecycle
Use a clearly defined payment state machine.
Suggested states:
requires_payment_method
requires_confirmation
processing
succeeded
failed
cancelled
expired
Refunds should have their own records and states.
Payment attempts should be separate from payment intents so failed attempts can be recorded without incorrectly changing the final financial state.
Different checkout methods may produce different attempts, but all should be processed through a common payment engine.
12. Security Requirements
Even though the platform uses virtual money, implement proper security because other applications will rely on its payment results.
Required safeguards include:
Authentication and authorization
Merchant isolation
Unique idempotency keys
Database transactions and row locking
Balanced ledger entries
Request validation
Signed webhooks
Rate limiting
Expiring checkout sessions
Secure API credential storage
Transaction and audit logging
Refund validation
Consistent payment status verification
Protection against duplicate and replayed requests
Never allow a client to submit arbitrary wallet balances or directly decide whether a payment succeeded.
Admin funding should use the same ledger posting mechanism as other money movements.
13. Implementation Strategy
We should develop the platform in small, testable stages.
Phase 1 — Foundation
Authentication
User roles
Automatic wallet creation
Ledger models and tables
Wallet setup
Admin seeder
Phase 2 — Wallet Operations
Safe virtual-money issuance
Wallet transfers
Balance and transaction history APIs
Authorization checks
Idempotency and concurrency testing
Phase 3 — Merchant Integration
Merchant registration
API credentials
Merchant authentication
Project settings
Merchant-isolated transaction records
Phase 4 — Core Payment Engine
Payment sessions/intents
Hosted checkout
Payment state transitions
Server-side status verification
Signed webhooks
Phase 5 — Payment Methods
Wallet checkout
QR checkout
Simulated card checkout
Failed-payment test scenarios
Phase 6 — Developer Experience
API documentation
Example Laravel integration
Simple React/Next.js integration
Merchant dashboard
Payment logs
Webhook testing tools
Refund support
The target is to make integration into a new application straightforward, with minimal configuration and a small amount of backend code.
14. Coding Style and Development Rules
The developer of this project is learning Laravel, so code must remain understandable and maintainable.
Follow these rules:
Use simple, descriptive function names.
Give every function one clear responsibility.
Avoid unnecessarily complex abstractions.
Use short comments explaining why important logic exists.
Keep HTTP request handling in controllers.
Keep shared payment and accounting logic in services.
Keep database relationships in models.
Follow Laravel conventions where they improve readability.
Preserve already implemented functionality.
Avoid renaming existing files unnecessarily.
Explain which files are being changed and why.
Test one feature before implementing the next.
Never bypass ledger accounting with direct wallet balance changes.
Do not modify the frontend unless specifically requested.
Verify the existing repository before making assumptions.
15. Current Immediate Goal
We are not trying to finish the entire gateway immediately.
The immediate goal is to complete a small but working end-to-end payment sandbox.
The initial milestone should demonstrate:
An administrator issues virtual NPR to a wallet.
A developer creates a merchant/project with an API credential.
An external test application requests payment using that credential.
Our gateway creates a hosted payment session.
The customer opens checkout and pays using their virtual wallet.
The platform records the transfer correctly.
The connected application receives a signed payment-success notification and verifies the final status.
Once this flow works reliably, implement QR and virtual card simulations using the same payment system.
Do not build separate independent payment engines for QR, card, and wallet payments.
16. What I Need From the Coding AI
First, inspect the existing codebase and identify which components are complete, incomplete, or incorrectly implemented.
Then:
Review the current ledger service for MySQL compatibility and transaction safety.
Identify the minimum changes needed to complete wallet operations.
Keep the existing authentication and Google OAuth flows intact.
Implement a simple admin/developer role structure if not already implemented.
Complete the backend wallet endpoints.
Plan the smallest reusable merchant payment integration.
Implement and test each stage incrementally.
Do not generate the entire payment platform in one step.
The long-term vision is a reusable, self-hosted virtual payment gateway where other developers can integrate sandbox wallet, QR, and card payments using API credentials without having to implement their own payment infrastructure.

