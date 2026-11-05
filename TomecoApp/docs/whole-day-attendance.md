# Whole-day attendance

Enforcers now clock in once and clock out once per working date. There is no
midday reset or second afternoon clock-in. Admins configure one Time In window,
one Time Out window, and working days in Attendance Management. Existing schedule
restrictions remain in effect; this change does not allow unrestricted clocking.

Apply `2026_10_21_000000_use_whole_day_enforcer_attendance.php` through Laravel
migrations. For existing split schedules, it keeps the first clock-in window and
uses the final afternoon clock-out window. Staff without an afternoon clock-out
window keep their current values. Existing morning/afternoon attendance records
are untouched and retain their historical duty labels. Legacy columns remain;
new records use `session=whole_day`. Automatic rollback is blocked because it
cannot safely reconstruct the previous morning clock-out configuration.

The shared service locks the staff row before checking for any attendance on the
same date, including historical session records, and creating a daily record.
The API and supervisor website use that service. A missed prior Time Out blocks
a new duty until corrected by an administrator. Status remains timed_in through
midday and timed_out after the daily Time Out. Location sharing stops as before.

API callers can continue using the same attendance endpoints and top-level
`status`/`attendance` fields. Metadata exposes `session=whole_day`,
`sessions.whole_day`, and `schedule.sessions.whole_day`; the schedule also exposes
`in_start`, `in_end`, `out_start`, and `out_end` directly. The existing Android
client ignores this metadata and uses the unchanged status/timestamp fields.

Regression coverage: `EnforcerDutySessionsTest`, `EnforcerAttendanceTest`,
`OfficerAttendanceTest`, `UserAttendanceRestrictionsTest`,
`AdminAttendanceUpdateTest`, and `OfficerLocationMonitoringTest`.
