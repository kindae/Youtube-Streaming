<?php
require_once __DIR__ . "/includes/app.php";

/*
|--------------------------------------------------------------------------
| VIDÉO DEMANDÉE
|--------------------------------------------------------------------------
*/
$youtube_id = $_GET['v'] ?? '';
$video = null;

if (idYoutubeValide($youtube_id)) {
    $stmt = $pdo->prepare("SELECT * FROM youtube_videos WHERE youtube_id = ? AND statut = 'publie' LIMIT 1");
    $stmt->execute([$youtube_id]);
    $video = $stmt->fetch();
}

if (!$video) {
    http_response_code(404);
    $titre_page = "Vidéo introuvable – " . $site['nom'];
    require __DIR__ . "/includes/header.php";
    ?>
    <div class="container">
        <div class="empty">
            <div class="empty-icon">📼</div>
            <h1>Vidéo introuvable</h1>
            <p>Cette vidéo n'existe pas ou n'est plus disponible.</p>
            <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
        </div>
    </div>
    <?php
    require __DIR__ . "/includes/footer.php";
    exit;
}

/*
|--------------------------------------------------------------------------
| VIDÉOS SUIVANTES : même catégorie d'abord, puis les plus récentes
|--------------------------------------------------------------------------
*/
$limite = 14;
$suggestions = [];

if (!empty($video['categorie'])) {
    $stmt = $pdo->prepare("SELECT * FROM youtube_videos WHERE statut = 'publie' AND categorie = ? AND id != ? ORDER BY date_youtube DESC, id DESC LIMIT $limite");
    $stmt->execute([$video['categorie'], $video['id']]);
    $suggestions = $stmt->fetchAll();
}

if (count($suggestions) < $limite) {
    $exclus = array_merge([$video['id']], array_column($suggestions, 'id'));
    $marqueurs = implode(',', array_fill(0, count($exclus), '?'));
    $reste = $limite - count($suggestions);
    $stmt = $pdo->prepare("SELECT * FROM youtube_videos WHERE statut = 'publie' AND id NOT IN ($marqueurs) ORDER BY date_youtube DESC, id DESC LIMIT $reste");
    $stmt->execute($exclus);
    $suggestions = array_merge($suggestions, $stmt->fetchAll());
}

$suivante = $suggestions[0] ?? null;

/*
|--------------------------------------------------------------------------
| AFFICHAGE
|--------------------------------------------------------------------------
*/
$url_page = $site['url_site'] . '/' . lienVideo($video);
$titre_page = $video['titre'] . ' – ' . $site['nom'];
$meta = [
    'description' => $video['description'] ?: $video['titre'],
    'image'       => miniature($video, 'hqdefault'),
    'url'         => $url_page,
    'type'        => 'video.other',
];
$classe_body = 'page-watch';
$date_publication = !empty($video['date_youtube']) ? strtotime($video['date_youtube']) : null;
$mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

require __DIR__ . "/includes/header.php";
?>

<div class="watch-layout">
    <div class="watch-main">

        <!-- LECTEUR -->
        <div class="player-wrap">
            <div class="player" id="player"
                 data-id="<?= e($video['youtube_id']) ?>"
                 data-next="<?= $suivante ? e(lienVideo($suivante)) : '' ?>">
                <iframe id="player-frame"
                        src="https://www.youtube-nocookie.com/embed/<?= e($video['youtube_id']) ?>?autoplay=1&rel=0&modestbranding=1&playsinline=1&enablejsapi=1"
                        title="<?= e($video['titre']) ?>"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen></iframe>
            </div>

            <?php if ($suivante): ?>
                <div class="upnext-overlay" hidden>
                    <p>Vidéo suivante dans <strong class="upnext-count">8</strong></p>
                    <div class="upnext-card">
                        <img src="<?= e(miniature($suivante)) ?>" alt="">
                        <span><?= e($suivante['titre']) ?></span>
                    </div>
                    <div class="upnext-actions">
                        <button type="button" class="btn btn-ghost upnext-cancel">Annuler</button>
                        <a href="<?= e(lienVideo($suivante)) ?>" class="btn btn-primary">Lire maintenant</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- INFOS -->
        <h1 class="watch-title"><?= e($video['titre']) ?></h1>

        <div class="watch-bar">
            <div class="watch-channel">
                <img src="<?= e($site['logo']) ?>" alt="" class="brand-logo">
                <div>
                    <strong><?= e($site['nom']) ?></strong>
                    <small><?= e($site['slogan']) ?></small>
                </div>
                <a href="<?= e($site['abonnement']) ?>" target="_blank" rel="noopener" class="btn btn-subscribe">S'abonner</a>
            </div>

            <div class="watch-actions">
                <span class="pill">👍 <?= formatVues($video['likes_youtube']) ?></span>
                <button type="button" class="pill pill-btn share-btn"
                        data-url="<?= e($url_page) ?>" data-title="<?= e($video['titre']) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16.1c-.8 0-1.4.3-2 .8l-7.1-4.2c.1-.2.1-.5.1-.7s0-.5-.1-.7L16 7.2c.5.5 1.2.8 2 .8a3 3 0 1 0-3-3c0 .2 0 .5.1.7L8 9.8A3 3 0 0 0 3 12a3 3 0 0 0 5 2.2l7.1 4.2c-.1.2-.1.4-.1.6a2.9 2.9 0 1 0 3-2.9z"/></svg>
                    Partager
                </button>
                <button type="button" class="pill pill-btn autoplay-toggle" aria-pressed="true" title="Lire automatiquement la vidéo suivante">
                    <span class="switch" aria-hidden="true"></span> Lecture auto
                </button>
            </div>
        </div>

        <!-- PARTAGE -->
        <div class="share-panel" hidden>
            <a target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($video['titre'] . ' ' . $url_page) ?>" class="share share-wa">WhatsApp</a>
            <a target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($url_page) ?>" class="share share-fb">Facebook</a>
            <a target="_blank" rel="noopener" href="https://x.com/intent/tweet?text=<?= rawurlencode($video['titre']) ?>&url=<?= rawurlencode($url_page) ?>" class="share share-x">X</a>
            <a target="_blank" rel="noopener" href="https://t.me/share/url?url=<?= rawurlencode($url_page) ?>&text=<?= rawurlencode($video['titre']) ?>" class="share share-tg">Telegram</a>
            <div class="share-copy">
                <input type="text" readonly value="<?= e($url_page) ?>" aria-label="Lien de la vidéo">
                <button type="button" class="btn btn-primary copy-link">Copier</button>
            </div>
        </div>

        <!-- DESCRIPTION -->
        <div class="description collapsed">
            <p class="description-meta">
                <strong><?= number_format((int) $video['vues_youtube'], 0, ',', ' ') ?> vues</strong>
                <?php if ($date_publication): ?>
                    · <?= date('j', $date_publication) . ' ' . $mois[date('n', $date_publication) - 1] . ' ' . date('Y', $date_publication) ?>
                <?php endif; ?>
                <?php if (!empty($video['categorie'])): ?>
                    · <a href="<?= e(urlAccueil(['categorie' => $video['categorie']])) ?>" class="tag-link">#<?= e($video['categorie']) ?></a>
                <?php endif; ?>
                · 💬 <?= formatVues($video['commentaires_youtube']) ?> commentaires
            </p>
            <?php if (!empty(trim($video['description'] ?? ''))): ?>
                <div class="description-text"><?= formaterDescription($video['description']) ?></div>
                <button type="button" class="description-toggle">Afficher plus</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- À SUIVRE -->
    <aside class="watch-side" aria-label="Vidéos suivantes">
        <h2 class="side-title">À suivre</h2>
        <div class="side-list">
            <?php foreach ($suggestions as $s) afficherCarteVideo($s, 'compact'); ?>
        </div>
        <a href="index.php" class="btn btn-ghost btn-block">Toutes les vidéos</a>
    </aside>
</div>

<?php
require __DIR__ . "/includes/footer.php";
