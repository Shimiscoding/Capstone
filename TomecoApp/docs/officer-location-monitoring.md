# Officer Location Monitoring

## Deployment

1. Set the Reverb and Google Maps values documented in `.env.example`.
2. Restrict `GOOGLE_MAPS_BROWSER_KEY` in Google Cloud to the production web origins and the Maps JavaScript API.
3. Set `REVERB_ALLOWED_ORIGINS` to the comma-separated TOMECO web origins.
4. Run:

```shell
php artisan migrate --force
npm install
npm run build
php artisan optimize
php artisan reverb:start
```

Use a process supervisor for `php artisan reverb:start` in production. Terminate TLS at the web server or load balancer, and set `REVERB_SCHEME=https` plus the externally reachable host and port.

## Android contract

The Android officer app authenticates with its existing Sanctum token and sends locations only between Time In and Time Out:

```http
POST /api/officer/location
Authorization: Bearer <officer-sanctum-token>
Accept: application/json
Content-Type: application/json

{
  "latitude": 11.2445,
  "longitude": 125.0031,
  "accuracy": 7.5,
  "speed": 3.2,
  "heading": 110,
  "recorded_at": "2026-08-24T13:30:00Z"
}
```

`speed` is meters per second, matching Android `Location.getSpeed()`. `recorded_at` is optional; Laravel's server receipt time is always retained in `updated_at` and is used for live/stale status. The app must stop its `FusedLocationProviderClient` callback and pending network work after a successful Time Out. Laravel independently rejects off-duty submissions, so mobile behavior cannot bypass the privacy rule.

Recommended Android behavior is a foreground location service while on duty, a 5–10 second live update target, and bounded retry/backoff while offline. Do not replay points captured after Time Out. A `403` response means tracking must stop; `401` means re-authentication is required; `422` means the GPS payload is invalid; `429` means the sender must back off.

## Data and authorization

The existing project has no separate teams table. A team is the supervisor-led group represented by `users.supervisor_id`; consequently `officer_locations.team_id` references the supervising user. Assigned area comes from `users.area`. The removed legacy `badgeNumber` field is not recreated; the UI uses `username` as an optional personnel identifier.

Admins may authorize any `team.{supervisorId}.locations` private channel. Supervisors may authorize only the channel whose ID equals their own user ID. Current-location and history HTTP queries apply the same server-side rule.

## Operations

- Current location: one `officer_locations` row per officer via `updateOrCreate`.
- History: saved after the configured interval or meaningful distance, not on every live update.
- Status: active, delayed, offline, and off-duty thresholds live in `config/officer-location.php`.
- Time Out sets `is_sharing=false` and broadcasts the state immediately.
- Reverb reconnect is automatic through Echo; the dashboard reloads current positions after reconnect to close any event gap.

Route history is available separately at `GET /location-monitoring/officers/{officer}/history?date=YYYY-MM-DD` for an authorized future route-history view.
