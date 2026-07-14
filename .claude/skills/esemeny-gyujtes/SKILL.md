---
name: esemeny-gyujtes
description: Végigmegy a docs/esemeny-forrasok.md-ben felsorolt honlapokon, kigyűjti a borhoz köthető eseményeket, kiszűri a holborozzak.hu-n már meglévőket, és az újakat a public/admin/jelolt-import.json fájlba írva (commit + push → auto-deploy) az admin Jelöltek oldalára juttatja. Használd, ha a felhasználó esemény-gyűjtést, forrás-honlapok átnézését vagy új események importálását kéri.
---

# Esemény-gyűjtés a forrás-honlapokról

A cél: a forráslistában szereplő honlapokról kigyűjteni azokat a **jövőbeni, magyarországi,
borhoz köthető eseményeket**, amelyek még nincsenek fent a holborozzak.hu-n, és eljuttatni
őket **jelöltként** az admin → Jelöltek oldalra, ahol kézi jóváhagyás után lesznek `draft`
események. SOHA ne írj közvetlenül az `events` táblába — mindig a jelölt-folyamaton át.

**Nincs token, nincs API-kulcs, nincs automatikus futás** — ezt a skillt csak a felhasználó
indítja kézzel; a kinyerést te (a session-beli Claude) végzed; az átadás a git push →
auto-deploy útján történik.

## 0. Előfeltétel

Olvasd be a `docs/esemeny-forrasok.md`-t. Ha üres vagy nem létezik, kérj forrás
URL-eket a felhasználótól, és vedd fel őket a fájlba.

## 1. Meglévő események lekérése (elő-szűréshez)

Töltsd le az élő lista oldalt: `https://holborozzak.hu/esemenyek` (WebFetch).
Jegyezd fel a meglévő események címét + dátumát. Ez csak elő-szűrés — a végleges
dedup a szerveren történik (cím+nap+város normalizált kulccsal), de így elkerülöd,
hogy kicsit eltérő címmel duplikátumot küldj be. Kétes egyezésnél (hasonló cím,
ugyanaz a dátum/város) NE küldd be, inkább jelezd a felhasználónak.

## 2. Források végigjárása

Minden forrás URL-re:

1. Töltsd le az oldalt (WebFetch). Ha lista-/programoldal, kövesd az egyes események
   aloldalait is (forrásonként legfeljebb ~15 részletoldal, hogy ne fusson el).
2. Gyűjtsd ki eseményenként az alábbi mezőket (amit az oldal nem ad meg, maradjon üres):
   - `title` — az esemény neve (kötelező)
   - `short_description` — 1–2 mondatos magyar összefoglaló (te írod, a forrás alapján)
   - `description` — hosszabb leírás, ha a forrás ad hozzá anyagot (sima szöveg)
   - `start_datetime` / `end_datetime` — `YYYY-MM-DD HH:MM:SS`; ha csak nap ismert,
     idő `00:00:00` (az admin pontosítja jóváhagyáskor)
   - `venue_name`, `city`
   - `region_name` — a 22 magyar borvidék egyike, CSAK ha egyértelmű (pl. Tokaj, Villány,
     Eger, Badacsony, Szekszárd…); ha bizonytalan, hagyd üresen
   - `website_url` — az esemény hivatalos oldala (a részletoldal URL-je)
   - `facebook_url`, `ticket_url` — ha van
   - `is_free` (true/false) és `price_info` — ha az oldal egyértelműen közli
   - `image_url` — abszolút kép-URL, ha van értelmes borító
   - `source_url` — melyik forrásoldalról találtad
3. Szűrés: CSAK jövőbeni, konkrét dátumú, magyarországi, borhoz köthető esemény
   (borfesztivál, bornap, kóstoló, szüreti rendezvény, pincetúra stb.). Múltbeli,
   dátum nélküli, külföldi vagy nem boros programot hagyj ki.

## 3. Átadás az admin Jelöltek oldalnak

Írd a kigyűjtött ÚJ eseményeket a **`public/admin/jelolt-import.json`** fájlba
(FELÜLÍRVA a korábbi tartalmat — mindig csak az aktuális futás eredménye legyen benne):

```json
{
  "generated_at": "2026-07-14T12:00:00+02:00",
  "events": [ { "title": "…", "start_datetime": "…", … } ]
}
```

Majd **commit + push a main-re** (auto-deploy). A deploy után (~1–2 perc) az
**admin → Jelöltek** oldal megnyitáskor automatikusan beolvassa a fájlt, és a még
nem ismert tételeket felveszi jelöltnek (a dedup miatt idempotens — a fájl nyugodtan
a repóban maradhat, újratöltéskor nem duplikál).

Megjegyzés: a fájl webről elérhető (`/admin/jelolt-import.json`), de csak nyilvános
eseményadatokat tartalmazhat — SOHA ne kerüljön bele semmilyen titok vagy személyes adat.

## 4. Összefoglaló a felhasználónak

Táblázatban: forrás → hány eseményt találtál → hány újat tettél a fájlba.
Említsd meg a kihagyott kéteseket (és miért). Zárásként emlékeztesd a felhasználót:
deploy után nyissa meg az **admin → Jelöltek** oldalt
(`https://holborozzak.hu/admin/jeloltek.php`) — ott jelennek meg a jelöltek
jóváhagyásra, kitöltött adatlappal.

## Korlátok

- Légy konzervatív: inkább kevesebb, biztosan jó találat, mint sok szemét.
- Egy futásban forrásonként max ~15 részletoldal-letöltés.
- Ha egy forrásoldal nem tölthető le vagy szerkezete értelmezhetetlen, ugord át,
  és jelezd az összefoglalóban.
