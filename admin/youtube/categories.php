<?php
require_once __DIR__ . "/inc/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $ancienne = (string) ($_POST['ancienne'] ?? '');
    $nouvelle = mb_substr(trim((string) ($_POST['nouvelle'] ?? '')), 0, 100);

    if (($_POST['action'] ?? '') === 'supprimer') {
        $stmt = $pdo->prepare("UPDATE youtube_videos SET categorie = '' WHERE categorie = ?");
        $stmt->execute([$ancienne]);
        flash("Catégorie « $ancienne » supprimée (" . $stmt->rowCount() . " vidéo(s) sans catégorie).");
    } elseif ($nouvelle !== '' && $nouvelle !== $ancienne) {
        $stmt = $pdo->prepare("UPDATE youtube_videos SET categorie = ? WHERE categorie = ?");
        $stmt->execute([$nouvelle, $ancienne]);
        flash("« $ancienne » renommée en « $nouvelle » (" . $stmt->rowCount() . " vidéo(s)). Si « $nouvelle » existait déjà, les deux sont fusionnées.");
    }
    rediriger('categories.php');
}

$categories = $pdo->query("
    SELECT categorie, COUNT(*) AS total, SUM(statut = 'publie') AS publiees, COALESCE(SUM(vues_youtube), 0) AS vues
    FROM youtube_videos WHERE categorie IS NOT NULL AND categorie != ''
    GROUP BY categorie ORDER BY total DESC
")->fetchAll();
$sans = (int) $pdo->query("SELECT COUNT(*) FROM youtube_videos WHERE categorie IS NULL OR categorie = ''")->fetchColumn();

$titre_page = "Catégories";
$page_active = 'categories';
require __DIR__ . "/inc/header.php";
?>

<header class="page-head">
    <div>
        <h1>Catégories</h1>
        <p class="muted">Elles apparaissent comme filtres sur l'accueil du site. Pour classer des vidéos, sélectionnez-les dans la liste des vidéos.</p>
    </div>
    <div class="head-actions">
        <a href="videos.php?categorie=__aucune" class="btn btn-ghost">Vidéos sans catégorie (<?= $sans ?>)</a>
    </div>
</header>

<?php if ($categories): ?>
    <div class="cat-grid">
        <?php foreach ($categories as $c): ?>
            <div class="card cat-card">
                <div class="cat-head">
                    <h3><?= e($c['categorie']) ?></h3>
                    <a href="videos.php?categorie=<?= urlencode($c['categorie']) ?>" class="link"><?= (int) $c['total'] ?> vidéo<?= $c['total'] > 1 ? 's' : '' ?> →</a>
                </div>
                <p class="muted"><?= (int) $c['publiees'] ?> en ligne · <?= formatVues($c['vues']) ?> vues</p>
                <form method="post" class="cat-form">
                    <?= csrfChamp() ?>
                    <input type="hidden" name="ancienne" value="<?= e($c['categorie']) ?>">
                    <input type="text" name="nouvelle" value="<?= e($c['categorie']) ?>" aria-label="Nouveau nom" maxlength="100">
                    <button name="action" value="renommer" class="btn btn-ghost btn-sm">Renommer</button>
                    <button name="action" value="supprimer" class="icon-btn danger" title="Supprimer la catégorie"
                            data-confirm="Supprimer la catégorie « <?= e($c['categorie']) ?> » ? Les vidéos ne sont pas supprimées, elles deviennent sans catégorie.">
                        <svg viewBox="0 0 24 24"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty card">
        <div class="empty-icon">🏷️</div>
        <h3>Aucune catégorie</h3>
        <p class="muted">Sélectionnez des vidéos dans la liste et utilisez « Classer » pour créer une catégorie.</p>
        <a href="videos.php" class="btn btn-primary">Aller aux vidéos</a>
    </div>
<?php endif; ?>

<?php require __DIR__ . "/inc/footer.php"; ?>
