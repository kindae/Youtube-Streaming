<?php
/*
|--------------------------------------------------------------------------
| ACTIONS SUR LES VIDÉOS (une ou plusieurs)
|--------------------------------------------------------------------------
| POST : action, ids[] (ou id), categorie (pour l'action « categorie »)
*/
require_once __DIR__ . "/inc/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rediriger('videos.php');
}
verifierCsrf();

$action = $_POST['action'] ?? '';
$ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [$_POST['id'] ?? 0]))));
$retour = retourSur('videos.php');

if (!$ids) {
    flash("Aucune vidéo sélectionnée.", 'error');
    rediriger($retour);
}

$marqueurs = implode(',', array_fill(0, count($ids), '?'));
$n = count($ids);
$s = $n > 1 ? 's' : '';

switch ($action) {
    case 'publier':
        $pdo->prepare("UPDATE youtube_videos SET statut = 'publie' WHERE id IN ($marqueurs)")->execute($ids);
        flash("$n vidéo$s publiée$s.");
        break;

    case 'masquer':
        $pdo->prepare("UPDATE youtube_videos SET statut = 'brouillon', vedette = 0 WHERE id IN ($marqueurs)")->execute($ids);
        flash("$n vidéo$s masquée$s du site.");
        break;

    case 'vedette':
        $pdo->beginTransaction();
        $pdo->exec("UPDATE youtube_videos SET vedette = 0");
        $pdo->prepare("UPDATE youtube_videos SET vedette = 1, statut = 'publie' WHERE id = ?")->execute([$ids[0]]);
        $pdo->commit();
        flash("Vidéo mise à la une.");
        break;

    case 'retirer_vedette':
        $pdo->prepare("UPDATE youtube_videos SET vedette = 0 WHERE id IN ($marqueurs)")->execute($ids);
        flash("Vidéo retirée de la une.");
        break;

    case 'categorie':
        $categorie = mb_substr(trim((string) ($_POST['categorie'] ?? '')), 0, 100);
        $pdo->prepare("UPDATE youtube_videos SET categorie = ? WHERE id IN ($marqueurs)")->execute(array_merge([$categorie], $ids));
        flash($categorie === '' ? "Catégorie retirée de $n vidéo$s." : "$n vidéo$s classée$s dans « $categorie ».");
        break;

    case 'supprimer':
        $stmt = $pdo->prepare("SELECT youtube_id FROM youtube_videos WHERE id IN ($marqueurs)");
        $stmt->execute($ids);
        modifierExclusions($stmt->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare("DELETE FROM youtube_videos WHERE id IN ($marqueurs)")->execute($ids);
        flash("$n vidéo$s supprimée$s du site (elle$s reste" . ($n > 1 ? "nt" : "") . " sur YouTube).");
        if (strpos($retour, 'modifier.php') === 0) {
            $retour = 'videos.php';
        }
        break;

    default:
        flash("Action inconnue.", 'error');
}

rediriger($retour);
