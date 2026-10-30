<?php

require_once "../../config/database.php";

require_once "../../config/youtube_functions.php";


/*
|--------------------------------------------------------------------------
| ID DE TA CHAÎNE YOUTUBE
|--------------------------------------------------------------------------
*/

$channel_id =
    "UC003RZcnMJvfe1M9sVJtPOg";


/*
|--------------------------------------------------------------------------
| PREMIÈRE IMPORTATION COMPLÈTE
|--------------------------------------------------------------------------
*/

$result =
    recupererToutesVideosChaineYouTube(
        $channel_id,
        50
    );


/*
|--------------------------------------------------------------------------
| VÉRIFIER L'API
|--------------------------------------------------------------------------
*/

if (
    !$result["success"]
) {

    die(

        "Erreur : "
        .
        htmlspecialchars(
            $result["error"]
        )

    );

}


$videos =
    $result["videos"];


$ajoutees = 0;

$existantes = 0;

$erreurs = 0;


/*
|--------------------------------------------------------------------------
| PARCOURIR TOUTES LES VIDÉOS
|--------------------------------------------------------------------------
*/

foreach (
    $videos
    as $video
) {


    /*
    |--------------------------------------------------------------------------
    | VÉRIFIER SI ELLE EXISTE
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("

            SELECT id

            FROM youtube_videos

            WHERE youtube_id = ?

            LIMIT 1

        ");


    $stmt->execute([

        $video["youtube_id"]

    ]);


    $existe =
        $stmt->fetch();


    if (
        $existe
    ) {

        $existantes++;

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | RÉCUPÉRER LES INFOS COMPLÈTES
    |--------------------------------------------------------------------------
    */

    $details =
        recupererVideoYouTube(
            $video["youtube_id"]
        );


    if (
        !$details
        ||
        empty(
            $details["success"]
        )
    ) {

        $erreurs++;

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | AJOUT MYSQL
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("

            INSERT INTO youtube_videos (

                youtube_id,

                titre,

                description,

                miniature,

                date_youtube,

                duree,

                vues_youtube,

                likes_youtube,

                commentaires_youtube,

                categorie,

                vedette,

                statut,

                date_ajout,

                derniere_sync

            )

            VALUES (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'publie', NOW(), NOW()

            )

        ");


    $stmt->execute([

        $video["youtube_id"],

        $details["titre"],

        $details["description"],

        $details["miniature"],

        $details["date_youtube"],

        $details["duree"],

        $details["vues_youtube"],

        $details["likes_youtube"],

        $details["commentaires_youtube"],

        "YouTube"

    ]);


    $ajoutees++;

}


/*
|--------------------------------------------------------------------------
| AFFICHER LE RÉSULTAT
|--------------------------------------------------------------------------
*/

echo "<h1>Importation complète YouTube</h1>";

echo "<p>📺 Vidéos récupérées : "
    . count($videos)
    . "</p>";

echo "<p>✅ Nouvelles vidéos ajoutées : "
    . $ajoutees
    . "</p>";

echo "<p>ℹ️ Vidéos déjà présentes : "
    . $existantes
    . "</p>";

echo "<p>❌ Erreurs : "
    . $erreurs
    . "</p>";

?>