# Dopay CRM

Administrative and accounting CRM for the DoPay country offices in Uganda, Nigeria, Cameroon and Ivory Coast. It covers invoicing, payments and receipts, VAT/TVA tracking, the finance forms (Appendices A–K) with e-signature approval chains, budget monitoring, team messaging and an audit trail.

Built with Laravel 12, MySQL 8 and plain Blade. There is no Node or asset build step, so it runs on shared hosting such as Hostinger.

A clickable prototype of the whole system is in `docs/prototype.html`. Open it in a browser to see the screens that are still to be ported.

## What is in this build

| Area | Status |
|---|---|
| Sign-in: invite only, two-factor (TOTP), 30-minute sessions, rate limiting | Done |
| Signing PIN for e-signatures and sensitive actions; second-person authorization | Done |
| Separate country accounts (see below); roles and permissions (16 roles, including Country Administrator) | Done |
| Suppliers: add and edit (Super Administrator and Finance Manager by default) | Done |
| Dashboard with alerts (forms to sign, approvals, overdue advances, tax returns, messages) | Done |
| Customers, invoices (draft → approval → generate → share link → PDF) | Done |
| Payments with allocation, automatic receipts, reversal | Done |
| **Taxes**: monthly VAT/TVA returns per country, invoice register with customer TINs, tax payments, filing-day alerts, CSV export | Done |
| Finance forms A, B, C, F, G, J, K: fill online, approval chains, e-signatures with local time, print/PDF | Done |
| Budget monitoring (Appendix F vs actuals) | Done |
| Team messaging across branches and countries | Done |
| Audit trail, global search, in-app help | Done |
| Read-only JSON API (`/api/v1`, Sanctum tokens) | Done |
| Expenses list, products, documents, reports, user admin and settings screens | In the prototype; to port next |

### Country accounts

- Each country is its own DoPay account. It has its own:
  - customers, invoices, payments, suppliers, expenses and taxes;
  - numbering (`DOPAY-UG-INV-2026-000001`);
  - users;
  - company details printed on documents (**Administration → Account**).
- Only the **Super Administrator** can open other accounts. They choose an account, or **All accounts** in USD, from the selector at the top.
- A **Country Administrator** manages their own account: users, company details, suppliers and settings.
- Group approvers (Financial Controller, Regional Manager, CFO, CEO) sign finance forms routed to them from any country, but browse only their own country's records.
- Team messages work across countries.

### How tax is connected

- **VAT/TVA charged** is the tax on approved and sent invoices, grouped by month of issue. Drafts and cancelled invoices are excluded.
- **Tax payments** are expenses in the `Tax` category.
  - A payment whose description names VAT or TVA is matched to its `tax_period`. If that is blank, it goes to the month before the payment.
  - A payment counts as remitted once a second person marks it paid.
  - PAYE, withholding and other taxes are listed separately.
- **Filing day** (`countries.vat_filing_day`) sets when each month's return is due in the following month.
  - Returns that are overdue, or due within 21 days, appear on the Dashboard.
  - Rates and filing days are edited on the Taxes page, which needs the Settings permission.
- The same Tax-category expenses feed the **Tax** line in budget monitoring.

## Requirements

- PHP 8.2+ with the extensions `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `gd`, `intl` and `zip`
- MySQL 8 (or MariaDB 10.6+)
- Composer 2

## Install locally

```bash
git clone https://github.com/apjohnson7/dopay-crm.git
cd dopay-crm
composer install
cp .env.example .env        # set DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

- **With demo data:** keep `DOPAY_DEMO_PASSWORD` in `.env`. The seeder then creates 18 demo users, customers, products, invoices, payments, tax payments and messages.
  - Sign in as `david.ssemanda@dopay.example` (Accounting Officer) or `patrick.mugisha@dopay.example` (Finance Manager), using that password.
  - Every demo user's signing PIN is `2468`.
- **Clean install:** leave `DOPAY_DEMO_PASSWORD` empty, then run `php artisan dopay:admin you@company.com "Your Name"`. This creates the first Super Administrator.

With `DOPAY_REQUIRE_2FA=true`, each user sets up an authenticator app and a signing PIN under **Security** before they can use the system.

## Deploy on Hostinger (shared or cloud hosting)

1. In hPanel, create a MySQL database and user.
2. Upload the project, or `git clone` it over SSH, to a folder above `public_html`, for example `~/dopay-crm`.
3. Over SSH, install and configure:
   ```bash
   cd ~/dopay-crm
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
   - Edit `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, and the database credentials.
   - Leave `DOPAY_DEMO_PASSWORD` empty for production.
4. Create the database, the admin and the caches:
   ```bash
   php artisan migrate --seed --force
   php artisan dopay:admin you@company.com "Your Name"
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
5. Point the domain at `~/dopay-crm/public`. If the plan can't change the document root:
   - replace `public_html` with a symlink: `rm -rf ~/public_html && ln -s ~/dopay-crm/public ~/public_html`;
   - otherwise copy `public/` into `public_html` and fix the two paths in `public_html/index.php`.
6. Add a cron job in hPanel → Advanced → Cron Jobs that runs every minute:
   ```
   cd ~/dopay-crm && php artisan schedule:run >> /dev/null 2>&1
   ```
   It sends payment reminders at 06:00 and processes the queue.
7. Enable SSL (free Let's Encrypt in hPanel).

## Tests

```bash
php artisan test
```

The tests run on in-memory SQLite. They cover:
- amounts in words;
- document numbering;
- the Expense Memorandum approval chain (role and country eligibility, PIN check);
- VAT matching between invoices and tax payments.

## Configuration

`config/dopay.php` holds:
- payment methods and terms;
- the 21 expense categories;
- permissions and the default grants per role, plus which roles work across countries;
- every finance form (fields, approval routes, reference patterns, footers).

Change it there, not in code.
