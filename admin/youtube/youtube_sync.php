<?php
/*
|--------------------------------------------------------------------------
| SYNCHRONISATION AUTOMATIQUE YOUTUBE (CRON)
|--------------------------------------------------------------------------
| Appel cron :
|   wget -qO- "https://plus.congonet24.com/admin/youtube/youtube_sync.php?token=VOTRE_JETON"
|   ou : php /chemin/vers/admin/youtube/youtube_sync.php [--complet]
| Option : &complet=1 parcourt toute la chaîne (première importation / rattrapage)
| La logique se trouve dans config/youtube_sync_lib.php
*/

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/youtube_sync_lib.php";

set_time_limit(300);

$en_cli = PHP_SAPI === 'cli';
$complet = $en_cli ? in_array('--complet', $argv ?? [], true) : !empty($_GET['complet']);

// Jeton optionnel (config/secrets.php => 'cron_token')
if (!$en_cli && CRON_TOKEN !== '' && !hash_equals(CRON_TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("Accès refusé.");
}

if (!$en_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
}

$resultat = synchroniserYoutube($pdo, $complet);
echo implode("\n", $resultat['lignes']) . "\n";

if (!$resultat['ok']) {
    if (!$en_cli) http_response_code(500);
    exit(1);
}
