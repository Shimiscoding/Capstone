# TOMECO attendance behavior

## My Team popup

The available-enforcers dialog uses a plain dimmed backdrop without blur and
the shared subtle shadow. On opening, its heading receives initial focus without
a visual outline; interactive controls retain keyboard focus indicators. Closing
by Escape, backdrop, or close button returns focus to Add enforcer. Its table
scrolls inside the bounded popup on narrow or short screens.

## Dashboard search

`components.dashboard-search` owns the header search shared by administrator
and supervisor layouts. It searches dashboard pages, staff, and administrator
violation records through `dashboard.search`. Supervisor staff results are
limited to their assigned enforcers; administrator-only pages and tickets are
excluded. Cashier and enforcer website access remains unchanged. Ticket matching
reuses `ViolationRecords` so encrypted names and plates use the existing search
logic across all dates. Results are bounded and responses are private/no-store.
The search panel keeps a transient query rather than changing the current page
URL or storing search text. Results are ordinary navigation links, with loading,
empty, failure/retry, clear, keyboard, and composition-safe behavior owned by
`dashboard-search.js`. Styling uses `dashboard-search.css` and existing tokens.

## Area assignments

`area-assignments.index` uses the shared dashboard shell, user avatars, and table
with a scoped `area-assignments.css` layout. The toolbar wraps on narrow screens;
only the labelled, keyboard-focusable table region scrolls horizontally. Native
area selects keep platform-owned popups. Row Save buttons use the compact pale
outlined style. Failed selections are restored only for the submitted
`assignment_user_id`. Add Area keeps the entered name, reopens on validation
failure, and restores focus on close. Submit controls lock until navigation and
recover on history restoration. Admin and supervisor team authorization and
existing assignment routes remain the source of truth.

## Settings

The administrator Settings page contains General, Notifications, and Security.
Removed Violations & Fines, Roles & Permissions, and Import & Export sections
are unavailable for editing; old tab links show General and submissions for
removed sections fail validation. Stored values remain available to existing
application features. General timezone and date format controls reflect saved
values and preserve submitted values on validation errors.

## Audit log

`AuditLogController::index` authorizes administrators only. The shared sidebar
shows one Audit Log link only to administrators. Role filtering belongs to the
table toolbar. The native actor-role and activity selects
use platform-owned popups and submit GET filters; search and pagination preserve
those filters. Role filters refer to the recorded actor role at event time, not
the role of the affected account or that actor's current role. Unknown historical
roles remain in All activity. Sources: `User::ROLES`, `AuditLogger::record`, and
the current audit-log request.

`dashboard.audit-logs` owns read-only disclosures inside the canonical
`.users-table`. Created entries show populated saved fields directly; deleted
entries show populated removed fields directly. Neither snapshot invents an
empty counterpart. Updates retain old/new comparisons, including genuinely unset
values. Zero and false are recorded values. Stored audit evidence is unchanged.
Pagination uses 20 entries, newest first with an ID tie breaker, and clamps pages.
Verification: `AuditLogTest` and the browser preview.

## Canonical UI map

The administrator Attendance navigation opens the enforcer listing directly.
The supervisor attendance listing has been removed; legacy `type=supervisor`
and unsupported role filters also show enforcers. Existing supervisor attendance
records and their administrator-only detail routes remain available.

| Capability | Canonical owner | Source of truth | Allowed variants | Verification |
|---|---|---|---|---|
| Data table | `.users-table`, `components.attendance-history-table` | DESIGN.md and this contract | Enforcer and supervisor history | Attendance feature tests and browser preview |
| Form | `supervisor.attendance` restriction form | `AdminAttendanceController::updateRestrictions` | Individual daily schedule | EnforcerDutySessionsTest and browser validation |
| Date | Native HTML time/date inputs | DESIGN.md, server H:i validation, AnalyticsReport date validation | Platform-owned time/calendar popup | Browser keyboard and narrow dialog checks, AnalyticsTest Manila boundaries |
| Select/Listbox | Native HTML select | Existing attendance list | Platform-owned sort popup | Browser keyboard check |
| Scrollbar | `public/css/dashboard.css` | Runtime palette and DESIGN.md | Global stylesheet | Browser computed styles |
| CRUD | Existing attendance routes and controllers | Attendance authorization and tests | Admin corrections/deletion, supervisor team recording | AdminAttendanceUpdateTest and EnforcerAttendanceTest |

## Page navigation loading

All three HTML layouts reuse `components.page-loader`, `public/js/page-loader.js`,
and `public/css/page-loader.css`. Internal same-tab links and accepted native
form submissions show the same loading screen. The Navigation API additionally
covers scripted redirects, native `form.submit()`, and cross-document history
navigation where supported. Existing AJAX handlers and inline validation retain
their own feedback. Hash links, downloads, external links, new tabs, and modified
clicks do not trigger the screen. `data-no-page-loader` opts a link or form out.
Native navigation proceeds without interception or an artificial delay. The screen
fades in and out over 180ms, with immediate dismissal for reduced motion. A per-tab
session-storage boolean carries the overlay into the destination until `DOMContentLoaded`;
remote maps and images retain their own loading states without covering the usable page.
no form data or destination URLs are stored. Storage failures leave navigation working.
Abort,
navigation error, Escape, and history restoration dismiss the screen and restore
focus. It is hidden by default, so normal navigation still works without JavaScript.

## Authentication

`components.auth-layout` owns the login/registration shell, and
`components.auth-field` with `public/js/auth.js` owns their field errors,
password visibility, first-error focus, and duplicate-submit prevention.
`public/css/theme-tokens.css` supplies the shared palette and scrollbar tokens;
`auth.css` implements the auth document scrollbar baseline. Password recovery
reuses the same split shell and field components as login and registration.
All forms remain ordinary CSRF-protected server posts.
Errors retain non-secret values; passwords are never repopulated or stored.
Client validation shows inline text, server validation remains authoritative,
and back navigation restores the submit control. Password hints reflect the
configured minimum, rather than defining a new password policy.

Login, registration, and password recovery use a locked viewport without scrolling. Their shared
layout uses compact spacing and a two-column registration grid; `auth.js`
fits the entire form to the available height after resize or validation changes.
Other application screens retain their existing scroll ownership.

`AuthController` and `WebsiteRoleAccessTest` are the source for website role
access. `SettingsTest` covers the public administrator-registration setting.
The redesign preserves both the registration route gate and the conditional
login-page sign-up link. Successful sign-in retains its intended destination;
successful registration retains the dashboard destination. The shared office
photo is a static decorative background, with no live data queries. Reset forms
retain the token and prefilled email, show the configured password minimum, and
never repopulate password fields.

## Whole-day attendance

Supervisor Home displays team summaries and assigned enforcers. It does not
display the supervisor's own Time In/Time Out card or schedule setup prompt.
Enforcer attendance remains available through its existing sidebar page.
Its summary and coverage data include only officer accounts assigned to the
current supervisor. Blank or whitespace-only areas count as awaiting assignment.
The coverage panel shows up to four areas with member counts; it does not imply
live location or duty status. Account status uses `effectiveAccountStatus()`.
The directory displays eight members per page, ordered by name then ID, and
clamps an out-of-range page. Navigation uses existing authorized routes and GET
pagination; no new mutations or background requests are introduced. The date
uses Asia/Manila. Sources: `DashboardController::index`, `User`, and
`AreaAssignmentController`. Verification: `HomeDashboardTest`.

The user-requested whole-day workflow allows one enforcer Time In and one Time Out
per working date, under the administrator's configured windows and timezone.
Schedule windows can start before or after noon; attendance does not reset at
midday. Completed attendance cannot be reopened that day. A duty from a prior
date must be corrected or closed before another starts. Sources: `AttendanceRules`,
`EnforcerDuty`, `Api/AttendanceController`, and `EnforcerAttendanceController`.

The restrictions form exposes only `attendance_time_in_*`, `attendance_time_out_*`,
working days, and the existing enabled setting. No afternoon input is accepted by
the update validator. Existing afternoon columns remain solely for compatibility
and preservation. Migration `2026_10_21_000000_use_whole_day_enforcer_attendance`
carries the final existing afternoon Time Out window into the daily schedule.
All stored attendance rows, IDs, timestamps, and coordinates remain unchanged.

New records use `whole_day`; history displays Whole day. Legacy records retain
Morning/Afternoon labels. The API retains `status`, `attendance`, date, coordinates,
and schedule information; session metadata now has a single `whole_day` entry.
The existing Android client reads the unchanged top-level status and timestamps.
Location sharing remains active through the day and stops after Time Out.

Native time controls and native sort selection use platform-owned popups. Failed
schedule submissions reopen the existing dialog with entered values and focused
error text. Submit blocks duplicates. Closing restores focus. Empty lists and
bounded table overflow follow the shared attendance implementation. Permissions,
admin corrections, and per-record deletion retain their existing routes.

## Analytics reports

`DashboardController::analytics` retains the existing role gate; `AnalyticsReport`
owns read-only calculations. All sections use the same issue-date, vehicle and
payment-status filters. Committed filters and violation ranking pages use GET
parameters. Ranking pages contain ten groups and clamp out-of-range page numbers.
The compact filter toolbar uses a native Report period dropdown. Dropdown changes
automatically submit the report. Selecting Custom dates focuses the start date
without submitting. Editing either date selects Custom dates and submits after
400 ms only when both dates are complete, not future dates, and ordered correctly.
Invalid dates keep the current report visible and show associated inline feedback.
Preset submissions ignore custom-date drafts. A noscript Update report button
preserves server submission when JavaScript is unavailable. At desktop widths all five
fields and the actions occupy one row, wrapping only at narrower viewports.
Dates are validated on the server with inline errors and focus recovery. Native
date/select controls use platform-owned popups, matching the existing records flow.

The report uses Asia/Manila dates, PHP assessed fine values and current ticket
status. Paid means `status = paid`; all other statuses are counted as unpaid,
consistent with the existing ticket report. Counts describe tickets, not unique
motorists. The comparison is the immediately preceding equal elapsed duration,
under the same filters; all-time reports omit comparison. Aging uses calendar
days as of generation, including for historical issue periods. It does not imply
a legal deadline. Amounts are not presented as payment-date collections.

The chart has a semantic activity table fallback for script/network failures.
Empty sections retain their panels and offer filter recovery. Chart controls
persist in the URL and survive filter submission. No new motion is added beyond
the short chart update, which respects reduced motion. Print output includes
dates, filters, generation time and the visible ranking page's scope. Table
overflow belongs to each panel, while the shared content scroller owns the page.
Sources and verification: `AnalyticsReport`, `AnalyticsTest`, and the existing
ticket status model. Locations group decrypted recorded text; officer GPS is
not used as a substitute for incident location.

## Home dashboard

The administrator Home reuses the application shell with four compact totals,
a selectable seven-day bar chart, quick links and the five most recent tickets.
Today and daily chart buckets use Asia/Manila calendar dates, converted to the
configured storage timezone. Today’s assessed fines are ticket amounts, not
payment collections. Unpaid counts all statuses other than paid, matching Records.
Summary links open Records with the corresponding period/status filters. Recent
tickets are ordered by issue timestamp then ID; View Ticket opens the existing
full ticket page. Supervisor and cashier Home routes retain their own workflows.

The native chart selector switches between issued tickets and registrations.
`home-dashboard.js` reads the server-rendered activity table and draws a local
SVG without a network dependency. Zero series explain that no activity was
recorded; if scripts are unavailable the table remains open and readable.
Tables own their horizontal overflow while the shared content area owns page
scrolling. Colors and shapes use existing theme tokens; styles are scoped to
`.home-dashboard`. Sources: `DashboardController::index`, `Violation`, `User`,
and existing website role middleware. Verification: `HomeDashboardTest`.

## Violation Records

The approved list keeps Ticket, Driver, Vehicle, Violation, Enforcer, Issued,
Fine / status, and a final View Ticket column. Both the ticket number and the
text-only View Ticket action navigate to the existing full ticket route.
Driver names retain their existing history links. Ticket and driver detail routes,
permissions, data and navigation remain unchanged; no drawer is introduced.
At the user's request, the driver-history Fine / status cell stacks its amount
above a small tinted status badge, with no dot, matching the supplied reference.

`ViolationRecords` owns server filtering, totals, ordering and pagination (15 rows,
stable ID tie breakers and page clamping). All summaries reflect the same filters
as the list. The default and Reset period is this month in Asia/Manila. Existing
one-sided date bounds continue to work under Custom dates. Fines are assessed
amounts, not collection totals. Existing current-status semantics are preserved.

`violation-records.js` refreshes the list through same-origin GET requests without
replacing the focused search field. Search debounces 300 ms, respects IME, has an
immediate clear action, and cancels/ignores superseded requests. Native select
and date popups remain platform-owned. Loading preserves table geometry; errors
keep the old list with a Retry action. Committed query parameters support browser
back/forward, sorting and pagination. Without JavaScript, Update records provides
normal server submission. Print output identifies the visible page and filters.

Names and plates stay encrypted at rest. Search checks authorized, filtered
candidates in bounded chunks in PHP and does not create a plaintext index. This
scan trades speed on large datasets for compatibility with the existing schema.
Sources: `Violation`, `ViolationRecords`, the unchanged web routes and website
access middleware. Verification: `ViolationRecordsPageTest`, `NormalizedTicketTest`,
website access tests and the isolated runtime preview.
