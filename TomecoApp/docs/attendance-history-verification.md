# Attendance history verification — 15 October 2026

The enforcer and supervisor history pages share `attendance-history-table`.
Locations sit beneath their associated timestamps, and horizontal overflow remains
inside the table card. Admins have visible Edit and Delete buttons; supervisors
retain read-only history access. Editing uses the existing time-correction endpoint.
New DELETE endpoints remove one attendance record and preserve its deletion through
the existing model audit events. Removing the last open enforcer duty stops location
sharing. Deleting a last-page record returns to a valid history page.

Verification:

- Attendance controller, authorization, duty-session and location regression tests.
- Admin and supervisor history rendering, JSON edit validation, per-record deletion,
  audit retention, remaining-duty location sharing and pagination boundary coverage.
- Strict premium static audit: zero findings. PHP Pint: passed. Production Vite build: passed.
- Browser preview generated from fictional, unsaved models at desktop 1440 × 900
  and narrow 390 × 844. No document overflow; the narrow table scrolls internally.
- Delete confirmation names the selected date and duty and initially focuses Cancel.
  Escape closes it and restores focus to the exact row's Delete button.
- Invalid edited time shows an inline error and preserves both entered values.

`php scripts/preview_attendance_history.php` regenerates the fictional preview for
future checks. Its model IDs are deliberately outside the normal record range;
use preview dialogs only for layout, validation and cancel checks. Server mutations
are exercised with the isolated test database. No real attendance records were
deleted or edited for this verification.
