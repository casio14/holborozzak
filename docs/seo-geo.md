# holborozzak.hu — SEO & AI-kereső (GEO) checklist

> Kiemelt projekt-cél: erős keresőoptimalizálás **és** AI-kereső/ajánlás-barátság.
> Minden új oldalnál/funkciónál tartsuk be ezt a listát.

## Fundamentum (a layout vázban, `partials/header.php`) — ✅ kész
- [x] `<html lang="hu">`, `charset=utf-8`, responsive viewport
- [x] Oldalankénti egyedi `<title>` és `<meta name="description">`
- [x] `<link rel="canonical">` (a tényleges kérésből számolva → bármely domainen helyes)
- [x] Open Graph (`og:title/description/url/type/site_name/locale/image`)
- [x] Twitter Card (`summary_large_image`)
- [x] `robots` meta (alap: `index,follow`, oldalanként felülírható)
- [x] `theme-color`
- [x] JSON-LD mechanizmus + alap `WebSite` és `Organization` strukturált adat
- [x] `robots.txt` — AI-crawlerek ENGEDÉLYEZVE

## Tartalmi / szemantikai elvek (minden oldal)
- [ ] Pontosan **egy `<h1>`** oldalanként, logikus címhierarchia
- [ ] Szemantikus elemek: `<article>`, `<section>`, `<nav>`, `<time datetime="...">`
- [ ] Dátum mindig **ISO 8601** `datetime` attribútumban is (gépi olvashatóság)
- [ ] Beszédes, slug-alapú URL-ek: `/esemeny/<slug>`
- [ ] Képeknek `alt` (az `events.image_alt` mezőből)
- [ ] Önállóan is értelmes, tényszerű tartalom (AI-k szeretik a tiszta tényeket)

## Strukturált adat (Schema.org JSON-LD) — a kulcs az AI-ajánlásokhoz
- [ ] **Eseménylista oldal:** `ItemList` az eseményekről
- [ ] **Esemény részletoldal:** `Event` — `name`, `startDate`/`endDate` (ISO),
      `eventStatus`, `location` → `Place` (`name`, `address` → `PostalAddress`,
      `geo` → `GeoCoordinates`), `image`, `description`, `url`,
      `offers` → `Offer` (ár/`price`, `priceCurrency`, `availability`, ingyenesnél `price: 0`),
      `organizer`, `performer` ha van
- [ ] `BreadcrumbList` a navigációhoz
- [ ] Opcionális `FAQPage`, ahol van GYIK

## Technikai SEO
- [ ] `sitemap.xml` dinamikusan a DB-ből (csak `status='published'`), `lastmod`
- [ ] `robots.txt` a sitemap hivatkozással (a VÉGLEGES `holborozzak.hu` domainen él igazán,
      mert a host gyökeréből szolgálódik — az ideiglenes almappás címen nem)
- [ ] HTTPS ✅, gyors betöltés (könnyű CSS, képek lazy-load + méretezés)
- [ ] 404 oldal értelmes tartalommal
- [ ] Lejárt eseményeknél megfontolni: marad indexelve (archív érték) vs. `noindex`

## AI-kereső (GEO) specifikus
- [ ] AI-crawlerek engedélyezése: GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot,
      Claude-Web, PerplexityBot, Google-Extended, CCBot (döntés szerint)
- [ ] Megfontolni: `llms.txt` (llmstxt.org) a site áttekintésével
- [ ] Friss `dateModified`/`datePublished` a strukturált adatban
- [ ] Egyértelmű entitások (borvidék nevek, települések) — segíti az AI megértést

## GEO-analitika (mérés)
- [x] **AI-ajánlás mérés (referrer-alapú):** admin statisztika „AI-ajánlások" panel —
      hány látogató érkezett AI-asszisztensből (ChatGPT, Perplexity, Gemini, Copilot,
      Claude), platformonként, a `event_interactions.referrer` alapján.
- [ ] **TODO — AI-crawler lekérések naplózása:** amikor egy AI *élő* crawlere
      (ChatGPT-User, PerplexityBot, OAI-SearchBot) lekéri egy esemény oldalát egy
      felhasználói kérdés megválaszolásához, azt is számoljuk (a „kattintás nélküli"
      AI-használat egy része). Ehhez a jelenleg botszűrt naplózást (`logInteraction`
      → `isLikelyBot`) kell kibővíteni: az AI-crawlereket ne dobjuk el, hanem külön
      típussal/jelöléssel rögzítsük, és a statisztikában külön mutassuk. Nagyobb
      változás (adatmodell + naplózó + admin nézet).

## Megnyitott döntések
- [x] Alap `og:image` megosztókép (`assets/hero.jpg`) — kész, a `header.php` beállítja.
- [ ] Lejárt események indexelési politikája
