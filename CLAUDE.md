# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 13 (PHP ^8.3) on the Laravel 11+ skeleton (`bootstrap/app.php` holds middleware aliases and routing; there are no HTTP/Console kernels). Server-rendered Blade with no JS framework, Tailwind v4 via `@tailwindcss/vite`, SweetAlert2 served locally from `public/vendor/sweetalert2`. PDF export via `barryvdh/laravel-dompdf`; audit trail via `spatie/laravel-activitylog`. Upgraded from Laravel 9 (Breeze) on 2026-10-28; the auth controllers are the old Breeze ones, adapted.

## Commands

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # seeds demo accounts (see below)

npm run build              # views are served from public/build: re-run after any class change
php artisan serve          # app at http://localhost:8000 (use --port if 8000 is taken)

php artisan test                                   # all tests
php artisan test --filter=InventoryStockTest       # one class
php artisan test --filter=test_admin_create_deducts_stock_immediately  # one method
./vendor/bin/pint          # code style
```

- **Corporate proxy:** HTTPS is intercepted with a self-signed root, so Composer (curl error 60) and npm fail. Export the Windows trusted roots to a PEM file and set `COMPOSER_CAFILE` / `NODE_EXTRA_CA_CERTS` to it; don't disable TLS verification. `npm run build` also downloads Instrument Sans (`bunny()` in `vite.config.js`, self-hosted, emitted via `@fonts`).
- **Demo logins** (`Database\Seeders\DemoSeeder`, idempotent): `admin@demo.test` / `password` (admin), `user@demo.test` / `password` (user). Self-registration is removed; admins create users under Settings.
- **Tests** use `RefreshDatabase` against `inventory_management_revamp_test` (`DB_DATABASE` in `phpunit.xml`), never the dev database. A real env var overrides it, e.g. `DB_DATABASE=other_test_db php artisan test`, which is how parallel runs avoid wiping each other.

## Domain and architecture

IT equipment issue tracking: staff record items handed out (`inventories`) against per-item shelf counts (`stocks`). Controllers in `app/Http/Controllers`; stock arithmetic lives in `App\Services\StockLedger`.

- **Roles**: plain `users.role` string (`admin` | `user`); `User::isAdmin()` / `isUser()`. Middleware aliases in `bootstrap/app.php`: `role:<name>` (`RoleMiddleware`) and `admin`. `EnsureUserIsActive` (web group) signs out deactivated accounts; `LoginRequest` refuses them.
- **Stock invariant** (relied on by Stock, Dashboard and `StockLedger`): `stocks.qty` is what is on the shelf now, and stock is deducted for exactly the inventory rows that are active AND `status = 'approved'` AND `condition = 'new'`. Given out = SUM(qty) of those rows (matched on item name); Total = in stock + given out. `StockLedger::save()` moves the difference between a row's before/after holding inside a transaction with `lockForUpdate`, and throws a `ValidationException` on `qty` rather than going negative.
- **Approval workflow** (`InventoryController`): admin creates are approved immediately (stock deducted); user creates are `pending` / `approval_type = 'new'`. A non-admin edit that changes something goes back to pending (`approval_type = 'edit'`, diff merged into `change_log`, `edited_by` set) and returns its stock; approval re-checks stock and deducts. Approvals are logged with `activity('inventory')->event('approved')`.
- **Item / department / site options**: the Item dropdown lists active `stocks` names (so issued items always match stock); departments and sites are in `config/inventory.php`. Items are still free-text strings in both tables; the `items` table and `inventories.item_id` exist to match production but nothing writes them.
- **Users** (`UserController`, ported from tender-management): the create modal has no password field. `store()` generates one (`App\Support\TemporaryPassword`) and emails it with `App\Mail\UserAccountCredentials` (queued, `ShouldBeEncrypted`; sent in-request while `QUEUE_CONNECTION=sync`; `MAIL_MAILER=log` locally writes it to `storage/logs/laravel.log`). Reusing a deactivated account's email reactivates it with a new emailed password; the Reactivate button keeps the old one. If sending throws, the account is kept, the error is reported, and an `error` flash tells the admin. The password is never shown, flashed or logged. The edit modal keeps an optional password (no email), as in tender. Emails use the published `resources/views/vendor/mail` layout (from tender); `App\Listeners\EmbedGfLogoInEmails` embeds the logo by Content-ID (`App\Support\EmailBranding`).
- **No hard deletes**: "delete" on inventory and users sets `active_yn = false`; lists, totals and the PDF exclude inactive rows. `DELETE /profile` returns 403 (accounts are admin-managed). Deactivating an approved new inventory row puts its qty back into stock.
- **Audit trail** (Settings > Audit Trail, `AuditTrailController`, `App\Support\AuditTrail`): ported from the sibling site-visit project. `HasStandardColumns` adds spatie's `LogsActivity` to every business model (dirty columns only, old → new; passwords, `change_log` and bookkeeping columns excluded; `active_yn` flips logged as deactivated/reactivated). `RecordSignIns` listener logs sign in/out and failed sign-ins; `RecordPageView` middleware logs signed-in HTML GETs. `App\Support\AuditTrailEntry` (the configured activity model) stamps the request IP on every entry. Logging failures are reported, never thrown. The old `audits` table is legacy and unused.

## Routes (`routes/web.php`)

`auth`: dashboard, profile, inventory index/store/update + PDF, stock index/store. `auth` + `role:admin`: inventory pending/approve (POST or PATCH)/destroy (deactivate), stock update (correct a count), and Settings at `/settings/users` (`users.*`, incl. `users.reactivate`) and `/settings/audit-trail` (`audit.index`). Create/edit happen in modals; there are no create/edit page routes.

## UI conventions (copied from tender-management, which mirrors site-visit)

Build pages from the shared pieces; don't restyle by hand.
- **Layouts**: `layouts.app` (navy top bar: Goodman Fielder logo `public/images/goodman-fielder-logo.png` left, system name centre, user menu right, icon nav row; admins see Approvals with a pending badge and Settings) and `layouts.auth` (sections `title`, `form-action`, `heading`, `subheading`, `content`, `form-attributes`, `after-form`). Pages use `@extends` + `@section('content')`.
- **Components** (`resources/views/components`): `x-page-header`, `x-card`, `x-button`, `x-icon-button` (icon-only row actions; `disabled-reason`), `x-icon` (Heroicons outline from `resources/svg/heroicons`), `x-modal` (native `<dialog>`), `x-form-errors`, `x-badge`, `x-settings-tabs`, `x-nav-link`.
- **CSS** (`resources/css/app.css`): `primary-*` teal, `gray-*`, `danger-*`, `brand-navy`; `.gf-label`, `.gf-input`, `.gf-hint`, `.gf-readonly-field`, `.gf-table`. Every `<select>` is `class="gf-input select-arrow"` (no forms plugin).
- **JS** (`resources/js/app.js`): `data-modal-open="{id}"` / `data-modal-close`; `<form data-confirm=...>` SweetAlert confirm (closes an open modal while asking, reopens it on Cancel); `<form data-processing="Saving…" data-processing-text=...>` non-dismissable spinner, on every form that changes data (create/edit/approve/deactivate/reactivate, stock, profile, password) but never on GET filters, the PDF download (a download never leaves the page, so it would spin forever) or sign in/out; `ProcessingAlertTest` enforces this, so give new POST forms one. It shows after a confirm, closes the modal so it's visible, and is cleared by the next page load or a Back-button `pageshow`. Flash `->with('status')` (success) / `->with('error')` / validation errors as SweetAlert. Modal forms carry a hidden `_modal` value and `:open="old('_modal') === '...'"` so failed validation reopens the right modal.

## Database

- Production is MariaDB/MySQL (a phpMyAdmin dump `inventory_system.sql` may sit in the repo root; `*.sql` is gitignored because it holds staff data and password hashes). Local dev uses SQL Server `localhost\SQLDEV` with Windows auth (`DB_CONNECTION=sqlsrv`, blank `DB_PORT`/`DB_USERNAME`/`DB_PASSWORD`, `DB_TRUST_SERVER_CERTIFICATE=true`), database `inventory_management_revamp`. Migrations must run on both engines; keep to portable Schema builder calls. The separate `inventory_management` database on SQLDEV belongs to another project.
- Timestamps are stored in UTC (`config/app.php`), as production always has.
- pdo_sqlsrv returns integer columns as strings; compare IDs with `==` / `assertEquals`, not strict equality.
- Business tables (`users`, `inventories`, `stocks`, `items`, `assets`) have `active_yn`, `created_by`, `updated_by` (FK → users, ON DELETE NO ACTION), filled by `App\Models\Concerns\HasStandardColumns` (also `active()` scope). Add it to any new business model. `active_yn` isn't mass-assignable: set it with property assignment or `forceFill`.
- Tables live in the default schema (no named schemas yet).
