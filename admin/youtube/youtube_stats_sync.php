<?php

require_once "../../config/database.php";

require_once "../../config/youtube_functions.php";


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$total_videos = 0;

$total_mises_a_jour = 0;

$total_erreurs = 0;


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES VIDÉOS DE MYSQL
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


$videos =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| NOMBRE TOTAL
|--------------------------------------------------------------------------
*/

$total_videos =
    count(
        $videos
    );


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
    | ID YOUTUBE
    |--------------------------------------------------------------------------
    */

    $youtube_id =
        $video["youtube_id"];


    /*
    |--------------------------------------------------------------------------
    | RÉCUPÉRER LES NOUVELLES INFOS
    |--------------------------------------------------------------------------
    */

    $details =
        recupererVideoYouTube(
            $youtube_id
        );


    /*
    |--------------------------------------------------------------------------
    | ERREUR
    |--------------------------------------------------------------------------
    */

    if (
        !$details
        ||
        empty(
            $details["success"]
        )
    ) {

        $total_erreurs++;

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | METTRE À JOUR MYSQL
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

        $details["vues_youtube"],

        $details["likes_youtube"],

        $details["commentaires_youtube"],

        $video["id"]

    ]);


    /*
    |--------------------------------------------------------------------------
    | COMPTEUR
    |--------------------------------------------------------------------------
    */

    $total_mises_a_jour++;

}


/*
|--------------------------------------------------------------------------
| AFFICHAGE
|--------------------------------------------------------------------------
*/

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Mise à jour statistiques YouTube
</title>


<style>

body {

    font-family: Arial, sans-serif;

    background: #f5f5f5;

    padding: 30px;

}

.container {

    max-width: 600px;

    margin: auto;

    background: white;

    padding: 30px;

    border-radius: 15px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.1);

}

.stat {

    padding: 15px;

    margin: 10px 0;

    background: #f5f5f5;

    border-radius: 8px;

}

.success {

    color: green;

}

.error {

    color: red;

}

</style>

</head>


<body>


<div class="container">


<h1>

📊 Statistiques YouTube

</h1>


<div class="stat">

📺 Vidéos analysées :

<strong>

<?= $total_videos ?>

</strong>

</div>


<div class="stat success">

✅ Vidéos mises à jour :

<strong>

<?= $total_mises_a_jour ?>

</strong>

</div>


<div class="stat error">

❌ Erreurs :

<strong>

<?= $total_erreurs ?>

</strong>

</div>


<br>


<p>

🕐 Dernière synchronisation :

<strong>

<?= date("d/m/Y H:i:s") ?>

</strong>

</p>


<a href="index.php">

← Retour à l'administration

</a>


</div>


</body>

</html>