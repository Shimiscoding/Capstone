# Analytics reports

The administrator analytics page uses `App\Services\AnalyticsReport` and the
existing `DashboardController::analytics` authorization gate. It reads existing
ticket data; no migration or payment workflow is required.

All sections share the issue-date range, payment status and vehicle filters.
The Report period dropdown uses Manila calendar boundaries. Editing a date selects
Custom dates. Dropdown changes apply automatically; date changes wait 400 ms and
require a complete valid range. Selecting Custom dates alone focuses the start
date without submitting. Reset clears all filters. Without JavaScript an Update
report button remains available. The
toolbar occupies one desktop row and wraps when its available width is narrow.
Custom periods include both dates,
with today's endpoint capped at generation time. The previous period has the same
elapsed duration and filters. All-time reports do not show a comparison.

Paid means the current `paid` status; other statuses remain unpaid, matching the
existing report. Amounts are assessed fines, not cash collections. Unpaid aging
uses Manila calendar days as of generation, including for historical issue-date
filters. Location rankings decrypt ticket location text in chunks; officer GPS
is not used. Different spellings can remain separate. Enforcer volume describes
recorded activity and is not a standalone performance score.

Violation groups count distinct tickets per group, so percentages can sum above
100%. Rankings show ten groups per page, retain filters and chart preferences,
and clamp invalid page numbers. Print output states when it includes only one
ranking page. The activity chart has a semantic data table fallback when its
external chart library cannot load.

## Verification

- `php artisan test --filter=AnalyticsTest`: 14 tests, 139 assertions passed after
  the compact-toolbar update. WebsiteRoleAccessTest also passed in the prior run.
- `npm run build`: passed.
- `node --check public/js/analytics-reports.js`: passed.
- Pint on `AnalyticsReport.php`, `AnalyticsTest.php` and
  `scripts/preview_analytics.php`: passed.
- Premium static audit: no findings; `storage/app/analytics-premium-audit.json`.
- Browser: 1440px desktop and 390px phone, no page overflow; native selection and
  keyboard chart changes; chart preference propagation into ranking links;
  accessible activity disclosure; empty results; invalid date focus; chart-load
  failure with a working activity table. Print styles were inspected; the native
  print dialog and PDF pagination were not exercised.

Run `php scripts/preview_analytics.php` to regenerate labeled fictional previews
in `public/demos/analytics-updated-*.html`. The script migrates and seeds only an
in-memory SQLite database, leaving application records unchanged. These preview
files are static: real filter and pagination requests require signing in to the
application. Browser screenshots are in
`storage/app/private/analytics-verification/`.
