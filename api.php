<?php
require_once __DIR__ . "/includes/app.php";

/*
|--------------------------------------------------------------------------
| SUGGESTIONS DE RECHERCHE (JSON)
|--------------------------------------------------------------------------
| api.php?action=suggestions&q=texte
*/
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';
$q = trim($_GET['q'] ?? '');

if ($action !== 'suggestions' || mb_strlen($q) < 2) {
    echo json_encode(['resultats' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT youtube_id, titre, miniature, vues_youtube, duree
    FROM youtube_videos
    WHERE statut = 'publie' AND titre LIKE ?
    ORDER BY vues_youtube DESC
    LIMIT 8
");
$stmt->execute(['%' . $q . '%']);

$resultats = array_map(function ($v) {
    return [
        'url'       => lienVideo($v),
        'titre'     => $v['titre'],
        'miniature' => miniature($v),
        'vues'      => formatVues($v['vues_youtube']) . ' vues',
        'duree'     => formatDuree($v['duree']),
    ];
}, $stmt->fetchAll());

echo json_encode(['resultats' => $resultats], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
