<?php
/*
| Variables attendues : $site, $titre_page, $meta (optionnel : description, image, url, type)
*/
$meta = $meta ?? [];
$meta_description = $meta['description'] ?? $site['description'];
$meta_image = $meta['image'] ?? ($site['url_site'] . '/' . $site['logo']);
$recherche_actuelle = $recherche ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($titre_page) ?></title>
    <meta name="description" content="<?= e(mb_strimwidth($meta_description, 0, 160, '…')) ?>">
    <meta name="theme-color" content="#0f0f12">

    <meta property="og:site_name" content="<?= e($site['nom']) ?>">
    <meta property="og:title" content="<?= e($titre_page) ?>">
    <meta property="og:description" content="<?= e(mb_strimwidth($meta_description, 0, 200, '…')) ?>">
    <meta property="og:image" content="<?= e($meta_image) ?>">
    <meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
    <?php if (!empty($meta['url'])): ?>
    <meta property="og:url" content="<?= e($meta['url']) ?>">
    <link rel="canonical" href="<?= e($meta['url']) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="<?= e($site['logo']) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://i.ytimg.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2">

    <script>
        // Applique le thème avant l'affichage pour éviter le flash
        document.documentElement.classList.add('js');
        try {
            var t = localStorage.getItem('theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
</head>
<body class="<?= e($classe_body ?? '') ?>">

<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand" aria-label="Accueil <?= e($site['nom']) ?>">
            <img src="<?= e($site['logo']) ?>" alt="" class="brand-logo">
            <span class="brand-name"><?= e($site['nom']) ?></span>
        </a>

        <form class="search" action="index.php" method="get" role="search" autocomplete="off">
            <div class="search-field">
                <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="search" name="q" id="search-input" placeholder="Rechercher un sketch, une vidéo…"
                       value="<?= e($recherche_actuelle) ?>" aria-label="Rechercher" aria-controls="search-suggestions" aria-autocomplete="list">
                <button type="button" class="search-close" aria-label="Fermer la recherche">✕</button>
            </div>
            <ul class="suggestions" id="search-suggestions" role="listbox" hidden></ul>
        </form>

        <div class="topbar-actions">
            <button type="button" class="icon-btn search-open" aria-label="Rechercher">
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </button>
            <button type="button" class="icon-btn theme-toggle" aria-label="Changer de thème">
                <svg class="i-moon" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.39 5.39 0 0 1-4.4 2.26 5.4 5.4 0 0 1-3.14-9.8A9.3 9.3 0 0 0 12 3z"/></svg>
                <svg class="i-sun" viewBox="0 0 24 24"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM2 13h2a1 1 0 0 0 0-2H2a1 1 0 0 0 0 2zm18 0h2a1 1 0 0 0 0-2h-2a1 1 0 0 0 0 2zM11 2v2a1 1 0 0 0 2 0V2a1 1 0 0 0-2 0zm0 18v2a1 1 0 0 0 2 0v-2a1 1 0 0 0-2 0zM5.99 4.58a1 1 0 0 0-1.41 1.41l1.06 1.06a1 1 0 0 0 1.41-1.41L5.99 4.58zm12.37 12.37a1 1 0 0 0-1.41 1.41l1.06 1.06a1 1 0 0 0 1.41-1.41l-1.06-1.06zm1.06-10.96a1 1 0 0 0-1.41-1.41l-1.06 1.06a1 1 0 0 0 1.41 1.41l1.06-1.06zM7.05 18.36a1 1 0 0 0-1.41-1.41l-1.06 1.06a1 1 0 0 0 1.41 1.41l1.06-1.06z"/></svg>
            </button>
            <a href="<?= e($site['abonnement']) ?>" target="_blank" rel="noopener" class="btn btn-subscribe">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.5 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>
                <span>S'abonner</span>
            </a>
        </div>
    </div>
</header>

<main class="page">
