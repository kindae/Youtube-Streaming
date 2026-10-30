<?php
require_once __DIR__ . "/inc/bootstrap.php";

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(statut = 'publie') AS publiees,
        SUM(statut != 'publie') AS masquees,
        COALESCE(SUM(CASE WHEN statut = 'publie' THEN vues_youtube END), 0) AS vues,
        COALESCE(SUM(CASE WHEN statut = 'publie' THEN likes_youtube END), 0) AS likes,
        COALESCE(SUM(CASE WHEN statut = 'publie' THEN commentaires_youtube END), 0) AS commentaires,
        SUM(categorie IS NULL OR categorie = '') AS sans_categorie,
        MAX(derniere_sync) AS derniere_sync
    FROM youtube_videos
")->fetch();

$vedette = $pdo->query("SELECT * FROM youtube_videos WHERE vedette = 1 AND statut = 'publie' ORDER BY id DESC LIMIT 1")->fetch();
$top = $pdo->query("SELECT * FROM youtube_videos WHERE statut = 'publie' ORDER BY vues_youtube DESC LIMIT 5")->fetchAll();
$recentes = $pdo->query("SELECT * FROM youtube_videos ORDER BY date_youtube DESC, id DESC LIMIT 5")->fetchAll();
$journal = journalSynchronisation(1);
$derniere = $journal[0] ?? null;

$titre_page = "Tableau de bord";
$page_active = 'index';
require __DIR__ . "/inc/header.php";
?>

<header class="page-head">
    <div>
        <h1>Bonjour 👋</h1>
        <p class="muted">Voici l'état de votre chaîne sur le site.</p>
    </div>
    <div class="head-actions">
        <form method="post" action="synchroniser.php" class="inline" data-loading="Synchronisation…">
            <?= csrfChamp() ?>
            <button class="btn btn-ghost"><svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6a6 6 0 0 1 5.65 8.03l1.46 1.46A8 8 0 0 0 12 4zm0 14a6 6 0 0 1-5.65-8.03L4.89 8.51A8 8 0 0 0 12 20v3l4-4-4-4v3z"/></svg> Synchroniser</button>
        </form>
        <a href="ajouter.php" class="btn btn-primary"><svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Ajouter une vidéo</a>
    </div>
</header>

<section class="stats">
    <a href="videos.php?statut=publie" class="stat">
        <span class="stat-label">Vidéos en ligne</span>
        <strong><?= number_format((int) $stats['publiees'], 0, ',', ' ') ?></strong>
        <small><?= (int) $stats['masquees'] ?> masquée<?= $stats['masquees'] > 1 ? 's' : '' ?></small>
    </a>
    <div class="stat">
        <span class="stat-label">Vues cumulées</span>
        <strong><?= formatVues($stats['vues']) ?></strong>
        <small><?= number_format((int) $stats['vues'], 0, ',', ' ') ?> vues</small>
    </div>
    <div class="stat">
        <span class="stat-label">J'aime</span>
        <strong><?= formatVues($stats['likes']) ?></strong>
        <small><?= formatVues($stats['commentaires']) ?> commentaires</small>
    </div>
    <a href="synchroniser.php" class="stat">
        <span class="stat-label">Dernière synchro</span>
        <strong class="stat-small">
            <?php if ($derniere): ?>
                <span class="dot <?= $derniere['ok'] ? 'dot-ok' : 'dot-error' ?>"></span>
                <?= e(tempsEcoule($derniere['date']) ?: $derniere['date']) ?>
            <?php elseif ($stats['derniere_sync']): ?>
                <?= e(tempsEcoule($stats['derniere_sync'])) ?>
            <?php else: ?>
                Jamais
            <?php endif; ?>
        </strong>
        <small><?= $derniere ? ($derniere['ok'] ? 'Réussie' : 'En échec – voir le détail') : 'Voir le journal' ?></small>
    </a>
</section>

<?php if ((int) $stats['sans_categorie'] > 0): ?>
    <div class="alert alert-info">
        <?= (int) $stats['sans_categorie'] ?> vidéo<?= $stats['sans_categorie'] > 1 ? 's n\'ont' : ' n\'a' ?> pas de catégorie.
        <a href="videos.php?categorie=__aucune">Les classer →</a>
    </div>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>⭐ À la une</h2>
            <a href="videos.php" class="link">Changer</a>
        </div>
        <?php if ($vedette): ?>
            <div class="featured-mini">
                <img src="<?= e(miniature($vedette)) ?>" alt="">
                <div>
                    <strong><?= e($vedette['titre']) ?></strong>
                    <p class="muted"><?= formatVues($vedette['vues_youtube']) ?> vues · <?= e(tempsEcoule($vedette['date_youtube'])) ?></p>
                    <div class="row-actions">
                        <a href="modifier.php?id=<?= (int) $vedette['id'] ?>" class="btn btn-ghost btn-sm">Modifier</a>
                        <a href="../../<?= e(lienVideo($vedette)) ?>" target="_blank" class="btn btn-ghost btn-sm">Voir</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="muted">Aucune vidéo à la une : le site affiche automatiquement la plus récente.
                Utilisez l'étoile ☆ dans la liste des vidéos pour en choisir une.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>🆕 Dernières vidéos</h2>
            <a href="videos.php" class="link">Tout voir</a>
        </div>
        <ul class="mini-list">
            <?php foreach ($recentes as $v): ?>
                <li>
                    <a href="modifier.php?id=<?= (int) $v['id'] ?>">
                        <img src="<?= e(miniature($v)) ?>" alt="" loading="lazy">
                        <span class="mini-title"><?= e($v['titre']) ?></span>
                        <span class="badge <?= $v['statut'] === 'publie' ? 'badge-ok' : 'badge-muted' ?>"><?= e(STATUTS[$v['statut']] ?? $v['statut']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <h2>🔥 Les plus vues</h2>
        <a href="videos.php?tri=vues" class="link">Classement complet</a>
    </div>
    <ol class="top-list">
        <?php $max = max(1, (int) ($top[0]['vues_youtube'] ?? 1)); ?>
        <?php foreach ($top as $i => $v): ?>
            <li>
                <span class="top-rank"><?= $i + 1 ?></span>
                <img src="<?= e(miniature($v)) ?>" alt="" loading="lazy">
                <div class="top-body">
                    <a href="modifier.php?id=<?= (int) $v['id'] ?>" class="mini-title"><?= e($v['titre']) ?></a>
                    <div class="bar"><span style="width: <?= round($v['vues_youtube'] / $max * 100) ?>%"></span></div>
                </div>
                <strong class="top-views"><?= formatVues($v['vues_youtube']) ?></strong>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php require __DIR__ . "/inc/footer.php"; ?>
