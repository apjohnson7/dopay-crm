# Dopay CRM security review

**Date:** 26 September 2026
**Scope:** the Laravel 12 application in this repository, meaning authentication, authorization, country-account separation, approvals, payments, taxes, messaging, the API, file uploads, share links, browser security and configuration. The clickable prototype (`docs/prototype.html`) holds sample data only and is not part of the review.

**Method:**
- Manual review of every route, controller, service and view.
- A second, independent review of the whole code base.
- A third pass on the fixes themselves.
- Regression tests in `tests/Feature/SecurityTest.php` and `AccountSeparationTest.php`.

**Limits of this review:** the code was reviewed, not run. The build environment could not install Composer packages. Before going live, run the test suite, `composer audit` and a scan of the deployed site (see *Before going live*).

## What was found and fixed

| # | Severity | Issue | Fix |
|---|---|---|---|
| 1 | High | Staff in one country could submit, generate, return or cancel another country's invoices by changing the ID in the address | Every invoice action now checks the record's country |
| 2 | High | Anyone who could see a finance form could edit, submit, revise or void it, or record it as paid, including group approvers from other countries | Only the preparer (or a forms administrator) in the form's country can change it; money actions need the paying country and permission; revisions are limited to forms in approval or approved |
| 3 | High | The audit trail showed every country's history, including invoice share tokens that open invoices without signing in | Audit entries carry a country and each account sees only its own; share tokens are no longer logged, and existing tokens were rotated |
| 4 | High | People could approve their own finance forms, or sign several steps of one chain | The preparer, holder and purchaser can't approve; one person signs one approval step per version |
| 5 | High | The API returned any invoice by ID from any country; tokens without the `read` ability and deactivated users were accepted | Country check, ability check and active-user check on the API; tokens expire after 90 days |
| 6 | Medium | Signing and authorizer PINs could be guessed with unlimited tries; 4-digit PINs allowed | 6-digit PINs (common ones refused), 5 wrong tries per person pair every 15 minutes, lock and alert after 20 wrong tries by colleagues in a day; older PINs must be reset |
| 7 | Medium | A second approver could come from another country, and a Branch Manager could authorize payment reversals | The authorizer must work in the same country and hold the right permission (`payments.reverse` for reversals); approver lists show only that country |
| 8 | Medium | Invoices: a pending invoice could be edited, and an approver could edit a colleague's draft and then approve it | Only drafts can be edited, only by their preparer; the preparer can't approve their own invoice |
| 9 | Medium | A country's bank and mobile money details (printed on every invoice) could be changed by one person | Needs a second person's PIN; Finance Managers and administrators are notified |
| 10 | Medium | Finance form lines accepted negative amounts, hiding totals and reducing budget and VAT figures; typed fields accepted any value | Amounts must be zero or more (except bank balances); categories, people, countries and options are checked |
| 11 | Medium | The payment page showed another country's open invoices; a customer could be moved to another country's branch | Both now limited to the user's own country |
| 12 | Medium | Tax rates and filing days of other countries could be changed | Each country's settings require access to that country |
| 13 | Low | Shared invoice links never expired while unpaid, and kept working after cancellation | Links expire 30 days after they were last sent and stop at once on cancellation; responses are not cached or indexed |
| 14 | Low | No browser security headers; inline JavaScript | Content Security Policy (scripts only from the site), anti-framing, no-sniff, no-referrer, HSTS on HTTPS, no caching of financial pages; all inline scripts removed |
| 15 | Low | CSV exports could carry spreadsheet formulas (`=HYPERLINK(...)`) | Cells starting with `= + - @` are neutralised |
| 16 | Low | Sign-in could be tried from many addresses against one account; the password reset form revealed which emails exist; failed sign-ins were not recorded | 20 tries per account per hour as well as 5 per minute per address; the same reset message for every email; failed sign-ins in the audit trail |
| 17 | Low | Unsafe defaults: `APP_DEBUG=true` and a known demo password in `.env.example`; demo data could be seeded in production | Production defaults (debug off, secure cookies); demo data refuses to run in production |
| 18 | Low | Messages worked without the `messages.use` permission; a local user without a branch saw every country; smaller leaks (dashboard counts, supplier alerts, supplier names across countries) | Permission enforced; branchless users see nothing; each limited to the country |

## Already in place and confirmed

- **Separate country accounts:** only the Super Administrator crosses countries.
- **Passwords and 2FA:** passwords need 12+ characters in mixed case with numbers, and are checked against known breaches. Two-factor sign-in is required. Sessions are encrypted and last 30 minutes.
- **Second approval:** invoice cancellation, payment reversal, form revision and voiding need a second person's approval.
- **Payments:** an allocation can only go to the same customer's approved invoices, up to the balance owed, with row locking.
- **Records and uploads:**
  - Supplier bank details, ID numbers and 2FA secrets are encrypted.
  - Records are never deleted; everything important is in the audit trail.
  - Uploads are checked by content type and size, and stored privately under random names.
- **Output safety:** all output is escaped, and database queries use bound parameters.

## Before going live

1. On the server:
   - `composer install --no-dev`
   - `php artisan migrate --force`
   - `php artisan test` on a staging copy
   - `composer audit`, and fix anything it reports
2. `.env`:
   - `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL=https://…`
   - `SESSION_SECURE_COOKIE=true`
   - `DOPAY_DEMO_PASSWORD` empty
3. Serve only over HTTPS (Let's Encrypt in hPanel), with the document root at `public/`. No other folder should be reachable from the web.
4. Protect `.env` (not readable by the web server group), and back up the database daily off the server.
5. Every user sets up two-factor sign-in and a new 6-digit PIN at first sign-in. The Super Administrator account should be used only for administration.
6. Scan the live site with a scanner such as OWASP ZAP, then do a short penetration test before real customer data goes in.
7. Review the audit trail weekly, especially "Wrong signing PIN", "Failed sign-in" and "Changed … bank details".

## Remaining recommendations (not blocking)

- **Reversals:** add a flow for paid forms that also reverses the posted expenses. Today they can't be revised.
- **Alerts:** add email and SMS for PIN lockouts and payment-detail changes (they are in-app notifications today).
- **Attachments:** add a download route with country checks, and a virus scan of uploads, when attachments become viewable.
- **Web application firewall:** consider one (for example Cloudflare) in front of the site.
