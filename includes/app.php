<?php
require_once __DIR__ . "/../config/database.php";

/*
|--------------------------------------------------------------------------
| CONFIGURATION DE LA CHAÎNE
|--------------------------------------------------------------------------
*/
$site = [
    'nom'         => "Congonet 24 +",
    'slogan'      => "Humour & Divertissement",
    'description' => "Bienvenue sur Congonet 24 +, votre chaîne d'humour, de comédie et de divertissement ! Retrouvez nos meilleurs sketches, parodies et spectacles.",
    'url_chaine'  => "https://youtube.com/@congonet24plus",
    'abonnement'  => "https://youtube.com/@congonet24plus?sub_confirmation=1",
    'logo'        => "auto.jpg",
    'url_site'    => "https://plus.congonet24.com",
];

/*
|--------------------------------------------------------------------------
| OUTILS
|--------------------------------------------------------------------------
*/
function e($texte)
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

function formatVues($nombre)
{
    $nombre = (int) $nombre;
    if ($nombre >= 1000000) {
        return str_replace('.', ',', round($nombre / 1000000, 1)) . " M";
    }
    if ($nombre >= 1000) {
        return str_replace('.', ',', round($nombre / 1000, 1)) . " k";
    }
    return number_format($nombre, 0, ',', ' ');
}

// Durée ISO 8601 de YouTube (PT1H2M3S) -> 1:02:03
function formatDuree($iso)
{
    if (empty($iso) || !preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
        return '';
    }
    $h = (int) ($m[1] ?? 0) * 24 + (int) ($m[2] ?? 0);
    $min = (int) ($m[3] ?? 0);
    $s = (int) ($m[4] ?? 0);
    if ($h === 0 && $min === 0 && $s === 0) {
        return '';
    }
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $min, $s) : sprintf('%d:%02d', $min, $s);
}

function tempsEcoule($date)
{
    if (empty($date) || !($ts = strtotime($date))) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) return "à l'instant";

    $unites = [
        31536000 => ['an', 'ans'],
        2592000  => ['mois', 'mois'],
        604800   => ['semaine', 'semaines'],
        86400    => ['jour', 'jours'],
        3600     => ['heure', 'heures'],
        60       => ['minute', 'minutes'],
    ];
    foreach ($unites as $secondes => $noms) {
        if ($diff >= $secondes) {
            $n = (int) floor($diff / $secondes);
            return "il y a " . $n . " " . ($n > 1 ? $noms[1] : $noms[0]);
        }
    }
    return '';
}

function idYoutubeValide($id)
{
    return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id);
}

function miniature($video, $qualite = 'mqdefault')
{
    if ($qualite === 'mqdefault' && !empty($video['miniature'])) {
        return $video['miniature'];
    }
    return "https://i.ytimg.com/vi/" . rawurlencode($video['youtube_id']) . "/" . $qualite . ".jpg";
}

function lienVideo($video)
{
    return "watch.php?v=" . rawurlencode($video['youtube_id']);
}

// Description : échappée, liens cliquables, horodatages (1:23) qui pilotent le lecteur
function formaterDescription($texte)
{
    $morceaux = preg_split('~(https?://[^\s<>"\']+)~i', (string) $texte, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($morceaux as $i => $morceau) {
        if ($i % 2 === 1) {
            $html .= '<a href="' . e($morceau) . '" target="_blank" rel="noopener nofollow">' . e($morceau) . '</a>';
            continue;
        }
        $html .= preg_replace_callback(
            '~(?<![\w:])((?:\d{1,2}:)?\d{1,2}:\d{2})(?![\w:])~',
            function ($m) {
                $parties = array_reverse(array_map('intval', explode(':', $m[1])));
                $secondes = $parties[0] + ($parties[1] ?? 0) * 60 + ($parties[2] ?? 0) * 3600;
                return '<a href="#" class="timestamp" data-t="' . $secondes . '">' . $m[1] . '</a>';
            },
            e($morceau)
        );
    }
    return nl2br($html);
}

function afficherCarteVideo($video, $classe = '')
{
    $duree = formatDuree($video['duree'] ?? '');
    $date = tempsEcoule($video['date_youtube'] ?? '');
    ?>
    <article class="video-card <?= e($classe) ?>">
        <a href="<?= e(lienVideo($video)) ?>" class="thumb" aria-label="Regarder : <?= e($video['titre']) ?>">
            <img src="<?= e(miniature($video)) ?>" alt="" loading="lazy" decoding="async"
                 onerror="this.onerror=null;this.src='https://i.ytimg.com/vi/<?= e($video['youtube_id']) ?>/hqdefault.jpg'">
            <?php if ($duree): ?><span class="duration"><?= e($duree) ?></span><?php endif; ?>
            <span class="thumb-play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
        </a>
        <div class="video-info">
            <h3 class="video-title"><a href="<?= e(lienVideo($video)) ?>" title="<?= e($video['titre']) ?>"><?= e($video['titre']) ?></a></h3>
            <p class="video-meta">
                <span><?= formatVues($video['vues_youtube']) ?> vues</span>
                <?php if ($date): ?><span><?= e($date) ?></span><?php endif; ?>
            </p>
        </div>
    </article>
    <?php
}

// Construit une URL de la page d'accueil en conservant les filtres actuels
function urlAccueil($params)
{
    $params = array_filter($params, function ($v) {
        return $v !== '' && $v !== null && $v !== 1;
    });
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}
