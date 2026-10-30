<?php

require_once "auth.php";

require_once "../../config/database.php";

require_once "../../config/youtube_functions.php";


$message = "";

$erreur = "";


if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    $lien =
        trim(
            $_POST["lien"]
            ?? ""
        );


    $categorie =
        trim(
            $_POST["categorie"]
            ?? ""
        );


    $vedette =
        isset(
            $_POST["vedette"]
        )
        ? 1
        : 0;


    /*
    |--------------------------------------------------------------------------
    | VÉRIFIER LE LIEN
    |--------------------------------------------------------------------------
    */

    if (
        empty($lien)
    ) {

        $erreur =
            "Veuillez entrer un lien YouTube.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | EXTRAIRE ID
        |--------------------------------------------------------------------------
        */

        $youtube_id =
            extraireYoutubeId(
                $lien
            );


        if (
            !$youtube_id
        ) {

            $erreur =
                "Le lien YouTube est invalide.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | VÉRIFIER DOUBLON
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

                $youtube_id

            ]);


            if (
                $stmt->fetch()
            ) {

                $erreur =
                    "Cette vidéo existe déjà.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | API YOUTUBE
                |--------------------------------------------------------------------------
                */

                $data =
                    recupererVideoYouTube(
                        $youtube_id
                    );


                if (
                    !$data
                    ||
                    empty(
                        $data["success"]
                    )
                ) {

                    $erreur =
                        $data["error"]
                        ??
                        "Impossible de récupérer les informations.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | GESTION VEDETTE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $vedette == 1
                    ) {

                        $pdo->exec("

                            UPDATE youtube_videos

                            SET vedette = 0

                        ");

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | INSERTION
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

                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'publie', NOW(), NOW()

                            )

                        ");


                    $stmt->execute([

                        $youtube_id,

                        $data["titre"],

                        $data["description"],

                        $data["miniature"],

                        $data["date_youtube"],

                        $data["duree"],

                        $data["vues_youtube"],

                        $data["likes_youtube"],

                        $data["commentaires_youtube"],

                        $categorie,

                        $vedette

                    ]);


                    $message =
                        "✅ Vidéo ajoutée avec succès !";

                }

            }

        }

    }

}

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
Ajouter une vidéo
</title>

<style>

body {

    margin: 0;

    padding: 20px;

    background: #f5f5f5;

    font-family: Arial;

}

.container {

    max-width: 600px;

    margin: auto;

    background: white;

    padding: 30px;

    border-radius: 15px;

}

input {

    width: 100%;

    padding: 14px;

    margin: 8px 0 20px;

    box-sizing: border-box;

}

button {

    width: 100%;

    padding: 14px;

    background: #e00000;

    color: white;

    border: none;

    cursor: pointer;

}

.message {

    background: #e8f5e9;

    color: #2e7d32;

    padding: 12px;

    margin-bottom: 20px;

}

.erreur {

    background: #ffebee;

    color: #c62828;

    padding: 12px;

    margin-bottom: 20px;

}

</style>

</head>

<body>

<div class="container">

<a href="index.php">
← Retour
</a>

<h1>
➕ Ajouter une vidéo
</h1>

<?php if (!empty($message)): ?>

<div class="message">

<?= htmlspecialchars(
    $message
) ?>

</div>

<?php endif; ?>


<?php if (!empty($erreur)): ?>

<div class="erreur">

<?= htmlspecialchars(
    $erreur
) ?>

</div>

<?php endif; ?>


<form method="POST">

<label>
Lien YouTube
</label>

<input

    type="url"

    name="lien"

    placeholder="https://www.youtube.com/watch?v=XXXXXXXXXXX"

    required

>


<label>
Catégorie
</label>

<input

    type="text"

    name="categorie"

    placeholder="Actualité, Sport, Éducation..."

>


<label>

<input

    type="checkbox"

    name="vedette"

    value="1"

>

⭐ Mettre à la une

</label>

<br><br>

<button type="submit">

🎬 Ajouter automatiquement

</button>

</form>

</div>

</body>

</html>