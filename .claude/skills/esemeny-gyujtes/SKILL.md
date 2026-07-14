---
name: esemeny-gyujtes
description: Végigmegy a docs/esemeny-forrasok.md-ben felsorolt honlapokon, kigyűjti a borhoz köthető eseményeket, kiszűri a holborozzak.hu-n már meglévőket, és az újakat jelöltként beküldi az admin Jelöltek oldalára (collect-ingest.php). Használd, ha a felhasználó esemény-gyűjtést, forrás-honlapok átnézését vagy új események importálását kéri.
---

# Esemény-gyűjtés a forrás-honlapokról

A cél: a forráslistában szereplő honlapokról kigyűjteni azokat a **jövőbeni, magyarországi,
borhoz köthető eseményeket**, amelyek még nincsenek fent a holborozzak.hu-n, és beküldeni
őket **jelöltként** — az admin → Jelöltek oldalon jelennek meg kitöltött adatlappal,
ott kézi jóváhagyás után lesznek `draft` események. SOHA ne írj közvetlenül az
`events` táblába — mindig a jelölt-folyamaton át.

## 0. Előfeltételek

1. **Token:** olvasd ki a `public/config.php`-ból a `collect_token` értékét (gitignore-olt
   lokális fájl). Ha a fájl vagy az érték hiányzik, ÁLLJ MEG, és kérd meg a felhasználót:
   másolja le a `public/config.example.php`-t `public/config.php` néven, és a
   `collect_token`-be írja be UGYANAZT az értéket, ami a GitHub `COLLECT_TOKEN`
   secretben van. (A többi mező üresen maradhat.) A tokent SOHA ne írd ki a válaszban.
2. **Forráslista:** olvasd be a `docs/esemeny-forrasok.md`-t. Ha üres vagy nem létezik,
   kérj forrás URL-eket a felhasználótól.

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
   - `start_datetime` / `end_datetime` — `YYYY-MM-DD HH:MM:SS`; ha csak nap ismert,
     idő `00:00:00` (az admin pontosítja jóváhagyáskor)
   - `venue_name`, `city`
   - `region_name` — a 22 magyar borvidék egyike, CSAK ha egyértelmű (pl. Tokaj, Villány,
     Eger, Badacsony, Szekszárd…); ha bizonytalan, hagyd üresen
   - `website_url` — az esemény hivatalos oldala (a részletoldal URL-je)
   - `image_url` — abszolút kép-URL, ha van értelmes borító
   - `source_url` — melyik forrásoldalról találtad
3. Szűrés: CSAK jövőbeni, konkrét dátumú, magyarországi, borhoz köthető esemény
   (borfesztivál, bornap, kóstoló, szüreti rendezvény, pincetúra stb.). Múltbeli,
   dátum nélküli, külföldi vagy nem boros programot hagyj ki.

## 3. Beküldés a jelöltek közé

A kigyűjtött ÚJ eseményeket egyetlen JSON-ban POST-old (a payloadot a scratchpadbe írd,
ne a repóba):

```
POST https://holborozzak.hu/collect-ingest.php
Content-Type: application/json

{ "token": "<collect_token>", "events": [ { ...mezők a 2. pont szerint... } ] }
```

FONTOS: a token a KÉRÉS TÖRZSÉBEN menjen — a Rackhost proxy az egyéni HTTP-fejléceket
(X-Collect-Token) eldobja. `curl -sS -X POST -H "Content-Type: application/json"
--data @payload.json https://holborozzak.hu/collect-ingest.php`

A válasz: `{"received": n, "added": n, "skipped": n}` — a `skipped` a szerveroldali
dedup által kiszűrt duplikátum.

## 4. Összefoglaló a felhasználónak

Táblázatban: forrás → hány eseményt találtál → hány újat küldtél be → szerver
added/skipped. Említsd meg a kihagyott kéteseket (és miért). Zárásként emlékeztesd:
a jelöltek az **admin → Jelöltek** oldalon várnak jóváhagyásra
(`https://holborozzak.hu/admin/jeloltek.php`).

## Korlátok

- Légy konzervatív: inkább kevesebb, biztosan jó találat, mint sok szemét.
- Egy futásban forrásonként max ~15 részletoldal-letöltés.
- Ha egy forrásoldal nem tölthető le vagy szerkezete értelmezhetetlen, ugord át,
  és jelezd az összefoglalóban.
