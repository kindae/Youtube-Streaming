<?php
require_once __DIR__ . "/inc/bootstrap.php";

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM youtube_videos WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) {
    flash("Vidéo introuvable.", 'error');
    rediriger('videos.php');
}

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();

    // Reprendre le titre et la description depuis YouTube
    if (($_POST['action'] ?? '') === 'recharger') {
        try {
            $item = detailsVideos([$video['youtube_id']])[$video['youtube_id']] ?? null;
            if (!$item) {
                throw new RuntimeException("Vidéo introuvable sur YouTube (supprimée ou privée).");
            }
            $st = $item['statistics'] ?? [];
            $pdo->prepare("
                UPDATE youtube_videos SET titre = ?, description = ?, miniature = ?, duree = ?,
                    vues_youtube = ?, likes_youtube = ?, commentaires_youtube = ?, derniere_sync = NOW()
                WHERE id = ?
            ")->execute([
                $item['snippet']['title'] ?? $video['titre'],
                $item['snippet']['description'] ?? '',
                miniatureDepuis($item),
                $item['contentDetails']['duration'] ?? $video['duree'],
                (int) ($st['viewCount'] ?? 0),
                (int) ($st['likeCount'] ?? 0),
                (int) ($st['commentCount'] ?? 0),
                $id,
            ]);
            flash("Titre, description et statistiques repris depuis YouTube.");
        } catch (Throwable $e) {
            flash($e->getMessage(), 'error');
        }
        rediriger("modifier.php?id=$id");
    }

    $titre = trim((string) ($_POST['titre'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $categorie = mb_substr(trim((string) ($_POST['categorie'] ?? '')), 0, 100);
    $statut = isset($_POST['publie']) ? 'publie' : 'brouillon';
    $vedette = isset($_POST['vedette']) && $statut === 'publie' ? 1 : 0;

    if ($titre === '') {
        $erreurs[] = "Le titre est obligatoire.";
    }

    if (!$erreurs) {
        $pdo->beginTransaction();
        if ($vedette) {
            $pdo->exec("UPDATE youtube_videos SET vedette = 0");
        }
        $pdo->prepare("UPDATE youtube_videos SET titre = ?, description = ?, categorie = ?, statut = ?, vedette = ? WHERE id = ?")
            ->execute([$titre, $description, $categorie, $statut, $vedette, $id]);
        $pdo->commit();

        flash("Modifications enregistrées.");
        rediriger(retourSur("videos.php"));
    }

    $video = array_merge($video, compact('titre', 'description', 'categorie', 'statut', 'vedette'));
}

$categories = listeCategories($pdo);
$retour = retourSur('videos.php');
$titre_page = "Modifier : " . $video['titre'];
$page_active = 'videos';
require __DIR__ . "/inc/header.php";
?>

<header class="page-head">
    <div>
        <a href="<?= e($retour) ?>" class="back-link">← Retour aux vidéos</a>
        <h1>Modifier la vidéo</h1>
    </div>
    <div class="head-actions">
        <a href="../../<?= e(lienVideo($video)) ?>" target="_blank" class="btn btn-ghost">Voir sur le site ↗</a>
    </div>
</header>

<?php foreach ($erreurs as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="edit-layout">
    <form method="post" class="card form" id="edit-form">
        <?= csrfChamp() ?>
        <input type="hidden" name="retour" value="<?= e($retour) ?>">

        <label class="field">
            <span>Titre <em class="counter" data-for="titre"></em></span>
            <input type="text" name="titre" id="titre" value="<?= e($video['titre']) ?>" maxlength="255" required>
        </label>

        <label class="field">
            <span>Description</span>
            <textarea name="description" rows="10"><?= e($video['description']) ?></textarea>
        </label>

        <label class="field">
            <span>Catégorie</span>
            <input type="text" name="categorie" value="<?= e($video['categorie']) ?>" list="liste-categories" placeholder="Ex. Sketch, Parodie…" maxlength="100">
            <small class="hint">Choisissez une catégorie existante ou tapez-en une nouvelle.</small>
        </label>
        <datalist id="liste-categories">
            <?php foreach ($categories as $c): ?><option value="<?= e($c['categorie']) ?>"><?php endforeach; ?>
        </datalist>

        <div class="toggles">
            <label class="toggle">
                <input type="checkbox" name="publie" <?= $video['statut'] === 'publie' ? 'checked' : '' ?>>
                <span class="switch"></span>
                <span><strong>Visible sur le site</strong><small>Décochez pour masquer la vidéo sans la supprimer.</small></span>
            </label>
            <label class="toggle">
                <input type="checkbox" name="vedette" <?= (int) $video['vedette'] === 1 ? 'checked' : '' ?>>
                <span class="switch"></span>
                <span><strong>À la une</strong><small>Mise en avant en haut de l'accueil (une seule vidéo à la fois).</small></span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?= e($retour) ?>" class="btn btn-ghost">Annuler</a>
        </div>
    </form>

    <aside class="edit-side">
        <div class="card">
            <div class="preview lite" data-id="<?= e($video['youtube_id']) ?>">
                <img src="<?= e(miniature($video, 'hqdefault')) ?>" alt="">
                <span class="preview-play">▶</span>
            </div>
            <dl class="facts">
                <div><dt>Vues</dt><dd><?= number_format((int) $video['vues_youtube'], 0, ',', ' ') ?></dd></div>
                <div><dt>J'aime</dt><dd><?= number_format((int) $video['likes_youtube'], 0, ',', ' ') ?></dd></div>
                <div><dt>Commentaires</dt><dd><?= number_format((int) $video['commentaires_youtube'], 0, ',', ' ') ?></dd></div>
                <div><dt>Durée</dt><dd><?= e(formatDuree($video['duree'] ?? '') ?: '—') ?></dd></div>
                <div><dt>Publiée</dt><dd><?= $video['date_youtube'] ? e(date('d/m/Y H:i', strtotime($video['date_youtube']))) : '—' ?></dd></div>
                <div><dt>Dernière synchro</dt><dd><?= !empty($video['derniere_sync']) ? e(tempsEcoule($video['derniere_sync'])) : '—' ?></dd></div>
                <div><dt>ID YouTube</dt><dd><a href="https://www.youtube.com/watch?v=<?= e($video['youtube_id']) ?>" target="_blank" rel="noopener"><?= e($video['youtube_id']) ?> ↗</a></dd></div>
            </dl>
        </div>

        <div class="card side-actions">
            <form method="post" data-loading="Récupération…">
                <?= csrfChamp() ?>
                <button name="action" value="recharger" class="btn btn-ghost btn-block"
                        data-confirm="Remplacer le titre et la description par ceux de YouTube ?">↻ Reprendre les infos de YouTube</button>
            </form>
            <form method="post" action="action.php">
                <?= csrfChamp() ?>
                <input type="hidden" name="id" value="<?= (int) $video['id'] ?>">
                <input type="hidden" name="retour" value="videos.php">
                <button name="action" value="supprimer" class="btn btn-danger-ghost btn-block"
                        data-confirm="Supprimer cette vidéo du site ? Elle restera sur YouTube mais ne sera plus réimportée par la synchronisation. (Pour la cacher temporairement, décochez plutôt « Visible sur le site ».)">Supprimer du site</button>
            </form>
        </div>
    </aside>
</div>

<?php require __DIR__ . "/inc/footer.php"; ?>
