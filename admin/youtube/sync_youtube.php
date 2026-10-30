<?php

require_once "../../config/database.php";
require_once "../../config/youtube_functions.php";


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES VIDÉOS DE LA BASE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, youtube_id
    FROM youtube_videos
    WHERE statut = 'publie'
");

$videos = $stmt->fetchAll();


$nombre_mis_a_jour = 0;

$nombre_erreurs = 0;


/*
|--------------------------------------------------------------------------
| PARCOURIR LES VIDÉOS
|--------------------------------------------------------------------------
*/

foreach ($videos as $video) {

    $youtube_id = $video['youtube_id'];

    $id_mysql = $video['id'];


    /*
    |--------------------------------------------------------------------------
    | RÉCUPÉRATION DES INFORMATIONS YOUTUBE
    |--------------------------------------------------------------------------
    */

    $data = recupererVideoYouTube(
        $youtube_id
    );


    if ($data === false) {

        $nombre_erreurs++;

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | MISE À JOUR MYSQL
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("

        UPDATE youtube_videos

        SET

            titre = ?,

            description = ?,

            miniature = ?,

            date_youtube = ?,

            duree = ?,

            vues_youtube = ?,

            likes_youtube = ?,

            commentaires_youtube = ?

        WHERE id = ?

    ");


    $update->execute([

        $data['titre'],

        $data['description'],

        $data['miniature'],

        $data['date_youtube'],

        $data['duree'],

        $data['vues_youtube'],

        $data['likes_youtube'],

        $data['commentaires_youtube'],

        $id_mysql

    ]);


    $nombre_mis_a_jour++;

}


/*
|--------------------------------------------------------------------------
| RÉSULTAT
|--------------------------------------------------------------------------
*/

echo "Synchronisation terminée.\n";

echo "Vidéos mises à jour : "
    . $nombre_mis_a_jour
    . "\n";

echo "Erreurs : "
    . $nombre_erreurs
    . "\n";