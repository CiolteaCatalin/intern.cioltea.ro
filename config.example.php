<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'family_hub',
        'user' => 'family_user',
        'pass' => 'schimba_parola_db',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Cioltea Hub',
        'base_url' => 'https://intern.exemplu.ro',
        'timezone' => 'Europe/Bucharest',
        'session_days' => 30,
        // Genereaza hash-ul cu:
        // php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;' 'PAROLA_TA!'
        'password_hash' => '$2y$10$SCHIMBA_HASHUL_AICI',
    ],
    'digistorage' => [
        // Integrare server-to-server cu Digi Storage. Credentialele nu ajung în browser.
        // Cu 2FA activ, folosește parola de aplicație Digi Storage, nu parola principală.
        'enabled' => false,
        'email' => 'emailul_contului_digi@example.com',
        'password' => 'PAROLA_DE_APLICATIE_DIGI',
        // Optional: poți folosi direct un token și lăsa email/password goale.
        'token' => '',
        // Optional: dacă ai mai multe mount-uri, setează ID-ul sau numele. Altfel se alege automat primul cu WRITE.
        'mount_id' => '',
        'mount_name' => '',
        // Cioltea Hub nu va naviga în afara acestui folder.
        'root_path' => '/7. POZE/',
    ],
    'notifications' => [
        // Web Push standard, gratuit si self-hosted. Nu ai nevoie de OneSignal/FCM.
        'enabled' => true,
        // Optional. Daca ramane gol se foloseste automat app.base_url ca identificator VAPID.
        // Poti pune si mailto:adresa@domeniu.ro
        'vapid_subject' => '',
        // Token lung si aleator pentru rularea cronului prin HTTP, daca nu folosesti CLI.
        'cron_token' => 'SCHIMBA_CU_UN_TOKEN_LUNG_ALEATOR',
    ],
];
