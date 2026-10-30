<?php
/*
|--------------------------------------------------------------------------
| SOCLE DE L'ADMINISTRATION
|--------------------------------------------------------------------------
*/
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../../../includes/app.php";
require_once __DIR__ . "/../../../config/youtube_sync_lib.php";

// Jeton anti-CSRF pour tous les formulaires
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrfChamp()
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function verifierCsrf()
{
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit("Session expirée : rechargez la page et réessayez.");
    }
}

// Messages affichés après une redirection
function flash($message, $type = 'success')
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function rediriger($url)
{
    header("Location: " . $url);
    exit;
}

// Ne garde que les redirections internes à l'administration
function retourSur($defaut = 'index.php')
{
    $retour = (string) ($_POST['retour'] ?? $_GET['retour'] ?? '');
    return preg_match('~^[a-z_]+\.php(\?[^\s]*)?$~i', $retour) ? $retour : $defaut;
}

function listeCategories(PDO $pdo)
{
    return $pdo->query("SELECT categorie, COUNT(*) AS total FROM youtube_videos WHERE categorie IS NOT NULL AND categorie != '' GROUP BY categorie ORDER BY categorie")->fetchAll();
}

const STATUTS = ['publie' => 'Publiée', 'brouillon' => 'Masquée'];
