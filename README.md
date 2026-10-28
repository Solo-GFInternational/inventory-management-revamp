# Inventory Management System

Goodman Fielder's internal system for tracking IT equipment: what is on the shelf, what has been handed out, to whom, and who approved it.

Staff record each item they issue (a laptop bag, a mouse, a monitor…) against the stock it came from. Administrators approve what regular users submit, keep stock counts right, and manage accounts. Every change, sign-in and page visit is recorded in an audit trail.

## Contents

- [Features](#features)
- [Roles](#roles)
- [How stock is counted](#how-stock-is-counted)
- [Tech stack](#tech-stack)
- [Local setup](#local-setup)
- [Configuration](#configuration)
- [Running the tests](#running-the-tests)
- [Deploying](#deploying)
- [Project layout](#project-layout)

## Features

- **Dashboard**: totals for in stock, given out, pending approvals and items issued this month; quantities per item, filterable by site and department; recent issues.
- **Inventory**: issue equipment to a person (date, item, quantity, serial number, condition, department, site), edit or deactivate records, search and filter, and download a yearly PDF report.
- **Approvals**: issues and edits submitted by regular users wait here until an administrator approves them. Edits show each changed field as old → new.
- **Stock**: per-item **In stock**, **Given out** and **Total**, with low-stock and out-of-stock badges. Anyone can add stock; administrators can correct a count.
- **Settings → Users**: create, edit, deactivate and reactivate accounts. A new user is emailed a generated temporary password; the administrator never sees it.
- **Settings → Audit Trail**: who did what and when, from which IP address. It covers sign-ins (including failed ones), pages opened, and every record change with old → new values. Filter by user, type and date.
- **No hard deletes**: "delete" deactivates a record, and deactivated records drop out of lists, totals and reports. Deactivated users can't sign in.

## Roles

| | User | Administrator |
|---|:-:|:-:|
| View dashboard, inventory and stock | ✓ | ✓ |
| Issue equipment | ✓ (waits for approval) | ✓ (approved immediately) |
| Edit an inventory record | ✓ (goes back for approval) | ✓ |
| Add stock | ✓ | ✓ |
| Approve, deactivate inventory | | ✓ |
| Correct a stock count | | ✓ |
| Settings (users, audit trail) | | ✓ |

Self-registration is turned off. Administrators create every account.

## How stock is counted

- **In stock** is what's on the shelf now.
- **Given out** is the total quantity of inventory records that are **active**, **approved** and in **new** condition. Used items being re-issued don't come out of stock.
- **Total** = in stock + given out.

The system keeps these consistent automatically (`App\Services\StockLedger`):

- An administrator's issue comes off the shelf immediately. A user's issue comes off when it's approved.
- You can't issue more than is in stock. The form says how many are available.
- Editing an approved record adjusts stock by the difference. A user's edit puts the stock back until the edit is approved.
- Deactivating an approved record puts its quantity back on the shelf.

## Tech stack

- Laravel 13, PHP 8.3+
- Blade views with Tailwind CSS v4 (built with Vite); no JavaScript framework
- SweetAlert2 for alerts, confirmations and "Processing…" spinners (served from `public/vendor`, no CDN)
- Instrument Sans font and Heroicons, matching Goodman Fielder's other internal systems (site-visit, tender-management)
- `spatie/laravel-activitylog` for the audit trail, `barryvdh/laravel-dompdf` for PDF reports
- Databases: **MariaDB/MySQL in production**, **SQL Server** for local development. Migrations use only portable schema calls, so they run on both.

## Local setup

Requirements: PHP 8.3+ (with `pdo_sqlsrv` for SQL Server, or `pdo_mysql`), Composer 2, Node 20+, and a database.

```bash
git clone https://github.com/Solo-GFInternational/inventory-management-revamp.git
cd inventory-management-revamp

composer install
npm install

cp .env.example .env
php artisan key:generate
# edit .env: database and mail settings (see Configuration)

php artisan migrate --seed
npm run build
php artisan serve          # http://localhost:8000 (add --port=8010 if 8000 is taken)
```

`--seed` creates two demo accounts and some sample stock and inventory:

| Email | Password | Role |
|---|---|---|
| `admin@demo.test` | `password` | Administrator |
| `user@demo.test` | `password` | User |

Run `npm run build` again after changing any view or CSS, because pages are served from `public/build`. `npm run dev` gives live reloading instead.

**Behind the corporate proxy**, Composer and npm fail with certificate errors (curl error 60), because HTTPS traffic is re-signed by a company certificate. Export the trusted root certificates from the Windows certificate store to a PEM file, then point the tools at it:

```bash
COMPOSER_CAFILE=/path/to/windows-ca-bundle.pem composer install
NODE_EXTRA_CA_CERTS=/path/to/windows-ca-bundle.pem npm install
```

Don't turn off TLS verification instead.

## Configuration

All settings live in `.env`; `.env.example` documents each one.

### Database

Local SQL Server named instance with Windows authentication (leave port, username and password empty):

```dotenv
DB_CONNECTION=sqlsrv
DB_HOST=localhost\SQLDEV
DB_PORT=
DB_DATABASE=inventory_management_revamp
DB_USERNAME=
DB_PASSWORD=
DB_TRUST_SERVER_CERTIFICATE=true
```

Create the database first (for example `CREATE DATABASE inventory_management_revamp`). Production uses `DB_CONNECTION=mysql` with the usual host, port, username and password.

### Mail

Mail is used to send new users their sign-in details. Locally, `MAIL_MAILER=log` writes each email to `storage/logs/laravel.log` instead of sending it. To send through the Goodman Fielder relay:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.goodmanfielder.com.au
MAIL_PORT=25
MAIL_FROM_ADDRESS="system.application@gfinternational.com.au"
MAIL_FROM_NAME="Inventory Management"
```

The relay presents a self-signed certificate, so certificate verification is skipped outside production (`MAIL_VERIFY_PEER`, see `config/mail.php`).

With `QUEUE_CONNECTION=sync`, emails send while the page waits, behind a "Processing…" spinner. If sending fails, the account is still created and the administrator is told.

### Other

- **Lookup lists**: departments and sites are listed in `config/inventory.php`. Edit that file to add or rename one. The Item list comes from the Stock page.
- **Timestamps** are stored in UTC.

## Running the tests

```bash
php artisan test                                         # everything
php artisan test --filter=InventoryStockTest             # one class
php artisan test --filter=test_admin_create_deducts_stock_immediately   # one test
./vendor/bin/pint                                        # code style
```

The tests run against a real SQL Server database, `inventory_management_revamp_test` (set in `phpunit.xml`), which is wiped on every run. Create it once before the first run. Never point the tests at a database you want to keep. To use a different test database, set `DB_DATABASE`, for example `DB_DATABASE=my_test_db php artisan test`.

## Deploying

1. Back up the production database.
2. `composer install --no-dev --optimize-autoloader`, then `npm ci && npm run build`.
3. Set `.env`: `APP_ENV=production`, `APP_DEBUG=false`, the MariaDB connection, and the mail settings above. Also set `MAIL_VERIFY_PEER=false` unless the relay has been given a trusted certificate; without either, every email fails.
4. `php artisan migrate --force`
5. `php artisan optimize`

**Before the first deployment of this version:** it upgrades the app from Laravel 9 to 13 and adds several migrations:

- standard `active_yn` / `created_by` / `updated_by` columns
- the activity log tables
- renaming `password_resets` to `password_reset_tokens`

These have only been run on SQL Server, so run them against a **copy** of the production MariaDB database first.

## Project layout

| Path | What's there |
|---|---|
| `app/Http/Controllers` | Inventory, Stock, Dashboard, Users, Audit Trail, Profile and sign-in controllers |
| `app/Services/StockLedger.php` | All stock arithmetic (see [How stock is counted](#how-stock-is-counted)) |
| `app/Models/Concerns/HasStandardColumns.php` | The standard columns, plus automatic audit logging, for every business table |
| `app/Support/AuditTrail.php` | How audit entries are recorded and worded |
| `app/Mail`, `resources/views/emails` | The new-user credentials email |
| `resources/views/components` | Shared UI pieces: cards, buttons, icon buttons, modals, badges, page header, settings tabs |
| `resources/views/layouts` | The signed-in layout (navy header menu) and the signed-out layout |
| `resources/css/app.css`, `resources/js/app.js` | Colours and form styles; modals, confirmations, processing spinners and alerts |
| `config/inventory.php` | Departments and sites |
| `routes/web.php` | Every page and action, grouped by who may use them |

`CLAUDE.md` has more detailed notes for developers on conventions and how the pieces fit together.
