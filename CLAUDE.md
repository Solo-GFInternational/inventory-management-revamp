# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 9 (PHP ^8.0.2) with Breeze auth scaffolding, server-rendered Blade views, Tailwind + Alpine.js built by Vite. PDF export via `barryvdh/laravel-dompdf`. The README is the stock Laravel README and has no project-specific content.

## Commands

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate

php artisan serve          # app at http://localhost:8000
npm run dev                # Vite dev server (hot reload for Blade/CSS/JS)
npm run build              # production assets

php artisan test                                   # all tests
php artisan test --filter=AuthenticationTest       # one class
php artisan test --filter=test_users_can_authenticate_using_the_login_screen  # one method
./vendor/bin/pint          # code style (Laravel Pint)
```

**Testing warning:** `phpunit.xml` has the SQLite in-memory DB lines commented out and there is no `.env.testing`, so tests run against the database in `.env` (currently MySQL `inventory_system`). Every feature test uses `RefreshDatabase`, which wipes that database. Before running tests, create a `.env.testing` pointing at a throwaway database, or uncomment the SQLite lines in `phpunit.xml`.

## Domain and architecture

Inventory issue tracking: staff record items handed out (`inventories`), backed by a stock count per item (`stocks`). All business logic lives in fat controllers in `app/Http/Controllers`; there are no services, form requests (beyond Breeze's), policies, or observers.

- **Roles** are a plain `users.role` string (`admin` | `user`, default `user`). `User::isAdmin()` / `isUser()` check it. Route middleware aliases in `app/Http/Kernel.php`: `role:<name>` (`RoleMiddleware`, exact match) and `admin` (`AdminMiddleware`).
- **Approval workflow** (`InventoryController`):
  - `store`: admins create rows as `approved`; users create rows as `pending` with `approval_type = 'new'`. `given_by` is always the signed-in user.
  - `update`: the change is applied immediately. If a non-admin changed anything, the row goes back to `pending` with `approval_type = 'edit'`, the field diff is stored in `inventories.change_log` (JSON, cast to array), and `edited_by` is set.
  - `approve`: for pending rows with `condition = 'new'`, the stock row is looked up by matching `stocks.item` to `inventories.item` **by string name** and decremented by `qty`; approval fails if stock is missing or insufficient. Approval then clears `approval_type` and `change_log`.
  - Updates and deletes write to `audits` (`user_id`, `inventory_id`, `action`, `changes` as JSON text). Creates and approvals are not audited.
- **Stock** (`StockController`): adding stock does `firstOrCreate` on the item name and increments `qty`. Items are free-text strings everywhere; there is no item master table in use (`App\Models\Item` is an empty stub with no migration).
- **Dashboard** (`DashboardController`): `SUM(qty)` per item over `inventories`, filterable by `site` and `department` (dropdowns built from distinct values).
- **PDF** (`downloadPdf`): yearly inventory report rendered from `resources/views/inventory/pdf.blade.php`, A4 landscape.
- An `assets` table (with disposal fields) exists in migrations but has no model, controller, or routes yet.

## Routing gotchas (`routes/web.php`)

- Inventory routes are declared twice: explicit routes and `Route::resource('inventory', ...)->except(['show'])`, both under `auth`. Edit/delete are therefore available to any signed-in user, not just admins.
- `inventory.approve` is defined twice (PATCH under `auth`, POST under `auth` + `role:admin`); the later definition owns the route name.
- `/inventory/download-pdf` has no auth middleware.
- `/admin-only` and `/user-only` are placeholder test routes.
- Post-login redirect is `/dashboard` (`RouteServiceProvider::HOME`); `/` redirects to login.

## Database

Current `.env` uses MySQL (`inventory_system`). Migrations use default Laravel conventions (no schemas, no `active_yn`/`created_by`/`updated_by` audit columns), so existing tables do not yet follow the user's global table standards; apply those standards to new tables and confirm with the user before reworking existing ones.
