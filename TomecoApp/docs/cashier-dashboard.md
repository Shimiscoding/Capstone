# Cashier ticket overview

The cashier is a website-only role with a read-only ticket overview. It uses real
`Ticket_issued` records through `App\Models\Violation`, including its encrypted
motorist/plate casts and recorded citation descriptions. No sample tickets are
inserted. The overview shows 15 records per page, newest first (ID breaks date
ties), assessed fines in PHP, stored statuses, and issue timestamps in Asia/Manila.

## Try the sample account locally

Run `php artisan db:seed --class=CashierSampleSeeder` in the application folder.
The seeder prints the initial password once. Sign in using `cashier@example.com`
or `cashier.sample` at the existing login page. After login, `/dashboard` redirects
to `/dashboard/cashier`.

The seeder runs only in local/testing environments. Re-running it preserves an
existing cashier's password and details. It refuses to repurpose an existing
account or overwrite a conflicting username/phone number. It is intentionally
separate from `DatabaseSeeder` so normal seeding does not add demo accounts.

## Where the code lives

- `app/Models/User.php`: cashier role and `isCashier()` helper.
- `app/Http/Controllers/DashboardController.php`: role-based landing redirect.
- `app/Http/Controllers/CashierDashboardController.php`: authorization and pagination.
- `routes/web.php`: authenticated cashier route.
- `app/Http/Middleware/EnsureUserCanAccessWebsite.php`: cashier website route allowlist.
- `app/Http/Middleware/EnsureApiAccountActive.php`: denies cashier API access.
- `resources/views/cashier/`: table and access-denied page.
- `resources/views/layouts/admin-dashboard.blade.php`: shared shell with cashier navigation.
- `public/css/cashier-dashboard.css`: table sizing using the shared palette.
- `database/seeders/CashierSampleSeeder.php`: explicit local demo-account creation.
- `tests/Feature/CashierDashboardTest.php`: login, data, paging, and permission checks.

The cashier sees only Ticket overview and Sign out. Other website routes return
a 403 with a return link. Authenticated API calls are also rejected. Payment
posting, receipts, exports, ticket edits, and cashier account management screens
are outside this first version.

Run the feature checks with `php artisan test --filter=CashierDashboardTest`.
