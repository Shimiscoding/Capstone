# TOMECO mock defense

## Start the isolated Laravel server

Use PowerShell with PHP 8.2+, `pdo_sqlite`, `gd`, Composer, and Node.js available. XAMPP’s Apache and MySQL can stay stopped for this walkthrough. The existing Laravel migrations populate the violation catalog. `MockDefenseSeeder` checks the database path, creates synthetic users, and prepares today's officer duty session so citation issuance can run without a live GPS service.

```powershell
Set-Location -LiteralPath 'C:\xampp\htdocs\Capstone\TomecoApp'
composer install
npm.cmd ci
npm.cmd run build
. .\scripts\Set-MockDefenseEnvironment.ps1
php artisan migrate --env=mock-defense
php artisan db:seed --class=MockDefenseSeeder --env=mock-defense
php artisan serve --env=mock-defense --no-reload --host=127.0.0.1 --port=8000 --tries=1
```

Run dependency installation when the dependencies are missing or the lockfiles change. Keep this terminal open and visit [the demo login](http://127.0.0.1:8000/login). This walkthrough uses built assets with no separate Vite or Reverb process. The setup script generates an independent key and `.env.mock-defense`. The database, logs, sessions and private attachments are in `storage\mock-defense`. Repeat migration and seeding safely in the same database. Use a separate PowerShell window for normal application commands or automated tests.

For a physical phone on the same private Wi-Fi network, use `--host=0.0.0.0` in the server command. Use the Windows Wi-Fi adapter’s IPv4 address from `ipconfig`. Keep the server running only during the demo and use synthetic data. For this machine the observed address was `192.168.1.9`, which may change on reconnect.

## Build and install Android debug

In another PowerShell window, ensure Android Studio’s `local.properties` uses the SDK installed on this computer. On this Windows account the `sdk.dir` line is `sdk.dir=C:/Users/Tamano/AppData/Local/Android/Sdk`. Preserve other local settings.

```powershell
Set-Location -LiteralPath 'C:\Users\Tamano\Desktop\Current Mobile App (Tomeco App)\TOMECO-Traffic-Violation-&-Impounding-System'
$env:JAVA_HOME = 'C:\Program Files\Android\Android Studio\jbr'
$env:ANDROID_HOME = 'C:\Users\Tamano\AppData\Local\Android\Sdk'
$env:API_BASE_URL = 'http://10.0.2.2:8000/'
# For a physical phone, replace the previous line with your current Wi-Fi address:
# $env:API_BASE_URL = 'http://192.168.1.9:8000/'
.\gradlew.bat :app:testDebugUnitTest :app:assembleDebug --no-parallel --max-workers=1 --no-configuration-cache
$demoAdb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
& $demoAdb devices -l
& $demoAdb -s emulator-5554 install -r '.\app\build\outputs\apk\debug\app-debug.apk'
```

Start `Medium_Phone` from Android Studio Device Manager if needed. Use the serial from `adb devices -l` for the chosen emulator or USB-debugging phone. Use a dedicated emulator or test device. The base URL ends with `/` because Retrofit adds `api/` to its endpoint paths. Rebuild and reinstall after changing it.

To set a simulated GPS fix for attendance on `Medium_Phone`, run the following after it boots. A phone uses its own location service.

```powershell
& $demoAdb -s emulator-5554 emu geo fix 125.006 11.245
& $demoAdb -s emulator-5554 shell cmd alarm set-timezone Asia/Manila
```

Android ticket receipts use the device timezone. The emulator command makes their displayed issue time match the dashboard's Manila time.

From a fresh Laravel PowerShell window, run `php artisan test` for the automated suite. The repo’s `phpunit.xml` uses an in-memory SQLite database.

## Five-minute walkthrough

| Time | Role and screen | Action and expected result |
| --- | --- | --- |
| 0:00 | Officer · Android Sign In and Home | Sign in as `demo.officer` with `TomecoDemo!2026`. Home shows the seeded officer on duty and enables Issue Violation Ticket. |
| 0:45 | Officer · Android Issue Citation | Enter `Jamie Demo Driver`, `112 Demo Lane`, permit expiry `2030/12/31`, observed plate `DEMO123`, document plate `DOC124`, and `Toyota Avanza`. Choose Illegal Parking (section 7) and enter `Demo Intersection`. |
| 2:00 | Officer · Android Terms and Review | Capture a synthetic photo with the emulator camera or photograph a printed synthetic fixture on a test phone. Draw and save a driver signature. Review the fields, issue the citation and copy the displayed `TMC-…` ticket number. |
| 3:15 | Admin · Web Violation Records | Sign in as `demo.admin` with `TomecoDemo!2026`. Search Violation Records for the exact ticket number, choose the matching row and compare the driver, plate, offense, evidence and signature. |
| 4:15 | Officer · Android History | Return to History and refresh. Search for the same ticket and open its details to show that it persists. |
| 4:45 | Admin · Web ticket details | Switch to a 390 px browser viewport and reopen the same details. Verify the ticket number and attachments remain readable. |

The dashboard review uses the existing admin role. The cashier workflow is documented as read-only. Impoundment, release, and payment/status changes require a defined workflow before they can be demonstrated. Open requirements include which role can authorize each transition, what custody and release fields are required, and whether payment or other clearance is a release prerequisite.

## Verified run

On 11 November 2026 (Asia/Manila), Android issued `TMC-04DF7DD11411` with one emulator-camera image and a drawn driver signature. The same ticket opened from Android History and from Violation Records in Edge at 1440 and 390 pixels. The demo record is at `/dashboard/violation-records/2` in the database from this run.

The live API checks used a separate synthetic ticket. A missing declared attachment returned `422`, a valid submission returned `201`, and its exact replay returned `200` with the original row ID. The Android form rejected a malformed expiry. A guest could not open the evidence and an admin account could not sign in through the officer mobile endpoint.

Automated checks passed on PHP 8.2.12 and Android Studio's JDK. The full Laravel SQLite suite ran 184 tests, the focused suite ran 22 tests on a clean MariaDB 10.4.32 instance, and Android ran 287 unit tests and built the debug APK. Vite built the web assets. Device coverage was the emulator. Browser coverage was Edge at desktop and phone widths.
