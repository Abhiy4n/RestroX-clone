# Project Structure

File-level entries below cover maintained application source, project configuration, tests, and documentation. Dependency trees, generated/build output, caches, runtime data, and local secrets are intentionally excluded.

## Repository root

| Path | Purpose |
| --- | --- |
| `AGENTS.md` | Repository-wide instructions for coding agents. |
| `docs/PROJECT_STRUCTURE.md` | Concise map of maintained project files; update it when task changes alter the map. |
| `demo.html` | Silver Dashboard visual and interaction reference. |
| `.agents/skills/ponytail/SKILL.md` | Upstream Ponytail skill for choosing the smallest complete solution; imported from [DietrichGebert/ponytail](https://github.com/DietrichGebert/ponytail/tree/09c434ab73ca43761c5d8b0c5d341926c2fba710/skills/ponytail). |
| `.agents/skills/ponytail/LICENSE` | MIT license for the imported Ponytail skill. |
| `.agents/skills/clean-code/SKILL.md` | Upstream clean-code skill entry point; imported from [ciembor/agent-rules-books](https://github.com/ciembor/agent-rules-books/tree/893a88a6fce3a80c565bf39ac65021b43a8b2990/clean-code). |
| `.agents/skills/clean-code/clean-code.mini.md` | Core clean-code rules loaded by the skill. |
| `.agents/skills/clean-code/clean-code.md` | Extended clean-code reference. |
| `.agents/skills/clean-code/LICENSE` | MIT license for the imported clean-code skill. |

## Backend (`backend/`)

Laravel API and backend application.

### Project and tooling files

| Path | Purpose |
| --- | --- |
| `backend/AGENTS.md` | Laravel and PHP agent guidance. |
| `backend/CLAUDE.md` | Claude-specific Laravel guidance. |
| `backend/README.md` | Backend setup and Laravel project overview. |
| `backend/idea.md` | Backend project notes. |
| `backend/artisan` | Laravel command-line entry point. |
| `backend/composer.json` | PHP dependencies and Composer scripts. |
| `backend/composer.lock` | Locked PHP dependency versions. |
| `backend/package.json` | Backend-side JavaScript dependencies and scripts. |
| `backend/vite.config.js` | Vite configuration for backend assets. |
| `backend/phpunit.xml` | PHPUnit test configuration. |
| `backend/boost.json` | Laravel Boost configuration. |
| `backend/.editorconfig` | Editor formatting defaults. |
| `backend/.gitattributes` | Git attribute settings. |
| `backend/.gitignore` | Backend ignore rules. |
| `backend/.env.example` | Example backend environment settings. |
| `backend/.npmrc` | npm configuration for the backend. |
| `backend/.mcp.json` | Backend MCP server configuration. |
| `backend/opencode.json` | OpenCode configuration. |
| `backend/.codex/config.toml` | Codex configuration. |
| `backend/.grok/config.toml` | Grok configuration. |
| `backend/.pi/mcp.json` | Pi MCP configuration. |
| `backend/q` | Backend file with an unclear purpose; inspect before relying on it. |
| `backend/er->wallet()->firstOrCreate(` | Backend file with an expression-like name and unclear purpose; inspect before relying on it. |

### Application code (`backend/app/`)

| Path | Purpose |
| --- | --- |
| `backend/app/Actions/AuthenticateUser.php` | Authenticates a user. |
| `backend/app/Actions/LinkGoogleAccount.php` | Links a Google identity to a user. |
| `backend/app/Actions/ProvisionWallet.php` | Provisions a user's wallet and ledger setup. |
| `backend/app/Actions/RegisterUser.php` | Registers a user. |
| `backend/app/Actions/SignInWithGoogle.php` | Signs a user in through Google. |
| `backend/app/Enums/GoogleAuthOutcome.php` | Represents Google authentication outcomes. |
| `backend/app/Exceptions/WalletOperationException.php` | Reports wallet operation failures. |
| `backend/app/Http/Controllers/Controller.php` | Base HTTP controller. |
| `backend/app/Http/Controllers/Api/CurrentUserController.php` | Returns the authenticated user's details. |
| `backend/app/Http/Controllers/Api/LoginController.php` | Handles API login. |
| `backend/app/Http/Controllers/Api/LogoutController.php` | Handles API logout. |
| `backend/app/Http/Controllers/Api/NewPasswordController.php` | Handles password reset completion. |
| `backend/app/Http/Controllers/Api/PasswordResetLinkController.php` | Sends password reset links. |
| `backend/app/Http/Controllers/Api/RegisterController.php` | Handles API registration. |
| `backend/app/Http/Controllers/Api/UserSessionController.php` | Lists and manages user sessions. |
| `backend/app/Http/Controllers/Api/VerifyEmailController.php` | Handles email verification. |
| `backend/app/Http/Controllers/Api/WalletController.php` | Handles wallet API operations. |
| `backend/app/Http/Controllers/Auth/GoogleAuthController.php` | Handles Google OAuth callbacks. |
| `backend/app/Http/Requests/AddMoneyRequest.php` | Validates wallet deposit requests. |
| `backend/app/Http/Requests/LoginRequest.php` | Validates login requests. |
| `backend/app/Http/Requests/RegisterRequest.php` | Validates registration requests. |
| `backend/app/Http/Requests/ResetPasswordRequest.php` | Validates password reset requests. |
| `backend/app/Http/Requests/SendMoneyRequest.php` | Validates wallet transfer requests. |
| `backend/app/Http/Requests/SendPasswordResetLinkRequest.php` | Validates password reset link requests. |
| `backend/app/Http/Resources/LedgerTransactionResource.php` | Serializes ledger transactions for the API. |
| `backend/app/Http/Resources/UserResource.php` | Serializes users for the API. |
| `backend/app/Http/Resources/UserSessionResource.php` | Serializes user sessions for the API. |
| `backend/app/Http/Resources/WalletResource.php` | Serializes wallets for the API. |
| `backend/app/Listeners/RecordUserLogin.php` | Records successful user logins. |
| `backend/app/Models/LedgerAccount.php` | Represents a ledger account. |
| `backend/app/Models/LedgerEntry.php` | Represents a ledger entry. |
| `backend/app/Models/LedgerTransaction.php` | Represents a ledger transaction. |
| `backend/app/Models/LoginActivity.php` | Represents recorded login activity. |
| `backend/app/Models/Merchant.php` | Represents a merchant. |
| `backend/app/Models/SocialAccount.php` | Represents a linked social account. |
| `backend/app/Models/User.php` | Represents an application user. |
| `backend/app/Models/Wallet.php` | Represents a user wallet. |
| `backend/app/Providers/AppServiceProvider.php` | Registers application services and bindings. |
| `backend/app/Services/LedgerService.php` | Performs ledger operations. |
| `backend/app/Services/WalletSetup.php` | Sets up wallet-related records. |
| `backend/app/Support/NormalizePhone.php` | Normalizes phone number values. |

### Bootstrap, configuration, and persistence

| Path | Purpose |
| --- | --- |
| `backend/bootstrap/app.php` | Configures the Laravel application bootstrap. |
| `backend/bootstrap/providers.php` | Lists application service providers. |
| `backend/config/admin.php` | Configures administrative access. |
| `backend/config/app.php` | Core application settings. |
| `backend/config/auth.php` | Authentication settings. |
| `backend/config/cache.php` | Cache settings. |
| `backend/config/cors.php` | Cross-origin request settings. |
| `backend/config/database.php` | Database connections and settings. |
| `backend/config/filesystems.php` | Filesystem disks and settings. |
| `backend/config/logging.php` | Logging channels and settings. |
| `backend/config/mail.php` | Mail transport settings. |
| `backend/config/queue.php` | Queue connection settings. |
| `backend/config/sanctum.php` | API token and stateful authentication settings. |
| `backend/config/services.php` | Third-party service settings. |
| `backend/config/session.php` | Session settings. |
| `backend/database/.gitignore` | Keeps generated database files out of version control. |
| `backend/database/factories/UserFactory.php` | Creates users for tests and seed data. |
| `backend/database/migrations/0001_01_01_000000_create_users_table.php` | Creates users and password reset storage. |
| `backend/database/migrations/0001_01_01_000001_create_cache_table.php` | Creates cache storage tables. |
| `backend/database/migrations/0001_01_01_000002_create_jobs_table.php` | Creates queue job tables. |
| `backend/database/migrations/2026_10_04_090808_create_personal_access_tokens_table.php` | Creates API personal access token storage. |
| `backend/database/migrations/2026_10_04_090935_create_social_accounts_table.php` | Creates linked social account storage. |
| `backend/database/migrations/2026_10_05_175804_add_auth_security_fields_to_users_table.php` | Adds authentication security fields to users. |
| `backend/database/migrations/2026_10_12_161724_create_login_acitivties_table.php` | Creates login activity storage. |
| `backend/database/migrations/2026_10_18_150258_create_wallets_table.php` | Creates wallet storage. |
| `backend/database/migrations/2026_10_19_133842_create_ledger_accounts_table.php` | Creates ledger account storage. |
| `backend/database/migrations/2026_10_19_133910_create_ledger_transactions_table.php` | Creates ledger transaction storage. |
| `backend/database/migrations/2026_10_19_133936_create_ledger_entries_table.php` | Creates ledger entry storage. |
| `backend/database/migrations/2026_10_20_012633_create_merchants_table.php` | Creates merchant storage. |
| `backend/database/migrations/2026_10_20_063629_add_role_to_users_table.php` | Adds user roles. |
| `backend/database/migrations/2026_10_20_063630_create_idempotency_keys_table.php` | Creates request idempotency storage. |
| `backend/database/migrations/2026_10_20_153958_backfill_wallets_and_ledger_accounts_for_existing_users.php` | Backfills wallet and ledger accounts for existing users. |
| `backend/database/migrations/2026_10_20_154739_scope_wallet_idempotency_keys.php` | Scopes idempotency keys to wallets. |
| `backend/database/seeders/AdminSeeder.php` | Seeds the administrator account. |
| `backend/database/seeders/DatabaseSeeder.php` | Coordinates database seeders. |

### Web assets, routing, and tests

| Path | Purpose |
| --- | --- |
| `backend/public/.htaccess` | Routes supported web-server requests through Laravel. |
| `backend/public/index.php` | Public Laravel HTTP entry point. |
| `backend/public/robots.txt` | Search engine crawling policy. |
| `backend/public/favicon.ico` | Backend site favicon. |
| `backend/resources/css/app.css` | Backend stylesheet entry point. |
| `backend/resources/js/app.js` | Backend JavaScript entry point. |
| `backend/resources/views/welcome.blade.php` | Default Laravel welcome view. |
| `backend/routes/api.php` | API route declarations. |
| `backend/routes/console.php` | Console command route declarations. |
| `backend/routes/web.php` | Web route declarations. |
| `backend/tests/TestCase.php` | Base Laravel test case. |
| `backend/tests/Feature/AdminSeederTest.php` | Tests administrator seeding behavior. |
| `backend/tests/Feature/AuthEndpointsTest.php` | Tests authentication API endpoints. |
| `backend/tests/Feature/ExampleTest.php` | Framework example feature test. |
| `backend/tests/Feature/GoogleAuthTest.php` | Tests Google authentication flows. |
| `backend/tests/Feature/MySqlWalletConcurrencyTest.php` | Tests wallet concurrency against MySQL. |
| `backend/tests/Feature/UserSessionsTest.php` | Tests user session endpoints. |
| `backend/tests/Feature/WalletApiTest.php` | Tests wallet API behavior. |
| `backend/tests/Unit/ExampleTest.php` | Framework example unit test. |

## Frontend (`frontend/`)

React and Vite client application.

| Path | Purpose |
| --- | --- |
| `frontend/README.md` | Frontend starter and development notes. |
| `frontend/package.json` | Frontend dependencies and scripts. |
| `frontend/package-lock.json` | Locked frontend dependency versions. |
| `frontend/vite.config.js` | Vite development and build configuration. |
| `frontend/index.html` | Browser document and frontend mount point. |
| `frontend/.gitignore` | Frontend ignore rules. |
| `frontend/.oxlintrc.json` | Oxlint rules. |
| `frontend/public/favicon.svg` | Frontend site favicon. |
| `frontend/public/icons.svg` | Shared SVG icon definitions. |
| `frontend/src/App.jsx` | Root frontend component. |
| `frontend/src/index.css` | Global frontend styles. |
| `frontend/src/styles/motion.css` | Reusable search, dropdown, navigation, result, and reduced-motion styles. |
| `frontend/src/main.jsx` | React application entry point. |
| `frontend/src/dashboard/Dashboard.jsx` | Authenticated sandbox dashboard and live wallet overview. |
| `frontend/src/dashboard/dashboard.css` | Dashboard layout and component styles based on the approved silver reference. |
| `frontend/src/auth/api.js` | Authentication API client helpers. |
| `frontend/src/auth/auth.css` | Authentication page styles. |
| `frontend/src/auth/AuthPage.jsx` | Authentication page component. |
| `frontend/src/auth/PhoneCountryInput.jsx` | Phone and country-code input component. |
| `frontend/src/assets/hero.png` | Frontend hero image asset. |
| `frontend/src/assets/login.svg` | Login illustration asset. |
| `frontend/src/assets/react.svg` | React starter asset. |
| `frontend/src/assets/vite.svg` | Vite starter asset. |
