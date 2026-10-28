# Inventory Management System

Goodman Fielder's internal system for tracking IT equipment: what is on the shelf, what has been handed out, to whom, and who approved it.

Staff record each item they issue (a laptop bag, a mouse, a monitor…) against the stock it came from. Administrators approve what regular users submit, keep stock counts right, and manage accounts. Every change, sign-in and page visit is recorded in an audit trail.

## Contents

- [Features](#features)
- [Roles](#roles)
- [How stock is counted](#how-stock-is-counted)
- [Tech stack](#tech-stack)
- [Local setup](#local-setup)
- [Database structure](#database-structure)
- [Configuration](#configuration)
- [Running the tests](#running-the-tests)
- [Deploying](#deploying)
- [Project layout](#project-layout)

## Features

- **Dashboard**: totals for in stock, given out, pending approvals and items issued this month; quantities per item, filterable by site and department; recent issues.
- **Inventory**: issue equipment to one or more people at once. Date, condition, item and site are entered once; "+ Add person" adds a row (taken by, department, serial number) per person, and each person gets their own single-unit record. Edit, view or deactivate records one at a time, search and filter, and download a yearly PDF report.
- **Barcodes**: every inventory record gets a Code 128 barcode (e.g. `INV000123`), saved as a JPEG label. Download it from the record's edit form or details page.
- **Handover**: on an item's page, **Return to stock** puts it back on the shelf; **Assign to new person** then records the new holder on the same record, so the barcode label stays valid. A regular user's assignment goes to Approvals.
- **Timeline**: each item's page has a timeline of notes (e.g. "New battery fitted") and its returns and handovers. Anyone can add a note; the author or an administrator can remove it.
- **Scanner**: hold a barcode label up to the device camera; the record's view-only details page opens. A typed-in code works too. The camera needs HTTPS (or localhost).
- **Approvals**: issues and edits submitted by regular users wait here until an administrator approves them. Edits show each changed field as old → new.
- **Stock**: per-item **In stock**, **Given out** and **Total**, with low-stock and out-of-stock badges. Anyone can add stock; administrators can correct a count.
- **Settings → Users**: create, edit, deactivate and reactivate accounts. A new user is emailed a generated temporary password; the administrator never sees it.
- **Settings → Audit Trail**: who did what and when, from which IP address. It covers sign-ins (including failed ones), pages opened, and every record change with old → new values. Filter by user, type and date.
- **Settings → Types**: the dropdown lists for departments and sites. Add, rename, reorder and deactivate options without a code change.
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
| Settings (users, audit trail, types) | | ✓ |

Self-registration is turned off. Administrators create every account.

## How stock is counted

- **In stock** is what's on the shelf now.
- **Given out** is the total quantity of inventory records that are **active**, **approved**, in **new** condition and **not returned**. Used items being re-issued don't come out of stock.
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
- **Microsoft SQL Server**, with tables grouped into schemas (see [Database structure](#database-structure)). The legacy system ran on MariaDB; its data is imported once when this version goes live.

## Local setup

Requirements: PHP 8.3+ with the `sqlsrv` / `pdo_sqlsrv` extensions, Composer 2, Node 20+, and a SQL Server database (2017 or later).

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

`--seed` loads the department and site lists, and creates two demo accounts with some sample stock and inventory:

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

## Database structure

SQL Server, with tables grouped into schemas by concern:

| Schema | Tables | Purpose |
|---|---|---|
| `security` | `users`, `password_reset_tokens` | Accounts and sign-in |
| `inventory` | `stocks`, `inventories`, `assets` | Stock items, equipment issued, fixed assets |
| `ref` | `types` | Managed dropdown lists (departments, sites) |
| `logs` | `activity_log` | The audit trail (append-only) |
| `dbo` | `migrations`, `cache`, `jobs`, `sessions`, `failed_jobs`… | Laravel's own tables |

- **Standard columns:** every business table has `active_yn`, `created_by`, `created_at`, `updated_by` and `updated_at`. The two user columns are foreign keys to `security.users`, filled in automatically from the signed-in user.
- **Foreign keys:** an inventory record points at its stock item (`stock_id`), department and site (`department_id`, `site_id` → `ref.types`), and the users who gave and edited it. All foreign keys are `NO ACTION`, since nothing is ever hard-deleted.
- **Constraint names:** `ux_` (unique), `ix_` (index), `fk_` (foreign key) and `ck_` (check), followed by the table and columns. Unique indexes on values that can be deactivated (email, item name, type name) only cover active rows. Fixed values (role, condition, status) are enforced with check constraints.

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

Create the database first (for example `CREATE DATABASE inventory_management_revamp`); the first migration creates the schemas inside it. Production uses the same `sqlsrv` driver; set host, port, username and password if it doesn't use Windows authentication.

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

- **Lookup lists**: departments and sites are managed under **Settings → Types**. The Item list comes from the Stock page.
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
3. Set `.env`: `APP_ENV=production`, `APP_DEBUG=false`, the SQL Server connection, and the mail settings above. Also set `MAIL_VERIFY_PEER=false` unless the relay has been given a trusted certificate; without either, every email fails.
4. `php artisan migrate --force`
5. `php artisan inventory:barcodes` (gives any record without a barcode one, and redraws missing label images; safe to re-run)
6. `php artisan optimize`

Serve the site over **HTTPS**: browsers only allow the camera on secure pages, so over plain HTTP the scanner falls back to typing the code. Barcode JPEGs live in `storage/app/private/barcodes`; include that folder in backups (they can also be regenerated with `inventory:barcodes`).

**First deployment of this version:** it moves the system from MariaDB to SQL Server, and the migrations are a fresh baseline rather than changes to the old tables. Create an empty SQL Server database, run `php artisan migrate --force` and `php artisan db:seed --class=TypeSeeder --force` (not the demo seeder), then import the existing users, stock and inventory from the MariaDB system, and run `php artisan inventory:barcodes` so imported records get barcodes. Department, site and item text becomes ids pointing at `ref.types` and `inventory.stocks`. Rehearse the import on a copy first.

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
| `database/migrations` | The schema baseline: schemas, tables, foreign keys, indexes and checks |
| `database/seeders/TypeSeeder.php` | The initial department and site lists |
| `routes/web.php` | Every page and action, grouped by who may use them |

`CLAUDE.md` has more detailed notes for developers on conventions and how the pieces fit together.
