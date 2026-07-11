<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#b42318">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
     <link rel="stylesheet" href="{{ asset('css/mobile.css') }}">

    <title>TOMECO Mobile</title>

    <link rel="manifest" href="/manifest.json">
    <link rel="icon" sizes="192x192" href="/pwa/icons/android/launchericon-192x192.png">
    <link rel="apple-touch-icon" href="/pwa/icons/ios/180.png">
   
</head>
<body>
    <main class="mobile-shell">
        <section class="hero-panel" aria-labelledby="app-title">
            <header class="app-header">
                <div class="brand-lockup">
                    <div class="brand-mark" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div>
                        <p class="brand-kicker">Tacloban Traffic Office</p>
                        <h1 id="app-title">TOMECO</h1>
                    </div>
                </div>

                @if (Route::has('login'))
                    <a class="ghost-link" href="{{ route('mobile.login') }}">Log in</a>
                @endif
            </header>

            <div class="hero-copy">
                <p class="eyebrow">Mobile enforcement system</p>
                <h2>Issue tickets, capture evidence, and track impounded vehicles in the field.</h2>
                <p>
                    A mobile-first foundation for traffic violation records, QR-coded tickets,
                    payment status, and officer reporting.
                </p>
            </div>

            <div class="primary-actions" aria-label="Primary actions">
                @if (Route::has('mobile.login'))
                    <a class="primary-button" href="{{ route('mobile.login') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <path d="m10 17 5-5-5-5"/>
                            <path d="M15 12H3"/>
                        </svg>
                        Continue to login
                    </a>
                @endif

            </div>
        </section>

        <section class="quick-panel" aria-label="Mobile modules">
            <div class="status-strip">
                <div>
                    <span class="status-dot"></span>
                    <p>Ready for field encoding</p>
                </div>
                <strong>{{ now()->format('h:i A') }}</strong>
            </div>

            <div class="module-grid">
                <article class="module-card">
                    <div class="module-icon scan">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7V5a1 1 0 0 1 1-1h2"/>
                            <path d="M17 4h2a1 1 0 0 1 1 1v2"/>
                            <path d="M20 17v2a1 1 0 0 1-1 1h-2"/>
                            <path d="M7 20H5a1 1 0 0 1-1-1v-2"/>
                            <path d="M7 12h10"/>
                        </svg>
                    </div>
                    <h3>Plate Scan</h3>
                    <p>Prepare OCR capture for vehicle plates.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon ticket">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a3 3 0 0 0 0 6v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a3 3 0 0 0 0-6Z"/>
                            <path d="M9 8h6"/>
                            <path d="M9 12h6"/>
                            <path d="M9 16h4"/>
                        </svg>
                    </div>
                    <h3>Issue Ticket</h3>
                    <p>Encode violation details and generate QR tickets.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon camera">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14.5 4 16 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3l1.5-3Z"/>
                            <circle cx="12" cy="13" r="3"/>
                        </svg>
                    </div>
                    <h3>Evidence</h3>
                    <p>Attach vehicle, plate, and incident photos.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon impound">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 13h18"/>
                            <path d="M5 13 7 6h10l2 7"/>
                            <circle cx="7.5" cy="17.5" r="1.5"/>
                            <circle cx="16.5" cy="17.5" r="1.5"/>
                        </svg>
                    </div>
                    <h3>Impound</h3>
                    <p>Record custody, location, and release status.</p>
                </article>
            </div>
        </section>
    </main>
</body>
</html>
