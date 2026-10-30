<?php
/* Variables : $titre_page, $page_active */
$menu = [
    'index'       => ['Tableau de bord', '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>'],
    'videos'      => ['Vidéos', '<path d="M4 6H2v14a2 2 0 0 0 2 2h14v-2H4V6zm16-4H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-8 12.5v-9l6 4.5-6 4.5z"/>'],
    'ajouter'     => ['Ajouter', '<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>'],
    'categories'  => ['Catégories', '<path d="M21.4 11.6 12.4 2.6A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7c0 .55.22 1.05.59 1.42l9 9a2 2 0 0 0 2.82 0l7-7a2 2 0 0 0-.01-2.82zM5.5 7A1.5 1.5 0 1 1 7 5.5 1.5 1.5 0 0 1 5.5 7z"/>'],
    'synchro'     => ['Synchronisation', '<path d="M12 4V1L8 5l4 4V6a6 6 0 0 1 5.65 8.03l1.46 1.46A8 8 0 0 0 12 4zm0 14a6 6 0 0 1-5.65-8.03L4.89 8.51A8 8 0 0 0 12 20v3l4-4-4-4v3z"/>'],
];
$liens_menu = ['index' => 'index.php', 'videos' => 'videos.php', 'ajouter' => 'ajouter.php', 'categories' => 'categories.php', 'synchro' => 'synchroniser.php'];
$flashs = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titre_page) ?> – Studio Congonet 24 +</title>
    <link rel="icon" href="../../auto.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=1">
    <script>
        try { var t = localStorage.getItem('theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
    </script>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <a href="index.php" class="side-brand">
            <img src="../../auto.jpg" alt="">
            <span>Studio<small>Congonet 24 +</small></span>
        </a>
        <nav class="side-nav">
            <?php foreach ($menu as $cle => [$libelle, $icone]): ?>
                <a href="<?= $liens_menu[$cle] ?>" class="<?= ($page_active ?? '') === $cle ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24"><?= $icone ?></svg><span><?= $libelle ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="side-bottom">
            <a href="../../index.php" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24"><path d="M14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7zm5 16H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7z"/></svg><span>Voir le site</span>
            </a>
            <button type="button" class="theme-toggle">
                <svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.39 5.39 0 0 1-4.4 2.26 5.4 5.4 0 0 1-3.14-9.8A9.3 9.3 0 0 0 12 3z"/></svg><span>Thème</span>
            </button>
            <a href="?logout=1" class="logout">
                <svg viewBox="0 0 24 24"><path d="M10.09 15.59 11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5a2 2 0 0 0-2 2v4h2V5h14v14H5v-4H3v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/></svg><span>Déconnexion</span>
            </a>
        </div>
    </aside>

    <main class="main">
        <div class="toasts">
            <?php foreach ($flashs as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?><button type="button" class="alert-close" aria-label="Fermer">✕</button></div>
            <?php endforeach; ?>
        </div>
