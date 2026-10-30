<?php
require_once __DIR__ . "/inc/bootstrap.php";

$liens = '';
$categorie = '';
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();

    $liens = (string) ($_POST['liens'] ?? '');
    $categorie = mb_substr(trim((string) ($_POST['categorie'] ?? '')), 0, 100);
    $statut = isset($_POST['publie']) ? 'publie' : 'brouillon';

    // Un lien (ou ID) par ligne, virgule ou espace
    $ids = [];
    $invalides = [];
    foreach (preg_split('/[\s,]+/', $liens, -1, PREG_SPLIT_NO_EMPTY) as $morceau) {
        $id = extraireIdYoutube($morceau);
        if ($id) {
            $ids[] = $id;
        } else {
            $invalides[] = $morceau;
        }
    }
    $ids = array_values(array_unique($ids));

    if (!$ids) {
        $erreurs[] = "Collez au moins un lien YouTube valide.";
    } else {
        try {
            modifierExclusions([], $ids);
            [$ajoutees, $ignorees] = importerVideos($pdo, $ids, $categorie, $statut, false);
            $introuvables = array_diff($ids, $ajoutees, $ignorees);

            if ($ajoutees) {
                flash(count($ajoutees) . " vidéo" . (count($ajoutees) > 1 ? "s ajoutées" : " ajoutée") . ".");
            }
            if ($ignorees) {
                flash(count($ignorees) . " déjà présente" . (count($ignorees) > 1 ? "s" : "") . " sur le site.", 'info');
            }
            if ($introuvables) {
                flash("Introuvable(s) sur YouTube (privée ou supprimée) : " . implode(', ', $introuvables), 'error');
            }
            if ($invalides) {
                flash("Liens non reconnus : " . implode(', ', array_slice($invalides, 0, 5)), 'error');
            }

            if (count($ajoutees) === 1) {
                $stmt = $pdo->prepare("SELECT id FROM youtube_videos WHERE youtube_id = ?");
                $stmt->execute([$ajoutees[0]]);
                rediriger("modifier.php?id=" . (int) $stmt->fetchColumn());
            }
            rediriger($ajoutees ? "videos.php?tri=ajout" : "ajouter.php");
        } catch (Throwable $e) {
            $erreurs[] = $e->getMessage();
        }
    }
}

$categories = listeCategories($pdo);
$titre_page = "Ajouter des vidéos";
$page_active = 'ajouter';
require __DIR__ . "/inc/header.php";
?>

<header class="page-head">
    <div>
        <h1>Ajouter des vidéos</h1>
        <p class="muted">Collez un ou plusieurs liens YouTube : titre, miniature, durée et statistiques sont récupérés automatiquement.</p>
    </div>
</header>

<?php foreach ($erreurs as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="edit-layout">
    <form method="post" class="card form" data-loading="Importation…">
        <?= csrfChamp() ?>

        <label class="field">
            <span>Liens YouTube</span>
            <textarea name="liens" rows="6" required autofocus
                      placeholder="https://www.youtube.com/watch?v=…&#10;https://youtu.be/…&#10;https://www.youtube.com/shorts/…"><?= e($liens) ?></textarea>
            <small class="hint">Un lien par ligne. Les liens « watch », « youtu.be », « shorts » et « live » sont acceptés.</small>
        </label>

        <div class="link-preview" hidden></div>

        <label class="field">
            <span>Catégorie <em class="muted">(facultatif)</em></span>
            <input type="text" name="categorie" value="<?= e($categorie) ?>" list="liste-categories" placeholder="Ex. Sketch" maxlength="100">
        </label>
        <datalist id="liste-categories">
            <?php foreach ($categories as $c): ?><option value="<?= e($c['categorie']) ?>"><?php endforeach; ?>
        </datalist>

        <div class="toggles">
            <label class="toggle">
                <input type="checkbox" name="publie" checked>
                <span class="switch"></span>
                <span><strong>Publier immédiatement</strong><small>Sinon la vidéo est ajoutée en mode masqué.</small></span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Ajouter</button>
        </div>
    </form>

    <aside class="edit-side">
        <div class="card">
            <h2 class="card-title">💡 Astuce</h2>
            <p class="muted">Les nouvelles vidéos de la chaîne sont ajoutées automatiquement par la synchronisation.
                Utilisez cette page pour une vidéo d'une autre chaîne, ou pour ne pas attendre.</p>
            <form method="post" action="synchroniser.php" data-loading="Synchronisation…">
                <?= csrfChamp() ?>
                <button class="btn btn-ghost btn-block">↻ Synchroniser la chaîne maintenant</button>
            </form>
        </div>
    </aside>
</div>

<?php require __DIR__ . "/inc/footer.php"; ?>
