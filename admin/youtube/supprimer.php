<?php
require_once "auth.php";

require_once "../../config/database.php";


// Récupérer l'ID
$id = intval($_GET['id'] ?? 0);


// Vérifier l'ID
if ($id <= 0) {

    header("Location: index.php");
    exit;

}


// Vérifier que la vidéo existe
$stmt = $pdo->prepare("

    SELECT id

    FROM youtube_videos

    WHERE id = ?

    LIMIT 1

");


$stmt->execute([$id]);


$video = $stmt->fetch();


if (!$video) {

    header("Location: index.php");
    exit;

}


// Supprimer la vidéo
$delete = $pdo->prepare("

    DELETE FROM youtube_videos

    WHERE id = ?

");


$delete->execute([$id]);


// Retour à l'administration
header(
    "Location: index.php"
);

exit;