# Database cleanup audit — November 12, 2026

Database: local MySQL `tomecodb`.

## Applied cleanup

Removed these obsolete columns from `users`:

- `attendance_afternoon_time_in_start`
- `attendance_afternoon_time_in_end`
- `attendance_afternoon_time_out_start`
- `attendance_afternoon_time_out_end`

Each column contained values for four users. The user explicitly authorized removing these four columns and their values after reviewing that finding. No database rows were deleted.

The web attendance rules and API use the active `attendance_time_*` daily schedule. The Android source does not reference the retired afternoon fields. The only runtime source references to the retired fields were stale entries in `User::$fillable`; those entries were removed.

Migration applied:

```sh
php artisan migrate --path=database/migrations/2026_11_12_000100_remove_unused_afternoon_attendance_columns.php --force
```

This migration removes only those four columns. It tolerates already-removed columns and refuses rollback because deleted values cannot be reconstructed. Earlier migrations remain available to build historical schemas during a fresh installation. The historical whole-day conversion test now recreates the old columns explicitly before testing that conversion.

## Preserved items

| Item | Reason retained |
| --- | --- |
| Active `users.attendance_time_*`, restrictions, and working days | Used by current attendance rules and schedule forms. |
| `enforcer_attendances.session` | Used by attendance APIs and history; contains five morning, two afternoon, and two whole-day records. |
| Empty `enforcer_attendances.time_out_latitude` / `time_out_longitude` | Written by the current time-out service and returned by the API. |
| Empty `ticket_issued.license_expiration_date` | Used by the web ticket view and mobile ticket contract; recently added repair. |
| Empty `ticket_violation_items.remarks` | Used when recording and displaying offense details, including “Others.” |
| Empty `users.nameExtension` | Used by staff creation, profiles, directory queries, and mobile user data. |
| Empty `users.remember_token` | Used by web authentication and password reset. |
| Empty `personal_access_tokens.expires_at` | Supported by Sanctum token expiration. |
| Empty `cache_locks` and `password_reset_tokens` tables | Laravel database cache locks and password reset storage. |
| Empty `settings` table | Used by application settings, runtime configuration, and administrator registration. |
| Other populated application and framework tables | Used by existing features; no further removal was justified. |

## Verification

All 18 tables were fingerprinted before and after the migration. SHA-256 comparisons included every remaining column and row, excluding only the four authorized columns and the newly inserted cleanup migration bookkeeping row. Table sets, remaining column lists, record counts, and record hashes matched exactly.

| Records | Before | After |
| --- | ---: | ---: |
| Users | 11 | 11 |
| Attendance | 9 | 9 |
| Tickets | 16 | 16 |
| Ticket violation items | 24 | 24 |

Verification manifests contain column names, counts, and hashes, with no raw record values:

- `storage/app/private/database-cleanup-2026-11-12/before.json`
- `storage/app/private/database-cleanup-2026-11-12/after.json`

Tests: **27 passed, 203 assertions**, using the isolated in-memory SQLite database. Coverage includes populated legacy-column removal, rerunning the cleanup, partially removed schemas, daily schedules, web/API attendance, and preserved historical sessions. Formatting passed for all four changed PHP files.

## Separate existing migration issue

The older `2026_11_10_000100_add_mobile_submission_details_to_ticket_issued` migration is pending in this database. It also adds `license_expiration_date`, which is already present through the November 12 repair. A future unrestricted migration run needs that overlap reconciled. This cleanup applied only its targeted migration and did not change or execute the pending migration.
