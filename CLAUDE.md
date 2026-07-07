# holborozzak.hu

## Mi ez a projekt?

Egy weboldal, amely összegyűjti és felsorolja a **magyarországi borhoz köthető
eseményeket** (borfesztiválok, bornapok, szüreti rendezvények stb.).

Példák az eseményekre:
- Budapesti Bor Napok
- Szent György-hegy Hajnalig
- (és további magyar boros rendezvények)

A weboldal címe: **holborozzak.hu**

## Cél és terjedelem

- A fő funkció egyszerű: **az események felsorolása** (lista/áttekintés).
- Nincs szükség bonyolult funkciókra (regisztráció, fizetés stb.) — a hangsúly
  a rendezvények áttekinthető megjelenítésén van.

## Design / megjelenés

- **Borhoz köthető színek** használata (pl. mély bordó/burgundi vörös, szőlőlevél-zöld,
  arany/aranysárga, krém/pergamen háttér).
- Magyar nyelvű felület.

## Technikai megjegyzések

- **Adattárolás:** MySQL adatbázis (saját szerveren). Az események adatait
  adatbázisban tároljuk, nem statikus fájlban.
- **Tech stack:** PHP (szerveroldali renderelés) + MySQL adatbázis.
  Frontend: sima HTML/CSS (borhoz köthető színek). Nincs build lépés.
- **Webszerver (Rackhost, FTP):**
  - Kiszolgáló: `wh28.rackhost.hu`
  - Felhasználónév: `c105746ptrk`
  - Célkönyvtár (deploy ide): `/web/holborozzak.hu/` (a `holborozzak.hu` document rootja)
  - Jelszó: **GitHub repository secret**-ben (`FTP_PASSWORD`), NEM a kódban.
- **GitHub repo:** `git@github.com:casio14/holborozzak.git` (korábban `borozzak` néven)
- **Deploy:** GitHub Actions (`.github/workflows/deploy.yml`) → `main`-re
  pusholáskor a `public/` mappa tartalmát felmásolja a webszerverre
  (`SamKirkland/FTP-Deploy-Action`, csak a változott fájlok).
  - **Protokoll: sima `ftp`** — a Rackhost FTP szervere NEM támogatja az FTPS-t
    (`AUTH TLS` → `500`). A jelszó így titkosítatlanul utazik (lásd biztonsági TODO).
  - **Az FTP-login a `holborozzak.hu` docrootjába (chroot) érkezik**, ezért a
    `server-dir` a gyökér: `./`. Abszolút `/web/holborozzak.hu/` egy felesleges
    `web/holborozzak.hu/` alkönyvtárba tenné a fájlokat.
  - **Cím:** https://holborozzak.hu/
  - **Verziózás: szemantikus, git tag-es.** A `VERSION` fájl tartja a
    `major.minor`-t (pl. `1.0`); a patch automatikusan a meglévő tagek alapján +1.
    Minden sikeres deploy `vX.Y.Z` git taget hoz létre, és a verzió megjelenik
    az oldal láblécében (a CI által generált `public/version.php`-ból).
  - **Major/minor léptetés:** kézzel írd át a `VERSION` fájlt (a patch onnantól 0-ról indul).
  - **Rollback / adott verzió:** Actions → Run workflow → a "Use workflow from"
    legördülőből válaszd a kívánt tag-et; ilyenkor nem készül új tag, csak újra deployol.
  - **TODO (biztonság):** ha a Rackhost ad SFTP/SSH-t, váltani titkosított feltöltésre.
- **Adatbázis (MySQL, Rackhost):**
  - Kiszolgáló: `mysql.rackhost.hu`
  - Adatbázis neve: `c105746holborozzak`
  - Felhasználónév: `c105746patrik`
  - Jelszó: **GitHub repository secret**-ben (`DB_PASSWORD`), NEM a kódban.
  - Port: 3306 (alapértelmezett, ellenőrizni)
  - **Kívülről is elérhető** → migrációkat futtathatunk helyi gépről / CI-ből is.
- **Fejlesztési mód:** inkrementálisan haladunk, kis lépésekben.

## Funkciók (frontend)

- Letisztult, modern lista (Eventbrite-szerű kártyák).
- Kiemelt események (`is_featured`).
- Tabok: **Közelgő**, **Kiemelt**, **E hétvégén**, **E hónapban**, **Ingyenes**.
  (Ezek lekérdezések a dátum/jelölő mezőkre, nem külön adatok.)
- Térképes megjelenítés (`latitude`/`longitude`).

## SEO & AI-kereső (GEO) — KIEMELT CÉL

Erős keresőoptimalizálás **és** AI-ajánlás-barátság (ChatGPT, Perplexity, Google AI
Overviews) kiemelt projektcél. Minden új oldalnál tartsd be a `docs/seo-geo.md`
checklistet: szerveroldali HTML, szemantikus markup (`<time datetime>`), oldalankénti
egyedi title/description/canonical, slug-URL-ek, **Schema.org JSON-LD** (eseménynél
`Event`, listán `ItemList`, + `BreadcrumbList`), Open Graph/Twitter (alap `og:image` a hero),
**dinamikus `sitemap.php`** (DB-ből, csak published; a `robots.txt` ide hivatkozik), `llms.txt`
(GEO áttekintés), egyedi **`404.php`** (`.htaccess` ErrorDocument), és AI-crawlereket engedő `robots.txt`. A `partials/header.php` már tartalmazza a meta/canonical/OG/
JSON-LD vázat (alap `WebSite`+`Organization`); `$jsonLd`-vel bővíthető oldalanként.

## Projekt szerkezet

- `public/` — **a deployolt weboldal** (csak ez kerül a webszerverre). PHP + HTML/CSS.
  - `db.php` — PDO MySQL kapcsolat (`db()` függvény, singleton). A configot a
    `config.php`-ból olvassa.
  - `config.php` — **generált**, NEM gitben: éles környezetben a CI hozza létre a
    `DB_PASSWORD` secretből; lokálisan a `config.example.php`-ból másolod.
  - `version.php` — generált verziófájl (CI).
  - `terkep.php` — **Eseménytérkép**: Leaflet + CARTO világos csempék, **szőlőfürt
    jelölők darabszám-jelvénnyel**, **markercluster** (zoom-alapú összevonás/szétválás),
    popup részletlinkkel. SEO: szerveroldali lista + `Event`/`ItemList` JSON-LD.
  - `naptar.php` — **Eseménynaptár**: havi naptárrács (hét-első nézet), eseményekkel a
    napjukon, hónaplépegetéssel (`?ev=&ho=`). A Naptár menüpont ide mutat.
  - `esemeny.php` — esemény **részletoldal**, szép URL-lel: **`/esemeny/<slug>`**
    (`.htaccess` rewrite; a régi `esemeny.php?slug=…` 301-gyel ide irányít; a
    `partials/header.php` `<base>` tagje miatt a relatív linkek a mélyebb útvonalon is
    működnek — új oldalaknál is relatív linkeket használj, a base megoldja). Teljes `Event`
    JSON-LD, canonical, OG-kép; 404 a nem létezőre. A kártyák/sorok/térkép ide linkelnek.
    Elrendezés:
    **hero** (borító + tömör borvörös címsáv: cím, dátum, státusz), alatta reszponzív rács
    (`.ed-grid`, CSS grid-areas): balra leírás, jobb oldalsávban infó (hol/borvidék/ár) +
    gombok (Jegyek/Hivatalos oldal a `go.php`-n át; **Facebook**; **Naptárhoz adom** →
    `ics.php`), és **kis Leaflet-térkép** ha van `latitude`/`longitude` (+ Google Maps link).
    **Reszponzív térkép-hely:** desktopon az oldalsávban, mobilon a leírás alatt (egyetlen
    térkép-elem, grid-areas átrendezéssel).
  - `ics.php` — esemény `.ics` (naptárhoz adás) `ics.php?e=<id>`; csak közzétett. `Disallow` robotsban.
  - `assets/app.js` — progresszív fejlesztés (részleges szűrés, no-jump).
  - `lib/events.php` — esemény-lekérdezések + megjelenítési segédfüggvények
    (magyar dátumformázás, státusz-pirula, hónap-csoportosítás, `h()` escape).
  - **`index.php` = nyitóoldal (landing):** hero+kereső → intro+statisztika → Kiemelt →
    Közelgő előnézet → „Böngéssz másképp" csempék → Szervezőknek CTA (két gomb:
    esemény beküldése → `esemeny-bekuldes.php`, ill. kiemelés iránti mailto)
    → Hírlevél. Kereső a `lib/events.php` `searchEvents()`-tel (ékezet-érzéketlen).
  - **`newsletter.php`** — hírlevél feliratkozás (POST→PRG); a `subscribers` táblát
    futásidőben is létrehozza (`lib/subscribers.php`), leiratkozó tokent generál, és
    ÚJ feliratkozónak **üdvözlő e-mailt** küld (`lib/mail.php` + `lib/newsletter.php`).
  - **`leiratkozas.php`** — hírlevél-leiratkozás: tokenes link (`?t=`, megerősítő
    gombbal — a levelező-előolvasók GET-je nem töröl) vagy e-mail címes űrlap
    (semleges válasz). `noindex` + robots `Disallow`.
  - **`newsletter-send.php`** — token-védett hírlevél-küldő végpont
    (`NEWSLETTER_TOKEN` secret ↔ config `newsletter_token`). A
    `.github/workflows/newsletter.yml` cron HETENTE hívja, de a szerveroldali
    13 napos védelem miatt ténylegesen KÉTHETENTE megy ki levél (`?force=1`
    megkerüli). Tartalma: a következő 3 hét közzétett eseményei (max 12);
    küldés-napló: `newsletter_log` tábla. Feladó: config `mail.from_email`
    (`MAIL_FROM` secret; üresen host-alapú no-reply fallback). Küldés PHP
    `mail()`-lel a Rackhost szerverről.
  - **Rackhost e-mail/HTTP tanulságok (ne felejtsd!):**
    - Az egyéni HTTP-fejléceket (pl. `X-Newsletter-Token`) a Rackhost proxy
      ELDOBJA → a tokent POST-törzsben (is) kell küldeni (collect-ingest és
      newsletter-send is így megy).
    - `mail()`-nél kötelező a `-f` boríték-feladó (Return-Path) a From címmel —
      enélkül DMARC/SPF igazodási hiba → spam (lib/mail.php kezeli).
    - A 998+ karakteres sorokat az MTA kényszerrel tördeli (szó/attribútum
      közepén is) → a sendMailHtml küldés előtt 500 karakternél tördel.
    - **E-mail hitelesítés (DNS, Rackhost DNS-kezelő — nem a repóban):**
      SPF beállítva (`v=spf1 a mx include:_cspf.rackhost.hu ~all`), DMARC felvéve
      (`_dmarc` TXT: `v=DMARC1; p=none; rua=mailto:info@holborozzak.hu; fo=1`).
      A `p=none` megfigyelő mód; ha a jelentések tiszták, később `p=quarantine`.
      **TODO:** DKIM bekapcsolása a Rackhost levelező-panelen (a legjobb Gmail-beérkezéshez).
    - **A `MAIL_FROM` feladó MINDIG @holborozzak.hu legyen!** Idegen domain (pl.
      korábban `hirlevel@kissptrk.hu`) SPF/DMARC-igazodási hibát ad → a levél spambe/eldobásra
      kerül. A címet a GitHub `MAIL_FROM` secret adja, és csak ÚJRADEPLOY után frissül a config.php-ban.
    - **Az üdvözlő e-mail CSAK új feliratkozónak megy** (`newsletter.php`: `rowCount() > 0`) —
      ismételt feliratkozásra szándékosan nincs levél (nem a küldés hibája).
  - **`admin/feliratkozok.php`** — feliratkozó-lista, CSV-export, törlés (CSRF).
  - **`admin/beerkezo.php`** — beérkező e-mailek nézete: az `info@holborozzak.hu` postafiókot
    **IMAP**-on olvassa (csak olvasás, a szerveren semmit nem módosít), listázza az utolsó ~40
    levelet (olvasatlan-jelzéssel), egy levél megnyitva a szöveges törzset mutatja + „Válasz
    e-mailben" mailto. Config: `config.php` `imap` szekció (`host`/`port`/`user`/`pass`); éles:
    `IMAP_PASSWORD` (kötelező) + `IMAP_HOST`/`IMAP_USER`/`IMAP_PORT` secretek (a host alap
    `mail.rackhost.hu` — a Rackhost webmailban ellenőrizd). Ha nincs `ext-imap` vagy nincs
    beállítva, a lap szép fallback-útmutatót mutat. `noindex`.
  - **`esemeny-bekuldes.php`** — nyilvános esemény **beküldő űrlap** (POST→PRG):
    validál, `draft` státuszú eseményt szúr be (slug auto, ütközésmentes), a kiválasztott
    kategóriákat az `event_categories`-be köti, a beküldő nevét/e-mailjét eltárolja
    (`events.submitter_name/email`, NEM publikus; migráció `003`). `noindex`. A beküldött
    esemény jóváhagyásra vár.
  - **`admin/`** — védett admin felület (session-alapú belépés). `auth.php` (session +
    `require_admin()` + CSRF helperek), `login.php`/`logout.php`, `index.php` (jóváhagyásra
    események kezelése). `index.php`: státusz-fülek (Beérkezett/Közzétett/Lemondott) +
    művelet-gombok; `action.php`: státuszváltás (publish/cancel/draft) + kiemelés-kapcsoló
    (POST+CSRF, PRG); `edit.php`: teljes szerkesztő (mezők, állapot, kiemelés, kép,
    kategóriák újraírása) — **kézi esemény-felvétel is**: id nélkül hívva üres űrlap +
    INSERT (a listaoldal „+ Új esemény" gombjáról). A részletes leírás **rich-text
    szerkesztő** (félkövér/dőlt/listák/link), mentéskor `sanitizeRichHtml()` fertőtlenít. `jeloltek.php`: **esemény-jelöltek** (automatikus gyűjtés 1. fázis) —
    „Import URL-ből" (a `lib/ai.php` Claude-hívással kinyeri az esemény adatait egy
    weboldalból), dedup (`lib/candidates.php`), jóváhagyás → `draft` event / elvetés.
    Jelöltek külön táblában (`event_candidates`, migráció `005`), NEM az `events`-ben.
    Anthropic kulcs: `config.php` `anthropic` szekció (`ANTHROPIC_API_KEY` secret; modell
    alap `claude-opus-4-8`, cURL a Messages API-ra). Hitelesítés: `config.php` `admin` szekció (`user` + bcrypt
    `pass_hash`); éles: `ADMIN_USER` + `ADMIN_PASSWORD` secret (a CI bcrypt-eli).
    `noindex` + `robots.txt` `Disallow: /admin/`.
  - **`esemenyek.php` = teljes lista:** tabok + multiselect szűrők (borvidék/kategória) +
    rendezés + hónapokra bontott sor-lista. Az „Események" menü ide mutat. Itt él az
    AJAX-os `#esemenyek-region` (részleges szűrés, `app.js`). `listUrl()` ide mutat.
  - **`borvidekek.php` = Borvidékek áttekintő** (`/borvidekek`, menüpont): mind a 22 magyar
    borvidék csempeként, közelgő esemény-számmal → linkel a borvidék-oldalakra. `ItemList` JSON-LD.
  - **`borvidek.php` = borvidék-oldal**, szép URL: **`/borvidek/<slug>`** (`.htaccess` rewrite;
    a `borvidek.php?slug=…` 301-gyel ide). Immerzív hero (a borvidék `image_url` fotója halvány
    sötét fátyollal, vagy dekoratív szőlőhegy-SVG fallback) + tény-sáv (közelgő esemény / fő
    szőlőfajta / jellemző borok) + a borvidék közelgő eseményei (`event-row`). SEO: egyedi
    title/description/canonical, `ItemList`+`BreadcrumbList` JSON-LD, OG-kép. A borvidék-leírás/
    szőlőfajták statikus adatfájlból: **`lib/regions_info.php`** (slug szerint; NINCS DB-migráció).
    Belső linkelés: az `esemeny.php` a borvidéket ide linkeli. Sitemapban minden borvidék benne van.
  - Közös: kártya (`event-card`) / sor (`event-row`) naptár-dátumkockával, státusz-pirulákkal,
    `ItemList`+`Event` JSON-LD (SEO/AI). Cache-busting: `style.css?v=<filemtime>`.
  - `assets/style.css` — közös stíluslap (boros paletta CSS-változókban).
  - `partials/header.php`, `partials/footer.php` — közös layout váz (minden oldal ezt használja).
    - **TODO (elnapolva):** a logó még nyitott — jelenleg ideiglenes szőlőfürt-SVG van.
      Felmerült irány: „A" koncepció = térkép-tű + borospohár (a „hol borozzak?" játék).
- `scripts/` — CI-ben futó segédscriptek (NEM deployolódik FTP-n). `collect_events.php`:
  napi esemény-gyűjtő — a Claude `web_search` eszközével közelgő magyar borrendezvényeket
  KERES az interneten (nem fix oldalakat néz), dedupál, és `event_candidates`-be ír `new`
  jelöltként. Indítja: `.github/workflows/collect.yml` (napi cron). Env: `DB_PASSWORD`,
  `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `COLLECT_URL`, `COLLECT_TOKEN`. A CI **nem** éri el
  közvetlenül a Rackhost MySQL-t ([2002] connection refused), ezért a találatokat HTTPS-en
  POST-olja a token-védett `public/collect-ingest.php`-ra, és a DB-írás (dedup + insert) ott,
  a szerveren történik. A jóváhagyás kézi (admin → Jelöltek). `import_sources.php`:
  **célzott forrás-importáló** — a web_search-alapú gyűjtő KIEGÉSZÍTÉSE. Egy KURÁLT listányi
  konkrét programoldalt (borvacsora-/kóstoló-helyszínek: Jardinette, Laposa, WineHub, Borbarátok,
  Winelovers Rendezvények + programturizmus borvacsora/borkóstoló kategóriák) tölt le, és
  MINDEGYIKBŐL több közelgő eseményt nyer ki a Claude-dal (csak jövőbeli, konkrét dátumúak),
  majd ugyanarra a `collect-ingest.php`-ra POST-ol. Miért kell: a kis borbár-/étterem-kóstolók
  dátumai gyakran csak a saját programoldalukon vannak fent. Új forrás = egy sor a `$SOURCES`
  tömbben. Indítja: `.github/workflows/import-sources.yml` (heti cron). Env ugyanaz, mint a
  gyűjtőnél (DB nélkül; szöveg-kinyerés Haiku-val).
- `db/` — adatbázis séma (`schema.sql`), `seed.sql` (minta események), migrációk. NEM kerül a webszerverre.
- `docs/` — tervdokumentumok (pl. `adatmodell.md`). NEM kerül a webszerverre.
- `.github/workflows/` — CI/CD (deploy).

## Adatmodell

Részletes terv: `docs/adatmodell.md`. Tényleges séma: `db/schema.sql`.

Táblák:
- **`events`** — fő tábla (cím, slug, rövid+hosszú leírás, kezdő/záró időpont,
  helyszín+koordináták, hivatalos kép, linkek, ingyenes/ár, kiemelés, állapot,
  időbélyegek).
- **`wine_regions`** — 22 magyar borvidék (segédtábla, FK az eventsből).
- **`categories`** + **`event_categories`** — címkék, több-a-többhöz kapcsolat.
- **`event_interactions`** — analitika: nyers kattintás/megtekintés napló (időbélyeggel,
  hashelt IP-vel). Kimenő kattintások (`click_website`/`click_ticket`) + `view`.
- **`event_impressions_daily`** — lista-megjelenések napi összesítésben (nagy volumen).
- **`ai_referrals`** — AI-asszisztensből érkező látogatók naplója (ChatGPT, Perplexity,
  Gemini, Claude, Copilot). Futásidőben jön létre (`ensureAiReferralsTable()`). A fő oldalak
  (`index`, `esemenyek`, `naptar`, `terkep`, `esemeny`) `logAiReferral()`-t hívnak, ami CSAK
  akkor ír sort, ha AI-jel van (`?utm_source=…` VAGY AI-host referrer) — a nyitóoldalt is beleértve.

**Bevételi cél:** a kattintás-statisztikákból kimutatás a szervezőknek. Migráció:
`db/migrations/001_add_analytics.sql` (élő DB-n is futtatható).
**Kattintás-naplózó kész:** `public/go.php?e=<id>&t=website|ticket` — naplóz az
`event_interactions`-be, majd `302`-vel a DB-ben tárolt cél URL-re irányít (a cél
SOHA nem a query stringből → nincs nyílt átirányítás). Az `esemeny.php` kimenő gombjai
ezen mennek át. GDPR: nyers IP helyett napi sóval hashelt `ip_hash` (`app_salt` a
configból; éles: `APP_SALT` secret, vagy a CI a DB jelszóból származtatja); botokat
nem számol; `robots.txt` `Disallow: /go.php` + `rel="nofollow"`.
**Sütis mérés kész (consent-alapú):** a lábléc süti-sávja (`partials/footer.php`)
kér hozzájárulást; elfogadáskor JS anonim mérési azonosítót állít be
(`hb_sid`, 32 hex, 365 nap; a döntés `hb_consent`, 180 nap). A `logInteraction()`
CSAK `hb_consent=1` + formátum-valid `hb_sid` esetén tölti a `session_id` oszlopot —
ez adja a napokon átívelő pontos egyedi/visszatérő mérést; elutasítóknál marad az
`ip_hash`-becslés. Az adatvédelmi tájékoztató (`adatvedelem.php`) sütik szakasza
ehhez igazítva. **`view`-mérés kész:** az `esemeny.php` részletoldal-megtekintéskor
naplóz (ugyanazzal a `logInteraction()`-nel). Az impresszió-mérés (lista-megjelenések,
`event_impressions_daily`) még nincs bekötve.
**Admin/saját forgalom kizárva:** admin belépéskor + minden admin-oldalon (`require_admin`)
tartós `hb_notrack=1` süti áll be; a három naplózó (`logInteraction`/`logSearchReferral`/
`logAiReferral`) a `trackingOptedOut()`-tal ezt kihagyja — a fejlesztői/teszt böngészés nem
hígítja a statisztikát (valódi látogatóként inkognitóban tesztelhető).
**Admin statisztika kész:** `admin/statisztika.php` — időszak-fülek (7/30/90 nap/teljes),
összesítő csempék (megtekintés/honlap-katt./jegy-katt./CTR; egyedi =
`COALESCE(session_id, ip_hash)`), „Sütis látogató-mérés" blokk (mért látogató,
visszatérő, látogató-konverzió, esemény/látogató), eseményenkénti táblázat CTR-rel,
napi bontás (14 nap), hivatkozó domainek (saját domain kiszűrve). **AI-ajánlások panel:**
az `ai_referrals` táblából számol (érkezés + egyedi látogató + platformonkénti bontás;
Továbbkattintás = az AI-látogató utóbb szervező-oldalra kattintott, azonos látogató-azonosítón).

Karakterkészlet: `utf8mb4`. Ismétlődő (évente megrendezett) eseménynél évente
új sort veszünk fel (évszám a slugban).
