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
| VÉRIFIER LES 20 DERNIÈRES VIDÉOS
|--------------------------------------------------------------------------
*/

$max_results = 20;


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$nouvelles_videos = 0;

$videos_existantes = 0;

$videos_statistiques = 0;

$erreurs = 0;

$nombre_requetes_stats = 0;


/*
|--------------------------------------------------------------------------
| ÉTAPE 1
| RÉCUPÉRER LES 20 DERNIÈRES VIDÉOS
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

        "Erreur récupération vidéos : "

        .

        $result["error"]

    );

}


$videos =
    $result["videos"];


/*
|--------------------------------------------------------------------------
| ÉTAPE 2
| AJOUTER LES NOUVELLES VIDÉOS
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


    /*
    |--------------------------------------------------------------------------
    | VIDÉO EXISTANTE
    |--------------------------------------------------------------------------
    */

    if (
        $existe
    ) {

        $videos_existantes++;

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | RÉCUPÉRER INFOS COMPLÈTES
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


    $nouvelles_videos++;

}


/*
|--------------------------------------------------------------------------
| ÉTAPE 3
| RÉCUPÉRER TOUTES LES VIDÉOS MYSQL
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->query("

        SELECT

            id,

            youtube_id

        FROM youtube_videos

        WHERE youtube_id IS NOT NULL

        AND youtube_id != ''

        ORDER BY id ASC

    ");


$toutes_videos =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| DIVISER EN LOTS DE 50
|--------------------------------------------------------------------------
*/

$lots =
    array_chunk(
        $toutes_videos,
        50
    );


/*
|--------------------------------------------------------------------------
| PARCOURIR CHAQUE LOT
|--------------------------------------------------------------------------
*/

foreach (
    $lots
    as $lot
) {


    /*
    |--------------------------------------------------------------------------
    | EXTRAIRE LES IDS YOUTUBE
    |--------------------------------------------------------------------------
    */

    $youtube_ids = [];


    foreach (
        $lot
        as $video
    ) {

        $youtube_ids[] =
            $video["youtube_id"];

    }


    /*
    |--------------------------------------------------------------------------
    | UNE REQUÊTE POUR 50 VIDÉOS
    |--------------------------------------------------------------------------
    */

    $result_stats =
        recupererStatistiquesVideosYouTube(
            $youtube_ids
        );


    $nombre_requetes_stats++;


    /*
    |--------------------------------------------------------------------------
    | ERREUR
    |--------------------------------------------------------------------------
    */

    if (
        !$result_stats["success"]
    ) {

        $erreurs++;

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | STATISTIQUES RÉCUPÉRÉES
    |--------------------------------------------------------------------------
    */

    $statistiques =
        $result_stats[
            "statistiques"
        ];


    /*
    |--------------------------------------------------------------------------
    | METTRE À JOUR MYSQL
    |--------------------------------------------------------------------------
    */

    foreach (
        $lot
        as $video
    ) {


        $youtube_id =
            $video["youtube_id"];


        /*
        |--------------------------------------------------------------------------
        | VÉRIFIER SI YOUTUBE A RETOURNÉ LA VIDÉO
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $statistiques[
                    $youtube_id
                ]
            )
        ) {

            $erreurs++;

            continue;

        }


        $stats =
            $statistiques[
                $youtube_id
            ];


        /*
        |--------------------------------------------------------------------------
        | UPDATE MYSQL
        |--------------------------------------------------------------------------
        */

        $update =
            $pdo->prepare("

                UPDATE youtube_videos

                SET

                    vues_youtube = ?,

                    likes_youtube = ?,

                    commentaires_youtube = ?,

                    derniere_sync = NOW()

                WHERE id = ?

            ");


        $update->execute([

            $stats[
                "vues_youtube"
            ],

            $stats[
                "likes_youtube"
            ],

            $stats[
                "commentaires_youtube"
            ],

            $video["id"]

        ]);


        $videos_statistiques++;

    }

}


/*
|--------------------------------------------------------------------------
| RÉSULTAT
|--------------------------------------------------------------------------
*/

echo "OK";

echo "\n";

echo "Nouvelles vidéos ajoutées : ";

echo $nouvelles_videos;

echo "\n";

echo "Vidéos déjà présentes : ";

echo $videos_existantes;

echo "\n";

echo "Statistiques mises à jour : ";

echo $videos_statistiques;

echo "\n";

echo "Requêtes statistiques effectuées : ";

echo $nombre_requetes_stats;

echo "\n";

echo "Erreurs : ";

echo $erreurs;

?>