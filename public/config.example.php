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
        'user' => 'c105746ptrk',
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
        'model'   => 'claude-opus-4-8',  // olcsóbb opció: 'claude-haiku-4-5'
    ],
];
