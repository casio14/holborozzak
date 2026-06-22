<?php
// IDEIGLENES logó-előnézet. Élesítés előtt törölni. (noindex)
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Logó-előnézet (A finomítva) — holborozzak.hu</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body { background: var(--cream); }
    .wrap { max-width: 980px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    h1 { color: var(--wine-700); }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-top: 1.5rem; }
    .card { background: var(--paper); border: 1px solid var(--line); border-radius: 14px; padding: 1.5rem; box-shadow: 0 6px 24px rgba(74,14,28,.07); }
    .card h2 { margin: 0 0 1rem; font-size: 1.05rem; color: var(--wine-900); }
    .big { display: grid; place-items: center; height: 120px; }
    .big svg { width: 96px; height: 96px; }
    /* sötét háttéren is nézzük (mint a hero) */
    .ondark { background: #4a0e1c; border-radius: 10px; margin-top: .75rem; display: grid; place-items: center; height: 80px; }
    .ondark svg { width: 56px; height: 56px; }
    .lockup { display: flex; align-items: center; gap: .5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--line); }
    .lockup svg { width: 32px; height: 32px; }
    .lockup .wm { font-family: Georgia, serif; font-weight: 700; font-size: 1.5rem; color: var(--wine-700); }
    .lockup .wm b { color: var(--wine-900); }
    .note { color: var(--muted); font-size: .9rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>A koncepció — finomított pohár</h1>
    <p class="note">A térkép-tű marad, csak a pohár lett letisztultabb. Melyik tetszik (A1/A2/A3)? Színt/vastagságot még hangolhatunk.</p>

    <div class="grid">

      <!-- A1: vonalas arany pohár -->
      <div class="card">
        <h2>A1 — Vonalas arany pohár</h2>
        <div class="big">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#722f37"/>
            <g fill="none" stroke="#ecd9a8" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12.7 6.4H19.3C19.1 9.9 17.7 11.7 16 11.9 14.3 11.7 12.9 9.9 12.7 6.4Z"/>
              <path d="M16 11.9V15.4"/>
              <path d="M13.7 15.7H18.3"/>
            </g>
          </svg>
        </div>
        <div class="ondark">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#9b4753"/>
            <g fill="none" stroke="#ecd9a8" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12.7 6.4H19.3C19.1 9.9 17.7 11.7 16 11.9 14.3 11.7 12.9 9.9 12.7 6.4Z"/>
              <path d="M16 11.9V15.4"/>
              <path d="M13.7 15.7H18.3"/>
            </g>
          </svg>
        </div>
        <div class="lockup">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#722f37"/>
            <g fill="none" stroke="#ecd9a8" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12.7 6.4H19.3C19.1 9.9 17.7 11.7 16 11.9 14.3 11.7 12.9 9.9 12.7 6.4Z"/>
              <path d="M16 11.9V15.4"/>
              <path d="M13.7 15.7H18.3"/>
            </g>
          </svg>
          <span class="wm">hol<b>borozzak</b>.hu</span>
        </div>
      </div>

      <!-- A2: tömör krém pohár -->
      <div class="card">
        <h2>A2 — Tömör krém pohár</h2>
        <div class="big">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#722f37"/>
            <g fill="#f3e7d0">
              <path d="M12.7 6.2H19.3C19.3 9.9 17.8 11.6 16 11.9 14.2 11.6 12.7 9.9 12.7 6.2Z"/>
              <rect x="15.45" y="11.6" width="1.1" height="4"/>
              <rect x="13.5" y="15.4" width="5" height="1.1" rx=".55"/>
            </g>
          </svg>
        </div>
        <div class="ondark">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#9b4753"/>
            <g fill="#f3e7d0">
              <path d="M12.7 6.2H19.3C19.3 9.9 17.8 11.6 16 11.9 14.2 11.6 12.7 9.9 12.7 6.2Z"/>
              <rect x="15.45" y="11.6" width="1.1" height="4"/>
              <rect x="13.5" y="15.4" width="5" height="1.1" rx=".55"/>
            </g>
          </svg>
        </div>
        <div class="lockup">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#722f37"/>
            <g fill="#f3e7d0">
              <path d="M12.7 6.2H19.3C19.3 9.9 17.8 11.6 16 11.9 14.2 11.6 12.7 9.9 12.7 6.2Z"/>
              <rect x="15.45" y="11.6" width="1.1" height="4"/>
              <rect x="13.5" y="15.4" width="5" height="1.1" rx=".55"/>
            </g>
          </svg>
          <span class="wm">hol<b>borozzak</b>.hu</span>
        </div>
      </div>

      <!-- A3: vonalas pohár + arany bor -->
      <div class="card">
        <h2>A3 — Vonalas pohár arany borral</h2>
        <div class="big">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#722f37"/>
            <path fill="#c8a14b" d="M13.5 8.4C14 10.1 14.9 11.2 16 11.4 17.1 11.2 18 10.1 18.5 8.4Z"/>
            <g fill="none" stroke="#ecd9a8" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12.7 6.4H19.3C19.1 9.9 17.7 11.7 16 11.9 14.3 11.7 12.9 9.9 12.7 6.4Z"/>
              <path d="M16 11.9V15.4"/>
              <path d="M13.7 15.7H18.3"/>
            </g>
          </svg>
        </div>
        <div class="ondark">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#9b4753"/>
            <path fill="#c8a14b" d="M13.5 8.4C14 10.1 14.9 11.2 16 11.4 17.1 11.2 18 10.1 18.5 8.4Z"/>
            <g fill="none" stroke="#ecd9a8" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12.7 6.4H19.3C19.1 9.9 17.7 11.7 16 11.9 14.3 11.7 12.9 9.9 12.7 6.4Z"/>
              <path d="M16 11.9V15.4"/>
              <path d="M13.7 15.7H18.3"/>
            </g>
          </svg>
        </div>
        <div class="lockup">
          <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16 3C10.8 3 6.5 7.1 6.5 12.3 6.5 18.6 16 28.5 16 28.5S25.5 18.6 25.5 12.3C25.5 7.1 21.2 3 16 3Z" fill="#722f37"/>
            <path fill="#c8a14b" d="M13.5 8.4C14 10.1 14.9 11.2 16 11.4 17.1 11.2 18 10.1 18.5 8.4Z"/>
            <g fill="none" stroke="#ecd9a8" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12.7 6.4H19.3C19.1 9.9 17.7 11.7 16 11.9 14.3 11.7 12.9 9.9 12.7 6.4Z"/>
              <path d="M16 11.9V15.4"/>
              <path d="M13.7 15.7H18.3"/>
            </g>
          </svg>
          <span class="wm">hol<b>borozzak</b>.hu</span>
        </div>
      </div>

    </div>
  </div>
</body>
</html>
