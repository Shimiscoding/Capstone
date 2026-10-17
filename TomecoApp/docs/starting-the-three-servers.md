# Starting the Three Development Servers

The Tomeco application needs three servers while developing:

1. **Laravel** serves the application on port `8000`.
2. **Vite** compiles the frontend assets and provides hot reload on port `5173`.
3. **Laravel Reverb** provides real-time WebSocket updates on port `8080`.

## Before starting

1. Open the **XAMPP Control Panel** and start **MySQL**.
2. Open three PowerShell or Command Prompt windows.
3. In each window, go to the project directory:

   ```powershell
   cd C:\xampp\htdocs\Capstone\TomecoApp
   ```

## Terminal 1: Laravel application server

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

## Terminal 2: Vite frontend server

```powershell
npm run dev
```

## Terminal 3: Reverb WebSocket server

```powershell
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Keep all three terminal windows open while using the application. Open the URL configured as `APP_URL` in the `.env` file; the current development URL is:

```text
http://192.168.5.178:8000
```

If the computer's local IP address changes, update `APP_URL`, `REVERB_HOST`, and the host/origin values in `vite.config.js`, then restart all three servers.

Android's `.env` must set `API_BASE_URL=http://192.168.5.178:8000/` for this LAN debug setup. Use the server root with a trailing slash; Retrofit endpoints already contain `api/`. Rebuild and reinstall the debug APK after changing the URL. Release builds require a trusted HTTPS server root and retain their cleartext restrictions.

For built production assets, `npm run build` replaces the Vite development server. Keep Laravel and Reverb running. Before starting XAMPP modules, check existing listeners: this machine already had XAMPP MySQL on 3306 and Apache on 80/443. Starting a second instance creates a port conflict. See `mobile-integration-verification.md` for the inspected processes, upload settings, safe migration, and test evidence.

To stop a server, select its terminal and press `Ctrl+C`.

## Quick check

The terminal output should show these ports:

- Laravel: `8000`
- Vite: `5173`
- Reverb: `8080`

If a command reports that its port is already in use, stop the old process using that port before starting the server again.
