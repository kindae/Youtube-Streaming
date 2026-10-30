<?php
/*
|--------------------------------------------------------------------------
| IDENTIFIANTS PRIVÉS
|--------------------------------------------------------------------------
| Copiez ce fichier en "secrets.php" (même dossier) et remplissez-le.
| "secrets.php" est ignoré par Git : il ne doit jamais être publié.
*/
return [
    'db_host'         => 'localhost',
    'db_name'         => 'nom_de_la_base',
    'db_user'         => 'utilisateur',
    'db_pass'         => 'mot_de_passe',
    'youtube_api_key' => 'VOTRE_CLE_API_YOUTUBE',
    'admin_code'      => 'CODE_ADMIN',
    // Jeton du cron : youtube_sync.php?token=... (laisser vide pour ne pas l'exiger)
    'cron_token'      => '',
];
