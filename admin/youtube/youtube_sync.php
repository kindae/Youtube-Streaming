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
| NOMBRE DE VIDÉOS À VÉRIFIER
|--------------------------------------------------------------------------
*/

$max_results = 20;


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES VIDÉOS
|--------------------------------------------------------------------------
*/

$result =
    recupererVideosChaineYouTube(
        $channel_id,
        $max_results
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


/*
|--------------------------------------------------------------------------
| PARCOURIR LES VIDÉOS
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

echo "<h1>Synchronisation YouTube</h1>";

echo "<p>";

echo "✅ Nouvelles vidéos ajoutées : ";

echo $ajoutees;

echo "</p>";


echo "<p>";

echo "ℹ️ Vidéos déjà présentes : ";

echo $existantes;

echo "</p>";

?>