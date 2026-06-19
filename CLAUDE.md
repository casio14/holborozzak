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
  - Kiszolgáló: `wh11.rackhost.hu`
  - Felhasználónév: `c105746ptrk`
  - Célkönyvtár (deploy ide): `/web/kissptrk.hu/`
  - Jelszó: **GitHub repository secret**-ben (`FTP_PASSWORD`), NEM a kódban.
  - Megjegyzés: a domain `holborozzak.hu`, de a könyvtár neve `kissptrk.hu` —
    ellenőrizni, hogy ez-e a `holborozzak.hu` document rootja.
- **GitHub repo:** `git@github.com:casio14/borozzak.git`
- **Deploy:** GitHub Actions (`.github/workflows/deploy.yml`) → `main`-re
  pusholáskor a `public/` mappa tartalmát felmásolja a webszerverre
  (`SamKirkland/FTP-Deploy-Action`, csak a változott fájlok).
  - **Protokoll: sima `ftp`** — a Rackhost FTP szervere NEM támogatja az FTPS-t
    (`AUTH TLS` → `500`). A jelszó így titkosítatlanul utazik (lásd biztonsági TODO).
  - **Az FTP-login a docrootba érkezik**, ezért a `server-dir` RELATÍV (`borozzak/`),
    nem abszolút. Abszolút `/web/kissptrk.hu/...` duplikálná a könyvtárat.
  - **Ideiglenes cím:** https://kissptrk.hu/borozzak/ (amíg a `holborozzak.hu` nem áll).
  - **Verziózás: szemantikus, git tag-es.** A `VERSION` fájl tartja a
    `major.minor`-t (pl. `1.0`); a patch automatikusan a meglévő tagek alapján +1.
    Minden sikeres deploy `vX.Y.Z` git taget hoz létre, és a verzió megjelenik
    az oldal láblécében (a CI által generált `public/version.php`-ból).
  - **Major/minor léptetés:** kézzel írd át a `VERSION` fájlt (a patch onnantól 0-ról indul).
  - **Rollback / adott verzió:** Actions → Run workflow → a "Use workflow from"
    legördülőből válaszd a kívánt tag-et; ilyenkor nem készül új tag, csak újra deployol.
  - **TODO (takarítás):** az első hibás deploy árvafájljai a szerveren a
    `/web/kissptrk.hu/web/kissptrk.hu/borozzak/` alatt maradtak — FTP-n/fájlkezelőből törölhetők.
  - **TODO (biztonság):** ha a Rackhost ad SFTP/SSH-t, váltani titkosított feltöltésre.
- **Adatbázis (MySQL, Rackhost):**
  - Kiszolgáló: `mysql.rackhost.hu`
  - Adatbázis neve: `c105746holborozzak`
  - Felhasználónév: `c105746ptrk`
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

## Projekt szerkezet

- `public/` — **a deployolt weboldal** (csak ez kerül a webszerverre). PHP + HTML/CSS.
  - `db.php` — PDO MySQL kapcsolat (`db()` függvény, singleton). A configot a
    `config.php`-ból olvassa.
  - `config.php` — **generált**, NEM gitben: éles környezetben a CI hozza létre a
    `DB_PASSWORD` secretből; lokálisan a `config.example.php`-ból másolod.
  - `health.php` — ideiglenes DB-egészség ellenőrző (élesítés előtt törölni/védeni).
  - `version.php` — generált verziófájl (CI).
- `db/` — adatbázis séma (`schema.sql`), migrációk. NEM kerül a webszerverre.
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

**Bevételi cél:** a kattintás-statisztikákból kimutatás a szervezőknek. Migráció:
`db/migrations/001_add_analytics.sql` (élő DB-n is futtatható). Kattintást átirányító
(`go.php`) naplóz majd. GDPR-t és bot-szűrést szem előtt tartani.

Karakterkészlet: `utf8mb4`. Ismétlődő (évente megrendezett) eseménynél évente
új sort veszünk fel (évszám a slugban).
