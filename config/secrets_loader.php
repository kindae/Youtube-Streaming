<?php
/*
|--------------------------------------------------------------------------
| CHARGEMENT DES IDENTIFIANTS (config/secrets.php)
|--------------------------------------------------------------------------
*/
if (!defined('APP_SECRETS_LOADED')) {
    $fichier_secrets = __DIR__ . '/secrets.php';

    if (!is_file($fichier_secrets)) {
        http_response_code(500);
        die("Configuration manquante : copiez config/secrets.example.php en config/secrets.php.");
    }

    $secrets = require $fichier_secrets;

    define('APP_SECRETS_LOADED', true);
    define('DB_HOST', $secrets['db_host'] ?? 'localhost');
    define('DB_NAME', $secrets['db_name'] ?? '');
    define('DB_USER', $secrets['db_user'] ?? '');
    define('DB_PASS', $secrets['db_pass'] ?? '');
    define('YOUTUBE_API_KEY', $secrets['youtube_api_key'] ?? '');
    define('ADMIN_CODE', (string) ($secrets['admin_code'] ?? ''));

    unset($secrets, $fichier_secrets);
}
