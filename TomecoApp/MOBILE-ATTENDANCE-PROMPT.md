# Mobile app update prompt

Update the TOMECO mobile enforcer attendance screen to support two independent duty sessions per day: Morning and Afternoon. The Laravel backend has already been updated. Inspect the existing mobile app and implement the changes using its existing architecture and UI components.

Use the existing authenticated endpoints:
- `GET /api/officer/attendance/status`
- `POST /api/officer/attendance/time-in`
- `POST /api/officer/attendance/time-out`

The status response retains `status` (`not_timed_in`, `timed_in`, or `timed_out`) and `attendance` and now includes:
- `date`: attendance date in the configured timezone.
- `session`: the current session (`morning` or `afternoon`), with open duty taking priority.
- `schedule`: `enabled`, `timezone`, `working_days` (lowercase weekday names), and `sessions.morning` / `sessions.afternoon`. Each schedule session has `in_start`, `in_end`, `out_start`, and `out_end`, formatted as `HH:mm` or null when unset.
- `sessions.morning` and `sessions.afternoon`: each contains `status` and `attendance` (null before Time In).
- Each attendance record includes `session`, `date`, ISO-8601 `time_in` / `time_out`, and nullable `time_in_location` / `time_out_location` objects containing latitude and longitude.

Show separate Morning and Afternoon cards, each with its configured windows, attendance timestamps, and status. Morning completion must not disable attendance for the entire day. Refresh status after either action, on app resume, and when the next schedule window starts. Use the server schedule timezone and date; do not hardcode noon or infer the next shift from the device timezone. The backend determines which session to record; keep POST payloads as the existing optional paired latitude/longitude values, without a client-supplied session.

Allow one Time In and one Time Out per session. Enable the appropriate action only on a scheduled working day, when the schedule is enabled and its required window is configured, and within the corresponding inclusive time window. A currently open duty must be timed out before another starts. Use top-level status to handle an open morning duty during the afternoon. If a Time Out window was missed, show guidance to contact an administrator for an attendance correction. Treat the server as authoritative and show its HTTP 422 message if an action is rejected.

Preserve authentication, GPS handling, permissions, and location sharing. Start/resume existing location tracking after a successful Time In; stop it after successful Time Out. Resume it again for afternoon duty. Avoid duplicate taps and automatic attendance submissions; retain state and permit retry after network errors. Do not mark an action completed before the server confirms it. If cached state is shown while offline, clearly indicate that it is stale and refresh before enabling attendance submissions.

Test morning Time In/Out followed by afternoon Time In/Out on the same date; duplicates in either session; afternoon Time In with morning duty still open; outside-window and non-working-day rejection; missing schedule; session/date transitions; API errors; location tracking through both sessions; and app restart after morning completion. Return the changed files and verification results.
