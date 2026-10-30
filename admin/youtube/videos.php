<?php
require_once __DIR__ . "/inc/bootstrap.php";

/*
|--------------------------------------------------------------------------
| FILTRES
|--------------------------------------------------------------------------
*/
$q = trim($_GET['q'] ?? '');
$statut = $_GET['statut'] ?? '';
$categorie = $_GET['categorie'] ?? '';
$tri = $_GET['tri'] ?? 'date';
$page = max(1, (int) ($_GET['page'] ?? 1));
$par_page = 30;

$tris = [
    'date'   => ['Plus récentes', 'date_youtube DESC, id DESC'],
    'ancien' => ['Plus anciennes', 'date_youtube ASC, id ASC'],
    'vues'   => ['Plus vues', 'vues_youtube DESC'],
    'likes'  => ['Plus aimées', 'likes_youtube DESC'],
    'titre'  => ['Titre A → Z', 'titre ASC'],
    'ajout'  => ['Ajout récent', 'id DESC'],
];
if (!isset($tris[$tri])) $tri = 'date';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(titre LIKE ? OR description LIKE ? OR youtube_id = ?)";
    array_push($params, "%$q%", "%$q%", extraireIdYoutube($q) ?? $q);
}
if (isset(STATUTS[$statut])) {
    $where[] = $statut === 'publie' ? "statut = 'publie'" : "statut != 'publie'";
}
if ($categorie === '__aucune') {
    $where[] = "(categorie IS NULL OR categorie = '')";
} elseif ($categorie !== '') {
    $where[] = "categorie = ?";
    $params[] = $categorie;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM youtube_videos $where_sql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $par_page));
$page = min($page, $pages);
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare("SELECT * FROM youtube_videos $where_sql ORDER BY {$tris[$tri][1]} LIMIT $par_page OFFSET $offset");
$stmt->execute($params);
$videos = $stmt->fetchAll();

$categories = listeCategories($pdo);
$filtres = array_filter(['q' => $q, 'statut' => $statut, 'categorie' => $categorie, 'tri' => $tri === 'date' ? '' : $tri]);
$url = function ($extra = []) use ($filtres) {
    $p = array_filter(array_merge($filtres, $extra), function ($v) { return $v !== '' && $v !== null; });
    return 'videos.php' . ($p ? '?' . http_build_query($p) : '');
};
$retour = basename($url($page > 1 ? ['page' => $page] : []));

$titre_page = "Vidéos";
$page_active = 'videos';
require __DIR__ . "/inc/header.php";
?>

<header class="page-head">
    <div>
        <h1>Vidéos</h1>
        <p class="muted"><?= number_format($total, 0, ',', ' ') ?> vidéo<?= $total > 1 ? 's' : '' ?><?= $filtres ? ' correspondant aux filtres' : '' ?></p>
    </div>
    <div class="head-actions">
        <a href="ajouter.php" class="btn btn-primary"><svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Ajouter</a>
    </div>
</header>

<!-- BARRE DE FILTRES -->
<form class="toolbar" method="get" data-autosubmit>
    <label class="search-box">
        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Titre, description ou lien YouTube…">
    </label>
    <select name="statut" aria-label="Statut">
        <option value="">Tous les statuts</option>
        <?php foreach (STATUTS as $cle => $libelle): ?>
            <option value="<?= $cle ?>" <?= $statut === $cle ? 'selected' : '' ?>><?= $libelle ?>s</option>
        <?php endforeach; ?>
    </select>
    <select name="categorie" aria-label="Catégorie">
        <option value="">Toutes les catégories</option>
        <option value="__aucune" <?= $categorie === '__aucune' ? 'selected' : '' ?>>— Sans catégorie —</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['categorie']) ?>" <?= $categorie === $c['categorie'] ? 'selected' : '' ?>><?= e($c['categorie']) ?> (<?= (int) $c['total'] ?>)</option>
        <?php endforeach; ?>
    </select>
    <select name="tri" aria-label="Trier">
        <?php foreach ($tris as $cle => $t): ?>
            <option value="<?= $cle ?>" <?= $tri === $cle ? 'selected' : '' ?>><?= $t[0] ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-ghost">Filtrer</button></noscript>
    <?php if ($filtres): ?><a href="videos.php" class="btn btn-ghost btn-sm">Effacer</a><?php endif; ?>
</form>

<?php if ($videos): ?>
<form method="post" action="action.php" id="bulk-form">
    <?= csrfChamp() ?>
    <input type="hidden" name="retour" value="<?= e($retour) ?>">

    <!-- ACTIONS GROUPÉES -->
    <div class="bulk-bar" hidden>
        <strong><span class="bulk-count">0</span> sélectionnée(s)</strong>
        <button name="action" value="publier" class="btn btn-ghost btn-sm">Publier</button>
        <button name="action" value="masquer" class="btn btn-ghost btn-sm">Masquer</button>
        <span class="bulk-cat">
            <input type="text" name="categorie" list="liste-categories" placeholder="Catégorie…" aria-label="Catégorie">
            <button name="action" value="categorie" class="btn btn-ghost btn-sm">Classer</button>
        </span>
        <button name="action" value="supprimer" class="btn btn-danger btn-sm" data-confirm="Supprimer les vidéos sélectionnées du site ? (elles restent sur YouTube)">Supprimer</button>
    </div>

    <div class="table">
        <div class="table-head">
            <label class="check"><input type="checkbox" class="check-all" aria-label="Tout sélectionner"></label>
            <span>Vidéo</span>
            <span>Catégorie</span>
            <span class="num">Vues</span>
            <span class="num">J'aime</span>
            <span>Statut</span>
            <span></span>
        </div>

        <?php foreach ($videos as $v):
            $publiee = $v['statut'] === 'publie';
            $duree = formatDuree($v['duree'] ?? ''); ?>
            <div class="table-row <?= $publiee ? '' : 'is-hidden' ?>">
                <label class="check"><input type="checkbox" name="ids[]" value="<?= (int) $v['id'] ?>" aria-label="Sélectionner"></label>

                <a href="modifier.php?id=<?= (int) $v['id'] ?>" class="cell-video">
                    <span class="thumb">
                        <img src="<?= e(miniature($v)) ?>" alt="" loading="lazy">
                        <?php if ($duree): ?><span class="duration"><?= e($duree) ?></span><?php endif; ?>
                    </span>
                    <span class="cell-text">
                        <strong><?= (int) $v['vedette'] === 1 ? '⭐ ' : '' ?><?= e($v['titre']) ?></strong>
                        <small><?= e(tempsEcoule($v['date_youtube']) ?: 'date inconnue') ?></small>
                    </span>
                </a>

                <span class="cell-cat">
                    <?php if (!empty($v['categorie'])): ?>
                        <a href="<?= e($url(['categorie' => $v['categorie'], 'page' => null])) ?>" class="badge"><?= e($v['categorie']) ?></a>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </span>
                <span class="num" data-label="Vues"><?= formatVues($v['vues_youtube']) ?></span>
                <span class="num" data-label="J'aime"><?= formatVues($v['likes_youtube']) ?></span>

                <span>
                    <button type="submit" form="f-statut-<?= (int) $v['id'] ?>" class="switch-btn" role="switch" aria-checked="<?= $publiee ? 'true' : 'false' ?>"
                            title="<?= $publiee ? 'En ligne – cliquer pour masquer' : 'Masquée – cliquer pour publier' ?>">
                        <span class="switch"></span><span class="switch-label"><?= $publiee ? 'En ligne' : 'Masquée' ?></span>
                    </button>
                </span>

                <span class="cell-actions">
                    <button type="submit" form="f-vedette-<?= (int) $v['id'] ?>" class="icon-btn <?= (int) $v['vedette'] === 1 ? 'is-star' : '' ?>"
                            title="<?= (int) $v['vedette'] === 1 ? 'Retirer de la une' : 'Mettre à la une' ?>"><?= (int) $v['vedette'] === 1 ? '★' : '☆' ?></button>
                    <a href="modifier.php?id=<?= (int) $v['id'] ?>" class="icon-btn" title="Modifier">
                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                    </a>
                    <a href="../../<?= e(lienVideo($v)) ?>" target="_blank" class="icon-btn" title="Voir sur le site">
                        <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                    </a>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</form>

<?php // Formulaires individuels (hors du formulaire groupé) ?>
<?php foreach ($videos as $v): ?>
    <form method="post" action="action.php" id="f-statut-<?= (int) $v['id'] ?>" hidden>
        <?= csrfChamp() ?>
        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
        <input type="hidden" name="action" value="<?= $v['statut'] === 'publie' ? 'masquer' : 'publier' ?>">
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
    </form>
    <form method="post" action="action.php" id="f-vedette-<?= (int) $v['id'] ?>" hidden>
        <?= csrfChamp() ?>
        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
        <input type="hidden" name="action" value="<?= (int) $v['vedette'] === 1 ? 'retirer_vedette' : 'vedette' ?>">
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
    </form>
<?php endforeach; ?>

<datalist id="liste-categories">
    <?php foreach ($categories as $c): ?><option value="<?= e($c['categorie']) ?>"><?php endforeach; ?>
</datalist>

<?php if ($pages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?><a href="<?= e($url(['page' => $page - 1])) ?>">‹ Précédent</a><?php endif; ?>
        <span class="muted">Page <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= e($url(['page' => $page + 1])) ?>">Suivant ›</a><?php endif; ?>
    </nav>
<?php endif; ?>

<?php else: ?>
    <div class="empty card">
        <div class="empty-icon">🎬</div>
        <h3>Aucune vidéo trouvée</h3>
        <p class="muted"><?= $filtres ? 'Modifiez ou effacez les filtres.' : 'Ajoutez une vidéo ou lancez une synchronisation.' ?></p>
        <?php if ($filtres): ?><a href="videos.php" class="btn btn-ghost">Effacer les filtres</a><?php else: ?><a href="ajouter.php" class="btn btn-primary">Ajouter une vidéo</a><?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . "/inc/footer.php"; ?>
