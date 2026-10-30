<?php
require_once __DIR__ . "/inc/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    set_time_limit(300);
    $r = synchroniserYoutube($pdo, !empty($_POST['complet']));

    if ($r['ok']) {
        flash("Synchronisation réussie : {$r['ajoutees']} nouvelle(s), {$r['mises_a_jour']} mise(s) à jour, " . count($r['masquees']) . " masquée(s).");
    } else {
        flash("Échec de la synchronisation : " . $r['erreur'], 'error');
    }
    rediriger(retourSur('synchroniser.php'));
}

$journal = journalSynchronisation(30);
$url_cron = rtrim($site['url_site'], '/') . '/admin/youtube/youtube_sync.php' . (CRON_TOKEN !== '' ? '?token=' . urlencode(CRON_TOKEN) : '');

$titre_page = "Synchronisation";
$page_active = 'synchro';
require __DIR__ . "/inc/header.php";
?>

<header class="page-head">
    <div>
        <h1>Synchronisation YouTube</h1>
        <p class="muted">Ajoute les nouvelles vidéos de la chaîne, met à jour les vues et likes, et masque les vidéos supprimées sur YouTube.</p>
    </div>
</header>

<div class="grid-2">
    <section class="card">
        <h2 class="card-title">Lancer maintenant</h2>
        <form method="post" data-loading="Synchronisation en cours…">
            <?= csrfChamp() ?>
            <button class="btn btn-primary btn-block">↻ Synchroniser</button>
        </form>
        <form method="post" data-loading="Synchronisation complète…" class="mt">
            <?= csrfChamp() ?>
            <input type="hidden" name="complet" value="1">
            <button class="btn btn-ghost btn-block" data-confirm="Parcourir toute la chaîne ? Utile si des vidéos anciennes manquent. Cela peut prendre une minute.">Synchronisation complète (toute la chaîne)</button>
        </form>
        <p class="hint mt">Les titres et descriptions modifiés dans l'administration ne sont jamais écrasés.</p>
    </section>

    <section class="card">
        <h2 class="card-title">Synchronisation automatique (cron)</h2>
        <p class="muted">Dans votre hébergeur, programmez cette adresse toutes les 30 à 60 minutes :</p>
        <div class="copy-field">
            <input type="text" readonly value="<?= e($url_cron) ?>" aria-label="Adresse du cron">
            <button type="button" class="btn btn-ghost btn-sm copy-btn">Copier</button>
        </div>
        <?php if (CRON_TOKEN === ''): ?>
            <div class="alert alert-warning mt">
                Aucun jeton : n'importe qui peut lancer la synchronisation. Ajoutez <code>'cron_token' => 'un-code-long',</code>
                dans <code>config/secrets.php</code>, puis mettez à jour l'adresse du cron.
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <h2 class="card-title">Journal</h2>
    <?php if ($journal): ?>
        <ul class="log">
            <?php foreach ($journal as $j): ?>
                <li class="<?= $j['ok'] ? '' : 'log-error' ?>">
                    <span class="dot <?= $j['ok'] ? 'dot-ok' : 'dot-error' ?>"></span>
                    <time title="<?= e($j['date']) ?>"><?= e(date('d/m H:i', strtotime($j['date']))) ?></time>
                    <span><?= e(preg_replace('/^Synchronisation YouTube – [^|]*\| /u', '', $j['texte'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">Aucune synchronisation enregistrée pour le moment.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . "/inc/footer.php"; ?>
