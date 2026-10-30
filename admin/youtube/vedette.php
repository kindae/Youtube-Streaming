<?php
require_once "auth.php";

require_once "../../config/database.php";

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}


// Retirer la vedette actuelle

$pdo->exec("
    UPDATE youtube_videos
    SET vedette = 0
");


// Mettre la nouvelle vidéo en vedette

$stmt = $pdo->prepare("
    UPDATE youtube_videos
    SET vedette = 1
    WHERE id = ?
");

$stmt->execute([$id]);


header("Location: index.php");

exit;