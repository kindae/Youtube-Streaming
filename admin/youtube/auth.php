<?php

require_once __DIR__ . "/../../config/secrets_loader.php";

session_start();


// =====================================================
// CODE D'ACCÈS ADMINISTRATION
// =====================================================

$code_acces = ADMIN_CODE;


// =====================================================
// DÉCONNEXION
// =====================================================

if (
    isset($_GET['logout'])
) {

    $_SESSION = [];

    session_destroy();

    header(
        "Location: index.php"
    );

    exit;

}


// =====================================================
// SI L'ADMIN EST DÉJÀ CONNECTÉ
// =====================================================

if (
    isset(
        $_SESSION['youtube_admin']
    )
    &&
    $_SESSION['youtube_admin'] === true
) {

    return;

}


// =====================================================
// TRAITEMENT DU CODE
// =====================================================

$erreur = "";


if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    $code =
        trim(
            $_POST["code"]
            ?? ""
        );


    if (
        $code_acces !== ""
        &&
        hash_equals(
            $code_acces,
            $code
        )
    ) {

        $_SESSION[
            "youtube_admin"
        ] = true;


        header(
            "Location: "
            . $_SERVER['REQUEST_URI']
        );


        exit;

    } else {

        $erreur =
            "Code d'accès incorrect.";

    }

}


// =====================================================
// PAGE DE CONNEXION
// =====================================================

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
    Administration sécurisée
</title>


<style>

* {

    box-sizing:
        border-box;

}


body {

    margin:
        0;

    min-height:
        100vh;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-family:
        Arial,
        sans-serif;

    background:
        #f5f5f5;

}


.login-box {

    width:
        100%;

    max-width:
        400px;

    background:
        white;

    padding:
        30px;

    border-radius:
        15px;

    box-shadow:
        0 5px 25px
        rgba(0,0,0,0.1);

    text-align:
        center;

}


.login-box h1 {

    margin-top:
        0;

}


.login-box p {

    color:
        #777;

}


input {

    width:
        100%;

    padding:
        14px;

    border:
        1px solid #ddd;

    border-radius:
        8px;

    margin:
        15px 0;

    font-size:
        18px;

    text-align:
        center;

}


button {

    width:
        100%;

    padding:
        14px;

    border:
        none;

    border-radius:
        8px;

    background:
        #222;

    color:
        white;

    font-size:
        16px;

    cursor:
        pointer;

}


.erreur {

    background:
        #ffebee;

    color:
        #c62828;

    padding:
        12px;

    border-radius:
        8px;

    margin-bottom:
        15px;

}

</style>

</head>


<body>


<div class="login-box">


<h1>
    🔐 Administration
</h1>


<p>
    Entrez votre code d'accès
</p>


<?php if (
    !empty($erreur)
): ?>


<div class="erreur">

<?= htmlspecialchars(
    $erreur
) ?>

</div>


<?php endif; ?>


<form method="POST">


<input

    type="password"

    name="code"

    placeholder="Code d'accès"

    inputmode="numeric"

    autocomplete="off"

    required

>


<button
    type="submit"
>

    🔓 Accéder

</button>


</form>


</div>


</body>

</html>


<?php

exit;