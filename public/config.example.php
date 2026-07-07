<?php
// MINTA konfiguráció. Másold le 'config.php' néven, és töltsd ki a jelszót.
//
// - A config.php NEM kerül gitbe (.gitignore), és NEM kerül a szerverre ebből
//   a mintából — éles környezetben a CI generálja a DB_PASSWORD secretből.
// - Lokális fejlesztéshez ide írhatod a saját (teszt) DB jelszavadat.

return [
    'db' => [
        'host' => 'mysql.rackhost.hu',
        'port' => 3306,
        'name' => 'c105746holborozzak',
        'user' => 'c105746patrik',
        'pass' => '', // <- ide a jelszó (lokálisan); éles: a CI tölti ki
    ],
    // Titkos „só" az IP-hasheléshez (kattintás-naplózó). Éles: APP_SALT secret.
    // Lokálisan tetszőleges hosszú véletlen szöveg.
    'app_salt' => 'valami-helyi-fejlesztoi-so',

    // Admin belépés. Éles: a CI tölti az ADMIN_USER + ADMIN_PASSWORD secretből
    // (a jelszót bcrypt-tel hash-eli). Lokálisan a hash-t így generálhatod:
    //   php -r "echo password_hash('jelszavad', PASSWORD_DEFAULT);"
    'admin' => [
        'user'      => 'admin',
        'pass_hash' => '', // üres = belépés letiltva (amíg nincs kitöltve)
    ],

    // Anthropic (Claude) API — az esemény-importhoz/gyűjtéshez. Éles: ANTHROPIC_API_KEY secret.
    'anthropic' => [
        'api_key' => '',                 // üres = az import funkció hibát ad
        'model'   => 'claude-haiku-4-5', // olcsó, gyors; igényesebb kinyeréshez: 'claude-opus-4-8'
    ],

    // A napi gyűjtő (GitHub Actions) ezzel a tokennel POST-ol a collect-ingest.php-ra.
    // Éles: COLLECT_TOKEN secret (ugyanaz az érték a workflow secretjében).
    'collect_token' => '',

    // A hírlevél-küldő cron (GitHub Actions) tokenje a newsletter-send.php-hoz.
    // Éles: NEWSLETTER_TOKEN secret (ugyanaz az érték a workflow secretjében).
    'newsletter_token' => '',

    // E-mail feladó (üdvözlő + hírlevél). Éles: MAIL_FROM secret.
    // Üresen hagyva a kiszolgáló hosztjából képzett no-reply cím a fallback —
    // a kézbesíthetőséghez (SPF) érdemes valódi, a domainhez tartozó címet megadni.
    'mail' => [
        'from_email' => '',
        'from_name'  => 'holborozzak.hu',
    ],

    // Beérkező e-mailek olvasása (admin → Beérkező) IMAP-on. Éles: IMAP_* secretek.
    // ÜRES jelszó = a funkció ki van kapcsolva (a lap beállítási útmutatót mutat).
    // A host-ot ELLENŐRIZD a Rackhost webmail (Roundcube) IMAP-beállításainál!
    'imap' => [
        'host' => 'mail.rackhost.hu',
        'port' => 993,
        'user' => 'info@holborozzak.hu',
        'pass' => '',
    ],
];
