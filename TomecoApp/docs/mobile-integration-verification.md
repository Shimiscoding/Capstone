# Android / Laravel integration verification

Update — 15 October 2026: `evidence_images` is now the only stored evidence column.
Migration `2026_10_15_000100_consolidate_ticket_evidence` preserves historical photos
before removing `evidence_image`. Android sends only the ordered `evidence_images[]`
parts, with one element for a single photo. The singular request key remains an
input alias for existing clients and is never stored separately or returned. The
12 October results below document the earlier implementation; its references to a
duplicate first-photo part or six uploaded file parts are historical.

Consolidation verification: all 129 Laravel tests passed (1,100 assertions), all
3 Android `ViolationSubmissionTest` tests passed, and `assembleDebug` succeeded.
An encrypted backup was saved before applying the migration locally. Post-migration
verification confirmed all 5 tickets, 9 cited offenses, historical evidence photos,
and unaffected encrypted values match the backup. A five-photo submission/read/render
check passed against the actual MySQL schema; fictional test records were rolled back
and temporary evidence files removed. The rebuilt debug APK has not been installed
or tested on a physical phone during this change.

Verified on 12 October 2026 (Asia/Manila). Web root: `C:\xampp\htdocs\Capstone\TomecoApp`. Android root: `C:\Users\Tamano\Desktop\Current Mobile App (Tomeco App)\TOMECO-Traffic-Violation-&-Impounding-System`.

## Implemented contract

- Inspected Android `TomecoApiService.kt`, `ApiModels.kt`, `AuthInterceptor.kt`, `RetrofitClient.kt`, `TomecoRepository.kt`, `SignatureData.kt`, `TypedSignatureData.kt`, and debug/main network security configurations. Retrofit supplies `api/` in each endpoint. Its interceptor supplies Bearer authentication and JSON Accept; errors read Laravel's `message`. Profile accepts the `data` envelope; ticket creation reads `data.enforcer_signature`. Attendance recognizes `timed_in` / `timed_out` and nullable ISO timestamps.
- Login retains officer-only mobile access, accepts username/email in `login`, and blocks inactive/suspended/banned accounts. Profile includes the username, address, area and supervisor assignment needed for restored Android sessions. Logout deletes the current Sanctum token. API errors use JSON even without an Accept header.
- All user-management endpoints now require authentication. Admins create/update/delete accounts; supervisors can read their own officers; officers can read their own account but cannot list accounts or elevate roles. The self-service signature endpoint remains separate. Unsupported retired driver/barangay fields are ignored; area assignment stays in its established workflow. Required database phone numbers now receive validation errors instead of database exceptions.
- Officer attendance/location authorization and schedule rules remain intact. Existing tests exercise time-in, status, time-out, coordinates, supervisor assignment, two duty sessions, location sharing and rejection cases. Catalog and normalized offense tests remain part of the full suite.
- Ticket creation accepts Android's exact registration field names: `or_number`, `cr_number`, `registration_plate_number`, `vehicle_make`, `vehicle_series`, `vehicle_body_type`, `vehicle_year_model`, `registered_owner_name`, `registered_owner_address`. `plate_number` remains the observed plate. Missing fields stay null. Registered-owner identity is never copied into driver identity. Vehicle descriptions accept 1,000 characters; the normalized database column is TEXT.
- Multipart `evidence_images[]` supplies the authoritative ordered 1–5 photo set. Android's accompanying first-photo `evidence_image` is validated but is not saved again. Older single-file and single-string submissions remain supported. JPG/PNG/WebP uploads have an 8,192 KiB maximum. New uploads are individually encrypted with the existing APP_KEY in `storage/app/private/ticket-evidence`; encrypted references are stored on the ticket. Image delivery requires authentication and ticket access. Legacy embedded encrypted database images remain readable. Backups must include both the database and private evidence directory.
- Numeric `SIG_VECTOR_V1` geometry is converted to a generated SVG image for display, with dimension/coordinate bounds and no stored markup injection. Typed PNG/data-URI and legacy base64 signatures remain supported. Issuing officer ID/name and signature come from the authenticated officer and are copied onto the ticket. Ticket rendering uses the snapshot, including after profile signature changes. Missing historical snapshots are honestly shown as unrecorded.
- Ticket pages show every ordered photo, separate observed/document plates, full registration values, a separate registered-owner card, and both signature panels. Narrow navigation starts closed and opens over the record. Hidden sidebar navigation is inert for keyboard users.

## Safe schema and runtime changes

`2026_10_12_000300_preserve_mobile_registration_and_evidence` adds nullable encrypted registration/evidence columns to the actual `Ticket_issued` table and widens `vehicle_type`. It is intentionally forward-only so rollback cannot discard new document/evidence data. No historical ciphertext is rewritten.

Before applying the migration, an encrypted ticket/schema/catalog backup was saved in `storage/app/private/ticket-backups`. After migration and verification, all 3 original tickets and 7 issued offenses remain. Original IDs, officer links, timestamps, fines, statuses and every encrypted value match the backup. No `migrate:fresh`, key regeneration, credential reset, or database-file deletion was performed.

The project already had a valid `.env` at inspection; an undotted `env` file was absent. Its existing APP_KEY and database credentials were retained. The configured database is `tomecodb` at `127.0.0.1:3306`. The configured credentials successfully connected. The reported historical 1045 could not be reproduced and no credentials were changed.

Initial process inspection identified:

| Listener | Process | Finding |
|---|---|---|
| 3306 | XAMPP `mysqld.exe` PID 7688, standalone, `C:\xampp\mysql\bin\my.ini` | Existing database listener; launching another MySQL instance conflicts. No second MySQL service was found. |
| 80 / 443 | XAMPP Apache parent PID 13576, child 26164 | Apache was already running; configuration syntax passed. |
| 8000 | Laravel serve PID 10360, request child 17736 | Application server at the current LAN address. |
| 8080 | Reverb PID 6376 | Existing WebSocket server, retained. |

Apache logs show abrupt parent-process termination (`AH02538`), an unclean/stale PID warning and a development certificate hostname warning. There was no current Apache bind conflict or syntax error. The original reason its parent exited is not recoverable from these logs alone; the certificate warning is not a startup failure. Apache was restarted to load PHP settings and successfully served requests afterward (parent 24180, child 22240).

MySQL logs contain pre-existing InnoDB page/log sequence inconsistency errors. No recovery mode or data-file intervention was attempted. MySQL was not restarted. Its 1 MiB packet limit was increased to 128 MiB in the running server and `my.ini`, preserving a private configuration backup. The private-file evidence implementation avoids huge database/JSON blobs; older embedded-image clients still benefit from the packet allowance.

`C:\xampp\php\php.ini` was backed up in `storage/app/private/php.ini.before-mobile-uploads` and configured with `upload_max_filesize=8M`, `post_max_size=64M`, `max_file_uploads=20`, `memory_limit=512M`. Apache and Laravel's request processes were restarted. Actual requests to both SAPIs accepted six file parts of exactly 8,388,608 bytes each. The temporary diagnostic endpoint was removed.

Current Wi-Fi IPv4: **192.168.5.178**. Android `.env` now sets **`API_BASE_URL=http://192.168.5.178:8000/`**, without `/api`. The generated debug BuildConfig confirms this URL. The debug APK was rebuilt. The machine-specific SDK path in `local.properties` was corrected and escaped; a stray `z` causing Kotlin compilation failure was removed from `ViolationEncodingScreen.kt`. The emulator example URL now has a trailing slash. Debug cleartext HTTP remains supported; release builds still require HTTPS in Retrofit and the release network security policy. A trusted production HTTPS endpoint has not been configured or tested here.

## Automated verification

- Full Laravel suite: **125 passed, 920 assertions**, including new multipart, registration encryption, evidence order/count/size/type, private image authorization, signature rendering/snapshot, account-status, bearer logout, user permissions, and an explicit guard against unsafe migration rollback.
- PHP Pint on changed PHP files: passed.
- `npm run build`: passed (61 modules, production Vite output).
- Strict premium UI audit: passed with zero findings.
- `php scripts/inspect_ticket_database.php --summary --verify`: original records/ciphertext preserved.
- `php scripts/check_ticket_views.php`: existing analytics, ticket list, complete ticket and motorist history rendered successfully.
- `php scripts/verify_mobile_ticket_mysql.php --max-size`: actual MySQL insertion/read/render using 5 ordered photos at 8 MiB each; registration encrypted and signature snapshot preserved. All fictional records/audits are rolled back and test evidence removed in cleanup. This verification calls the ticket controller within a transaction; Laravel HTTP routing/authentication are covered separately by feature tests.
- `node scripts/verify_mobile_upload_process.cjs`: real HTTP multipart parsing in Laravel CLI server and Apache, six 8 MiB parts accepted with 8M/64M/20 effective settings. Creates a temporary probe and removes it in `finally`.
- `php scripts/verify_mobile_ticket_mysql.php` followed by `node scripts/verify_mobile_browser.cjs`: real Edge browser verification of the rendered fictional ticket at 1440px and 390px. Checks readable content width, wrapping, five decoded evidence images, two decoded signatures, navigation toggle and keyboard focus. Screenshots/results are under `storage/app/private/mobile-verification`. Screenshot inspection caught and fixed a sidebar/content-width issue that a root overflow check alone missed.
- Android unit tests: **133 passed, zero failures/errors/skips**. Debug APK assembly completed. The compiled URL is `http://192.168.5.178:8000/`.

Android final aggregate verification (`testDebugUnitTest`, `lintDebug`, `assembleDebug`) succeeded. Lint: **0 errors, 37 warnings**. A forced lint cache refresh cleared the outdated SDK-property diagnostic; the final aggregate run reused the successful tests and rebuilt APK outputs.

These are automated host/browser checks. **No physical Android phone test or APK installation on a phone was performed.** Device Wi-Fi reachability, firewall access from the phone, camera/OCR capture and release HTTPS remain physical/deployment checks. The missing `google-services.json` warning is allowed by the project's existing Google Services configuration; Firebase-dependent features were not verified by these Laravel integration checks.

## Startup / repeat verification

The application server is currently running. Do not launch duplicate listeners. From the web project, when it is stopped:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Run those in separate terminals. With the completed production build, Vite's development server is optional. Open `http://192.168.5.178:8000`. MySQL is already listening on 3306; Apache is optional when using Laravel serve. Start stopped XAMPP modules through its control panel only after checking the corresponding listener. Do not restart MySQL as part of this task given its recorded InnoDB warnings.

From Android, with Android Studio's JBR configured as JAVA_HOME:

```powershell
.\gradlew.bat :app:testDebugUnitTest :app:lintDebug :app:assembleDebug --no-configuration-cache
```

The APK is `app\build\outputs\apk\debug\app-debug.apk`. Install this rebuilt debug APK for the new LAN URL. Use a trusted HTTPS root and rebuild for release deployment.

## Changed files

Web:

- `routes/api.php`, `routes/web.php`, `bootstrap/app.php`
- `app/Http/Controllers/Api/AuthController.php`, `UserController.php`, `ViolationController.php`
- `app/Http/Controllers/TicketEvidenceController.php`
- `app/Http/Middleware/EnsureApiAccountActive.php`
- `app/Models/Violation.php`, `app/Support/SignatureImage.php`
- `app/Services/TicketEvidence.php`, `app/Services/AuditLogger.php`
- `database/migrations/2026_10_12_000300_preserve_mobile_registration_and_evidence.php`
- `resources/views/dashboard/violation-ticket-show.blade.php`, `violation-record-show.blade.php`
- `resources/views/layouts/admin-dashboard.blade.php`, `supervisor-dashboard.blade.php`
- `public/css/dashboard.css`, production `public/build` output
- `tests/Feature/Api/MobileContractTest.php`, `UserApiTest.php`, `ProfileTest.php`, `UserManagementTest.php`, `UserAttendanceRestrictionsTest.php` (stale expectations reconciled with removed barangay fields, officer website restrictions and role filters)
- `scripts/mobile_runtime_status.php`, `verify_mobile_ticket_mysql.php`, `verify_mobile_browser.cjs`, `verify_mobile_upload_process.cjs`
- `DESIGN.md`, `UX-CONTRACT.md`, `docs/starting-the-three-servers.md`, this report

Android:

- `.env` (URL only; credentials preserved), `.env.example` (trailing slash), `local.properties` (local SDK path)
- `app/src/main/java/com/example/tomeco/ui/screens/ViolationEncodingScreen.kt` (compilation typo)
- Rebuilt debug APK and generated build outputs

Runtime configuration: `C:\xampp\php\php.ini`, `C:\xampp\mysql\bin\my.ini` (private copies saved before modification).
