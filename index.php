<?php
require_once __DIR__ . "/includes/app.php";

/*
|--------------------------------------------------------------------------
| RECHERCHE, FILTRES ET TRI
|--------------------------------------------------------------------------
*/
$recherche = trim($_GET['q'] ?? '');
$categorie = trim($_GET['categorie'] ?? '');
$tri = $_GET['tri'] ?? 'recent';
$page = max(1, intval($_GET['page'] ?? 1));
$par_page = 12;

$tris = [
    'recent'    => ['Plus récentes', 'date_youtube DESC, id DESC'],
    'populaire' => ['Plus vues', 'vues_youtube DESC, id DESC'],
    'aime'      => ['Plus aimées', 'likes_youtube DESC, id DESC'],
    'ancien'    => ['Plus anciennes', 'date_youtube ASC, id ASC'],
];
if (!isset($tris[$tri])) {
    $tri = 'recent';
}

$where = ["statut = 'publie'"];
$params = [];

if ($recherche !== '') {
    $where[] = "(titre LIKE ? OR description LIKE ?)";
    $params[] = "%" . $recherche . "%";
    $params[] = "%" . $recherche . "%";
}

if ($categorie !== '') {
    $where[] = "categorie = ?";
    $params[] = $categorie;
}

$where_sql = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| TOTAL & PAGINATION
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT COUNT(*) FROM youtube_videos WHERE $where_sql");
$stmt->execute($params);
$total_resultats = (int) $stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_resultats / $par_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare("SELECT * FROM youtube_videos WHERE $where_sql ORDER BY {$tris[$tri][1]} LIMIT $par_page OFFSET $offset");
$stmt->execute($params);
$videos = $stmt->fetchAll();

$accueil = ($recherche === '' && $categorie === '' && $page === 1 && $tri === 'recent');

/*
|--------------------------------------------------------------------------
| VIDÉO À LA UNE, POPULAIRES, STATISTIQUES, CATÉGORIES
|--------------------------------------------------------------------------
*/
$video_vedette = null;
$videos_populaires = [];

if ($accueil) {
    $video_vedette = $pdo->query("SELECT * FROM youtube_videos WHERE vedette = 1 AND statut = 'publie' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$video_vedette) {
        $video_vedette = $pdo->query("SELECT * FROM youtube_videos WHERE statut = 'publie' ORDER BY date_youtube DESC, id DESC LIMIT 1")->fetch();
    }
    $videos_populaires = $pdo->query("SELECT * FROM youtube_videos WHERE statut = 'publie' ORDER BY vues_youtube DESC LIMIT 10")->fetchAll();
}

$stats = $pdo->query("SELECT COUNT(*) AS total_videos, COALESCE(SUM(vues_youtube), 0) AS total_vues, COALESCE(SUM(likes_youtube), 0) AS total_likes FROM youtube_videos WHERE statut = 'publie'")->fetch();

$categories = $pdo->query("SELECT categorie, COUNT(*) AS total FROM youtube_videos WHERE statut = 'publie' AND categorie IS NOT NULL AND categorie != '' GROUP BY categorie ORDER BY total DESC")->fetchAll();

/*
|--------------------------------------------------------------------------
| AFFICHAGE
|--------------------------------------------------------------------------
*/
$filtres = ['q' => $recherche, 'categorie' => $categorie, 'tri' => $tri === 'recent' ? '' : $tri];

if ($recherche !== '') {
    $titre_section = 'Résultats pour « ' . $recherche . ' »';
} elseif ($categorie !== '') {
    $titre_section = $categorie;
} else {
    $titre_section = $tri === 'recent' ? 'Dernières vidéos' : $tris[$tri][0];
}

$titre_page = $site['nom'] . ' – ' . ($accueil ? $site['slogan'] : $titre_section);
$meta = ['url' => $site['url_site'] . '/' . urlAccueil($filtres + ['page' => $page])];

require __DIR__ . "/includes/header.php";
?>

<?php if ($accueil): ?>
    <!-- EN-TÊTE DE LA CHAÎNE -->
    <section class="channel">
        <div class="channel-banner" aria-hidden="true"></div>
        <div class="container channel-inner">
            <img src="<?= e($site['logo']) ?>" alt="Logo <?= e($site['nom']) ?>" class="channel-logo">
            <div class="channel-text">
                <h1><?= e($site['nom']) ?> <span class="tag">Humour</span></h1>
                <p class="channel-stats">
                    <span><strong><?= formatVues($stats['total_videos']) ?></strong> vidéos</span>
                    <span><strong><?= formatVues($stats['total_vues']) ?></strong> vues</span>
                    <span><strong><?= formatVues($stats['total_likes']) ?></strong> j'aime</span>
                </p>
                <p class="channel-desc"><?= e($site['description']) ?></p>
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="container">

    <!-- CATÉGORIES & TRI -->
    <nav class="filters" aria-label="Catégories">
        <div class="chips">
            <a href="<?= e(urlAccueil(['q' => $recherche, 'tri' => $filtres['tri']])) ?>" class="chip <?= $categorie === '' ? 'active' : '' ?>">Tout</a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= e(urlAccueil(['q' => $recherche, 'categorie' => $cat['categorie'], 'tri' => $filtres['tri']])) ?>"
                   class="chip <?= $categorie === $cat['categorie'] ? 'active' : '' ?>">
                    <?= e($cat['categorie']) ?> <small><?= (int) $cat['total'] ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <?php if ($video_vedette): ?>
        <!-- VIDÉO À LA UNE : lecture directement sur la page -->
        <section class="featured" aria-labelledby="featured-title">
            <div class="featured-player lite-player" data-id="<?= e($video_vedette['youtube_id']) ?>">
                <img src="<?= e(miniature($video_vedette, 'hqdefault')) ?>" alt="" fetchpriority="high">
                <button type="button" class="lite-play" aria-label="Lire « <?= e($video_vedette['titre']) ?> »">
                    <svg viewBox="0 0 68 48"><path class="bg" d="M66.5 7.7c-.8-2.9-2.5-5.4-5.4-6.2C55.8.1 34 0 34 0S12.2.1 6.9 1.6C4 2.4 2.3 4.8 1.5 7.7 0 13 0 24 0 24s0 11 1.5 16.3c.8 2.9 2.5 5.4 5.4 6.2C12.2 47.9 34 48 34 48s21.8-.1 27.1-1.6c2.9-.8 4.6-3.2 5.4-6.2C68 35 68 24 68 24s0-11-1.5-16.3z"/><path d="M45 24 27 14v20z" fill="#fff"/></svg>
                </button>
            </div>
            <div class="featured-body">
                <span class="eyebrow"><?= (int) ($video_vedette['vedette'] ?? 0) === 1 ? '⭐ À la une' : '🆕 Nouveauté' ?></span>
                <h2 id="featured-title"><a href="<?= e(lienVideo($video_vedette)) ?>"><?= e($video_vedette['titre']) ?></a></h2>
                <p class="featured-meta">
                    <?= formatVues($video_vedette['vues_youtube']) ?> vues
                    <?php if ($d = tempsEcoule($video_vedette['date_youtube'])): ?> · <?= e($d) ?><?php endif; ?>
                </p>
                <?php if (!empty($video_vedette['description'])): ?>
                    <p class="featured-desc"><?= e(mb_strimwidth($video_vedette['description'], 0, 220, '…')) ?></p>
                <?php endif; ?>
                <div class="featured-actions">
                    <a href="<?= e(lienVideo($video_vedette)) ?>" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> Regarder
                    </a>
                    <span class="pill">👍 <?= formatVues($video_vedette['likes_youtube']) ?></span>
                    <span class="pill">💬 <?= formatVues($video_vedette['commentaires_youtube']) ?></span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($videos_populaires)): ?>
        <!-- LES PLUS VUES -->
        <section class="row-section" aria-labelledby="pop-title">
            <div class="section-head">
                <h2 id="pop-title">🔥 Les plus vues</h2>
                <div class="row-nav">
                    <a href="<?= e(urlAccueil(['tri' => 'populaire'])) ?>" class="link-more">Tout voir</a>
                    <button type="button" class="icon-btn row-prev" aria-label="Précédent"><svg viewBox="0 0 24 24"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4L10.8 12z"/></svg></button>
                    <button type="button" class="icon-btn row-next" aria-label="Suivant"><svg viewBox="0 0 24 24"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z"/></svg></button>
                </div>
            </div>
            <div class="video-row">
                <?php foreach ($videos_populaires as $i => $video): ?>
                    <div class="row-item">
                        <span class="rank"><?= $i + 1 ?></span>
                        <?php afficherCarteVideo($video); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- LISTE PRINCIPALE -->
    <section aria-labelledby="list-title">
        <div class="section-head">
            <h2 id="list-title">
                <?= e($titre_section) ?>
                <small><?= number_format($total_resultats, 0, ',', ' ') ?> vidéo<?= $total_resultats > 1 ? 's' : '' ?></small>
            </h2>
            <form class="sort" method="get" action="index.php">
                <?php if ($recherche !== ''): ?><input type="hidden" name="q" value="<?= e($recherche) ?>"><?php endif; ?>
                <?php if ($categorie !== ''): ?><input type="hidden" name="categorie" value="<?= e($categorie) ?>"><?php endif; ?>
                <label for="tri" class="visually-hidden">Trier par</label>
                <select name="tri" id="tri" onchange="this.form.submit()">
                    <?php foreach ($tris as $cle => $t): ?>
                        <option value="<?= e($cle) ?>" <?= $cle === $tri ? 'selected' : '' ?>><?= e($t[0]) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn-ghost">OK</button></noscript>
            </form>
        </div>

        <?php if (!empty($videos)): ?>
            <div class="video-grid" id="video-grid">
                <?php foreach ($videos as $video) afficherCarteVideo($video); ?>
            </div>

            <?php if ($page < $total_pages): ?>
                <div class="load-more-wrap">
                    <a href="<?= e(urlAccueil($filtres + ['page' => $page + 1])) ?>" class="btn btn-ghost load-more" data-grid="#video-grid">
                        Afficher plus de vidéos
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(urlAccueil($filtres + ['page' => $page - 1])) ?>" aria-label="Page précédente">‹</a>
                    <?php endif; ?>
                    <?php
                    $precedent = 0;
                    for ($i = 1; $i <= $total_pages; $i++):
                        if ($i !== 1 && $i !== $total_pages && abs($i - $page) > 2) continue;
                        if ($precedent && $i - $precedent > 1): ?>
                            <span class="dots">…</span>
                        <?php endif;
                        $precedent = $i; ?>
                        <a href="<?= e(urlAccueil($filtres + ['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= e(urlAccueil($filtres + ['page' => $page + 1])) ?>" aria-label="Page suivante">›</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty">
                <div class="empty-icon">🎬</div>
                <h3>Aucune vidéo trouvée</h3>
                <p>Essayez un autre mot-clé ou parcourez toutes nos vidéos.</p>
                <a href="index.php" class="btn btn-primary">Voir toutes les vidéos</a>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
