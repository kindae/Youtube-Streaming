<?php
/*
|--------------------------------------------------------------------------
| SYNCHRONISATION AUTOMATIQUE YOUTUBE (CRON)
|--------------------------------------------------------------------------
| 1. Ajoute les nouvelles vidéos publiques de la chaîne
| 2. Met à jour vues, likes, commentaires, durée et miniature de toutes les vidéos
| 3. Masque (statut « brouillon ») les vidéos supprimées ou devenues privées sur YouTube
|
| Coût API : ~1 unité par tranche de 50 vidéos (au lieu de 100+ avec search.list).
|
| Appel cron :
|   wget -qO- "https://plus.congonet24.com/admin/youtube/youtube_sync.php?token=VOTRE_JETON"
|   ou : php /chemin/vers/admin/youtube/youtube_sync.php
| Options : &complet=1 parcourt toute la chaîne (première importation / rattrapage)
*/

require_once __DIR__ . "/../../config/database.php";

set_time_limit(300);

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/
$channel_id = "UC003RZcnMJvfe1M9sVJtPOg";
$categorie_defaut = "";   // Catégorie donnée aux nouvelles vidéos (modifiable ensuite dans l'admin)
$pages_max = 3;           // Pages de 50 vidéos lues pour trouver les nouveautés (sans ?complet=1)

$en_cli = PHP_SAPI === 'cli';
$complet = $en_cli ? in_array('--complet', $argv ?? [], true) : !empty($_GET['complet']);

/*
|--------------------------------------------------------------------------
| SÉCURITÉ : jeton optionnel (config/secrets.php => 'cron_token')
|--------------------------------------------------------------------------
*/
if (!$en_cli && CRON_TOKEN !== '' && !hash_equals(CRON_TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("Accès refusé.");
}

if (!$en_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
}

/*
|--------------------------------------------------------------------------
| VERROU : évite deux synchronisations en même temps
|--------------------------------------------------------------------------
*/
$verrou = fopen(sys_get_temp_dir() . '/congonet24_youtube_sync.lock', 'c');
if (!$verrou || !flock($verrou, LOCK_EX | LOCK_NB)) {
    exit("Une synchronisation est déjà en cours.\n");
}

$journal = [];
function noter($texte)
{
    global $journal;
    $journal[] = $texte;
    echo $texte . "\n";
    @flush();
}

function terminer($code_sortie)
{
    global $journal;
    $fichier = __DIR__ . '/../../config/youtube_sync.log';
    $lignes = is_file($fichier) ? file($fichier, FILE_IGNORE_NEW_LINES) : [];
    $lignes[] = '[' . date('Y-m-d H:i:s') . '] ' . implode(' | ', $journal);
    @file_put_contents($fichier, implode("\n", array_slice($lignes, -200)) . "\n");
    exit($code_sortie);
}

/*
|--------------------------------------------------------------------------
| APPEL À L'API YOUTUBE
|--------------------------------------------------------------------------
*/
function apiYoutube($ressource, array $params)
{
    $params['key'] = YOUTUBE_API_KEY;
    $ch = curl_init("https://www.googleapis.com/youtube/v3/" . $ressource . "?" . http_build_query($params));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $reponse = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur = curl_error($ch);
    curl_close($ch);

    if ($reponse === false) {
        throw new RuntimeException("Erreur réseau : " . $erreur);
    }
    $json = json_decode($reponse, true);
    if ($code !== 200 || !is_array($json)) {
        $message = $json['error']['message'] ?? substr((string) $reponse, 0, 200);
        throw new RuntimeException("API YouTube HTTP $code : " . strip_tags($message));
    }
    return $json;
}

// Détails complets de jusqu'à 50 vidéos, indexés par ID
function detailsVideos(array $ids)
{
    $resultat = [];
    foreach (array_chunk(array_values(array_unique($ids)), 50) as $lot) {
        $json = apiYoutube('videos', [
            'part' => 'snippet,contentDetails,statistics,status',
            'id' => implode(',', $lot),
            'maxResults' => 50,
        ]);
        foreach ($json['items'] ?? [] as $item) {
            $resultat[$item['id']] = $item;
        }
    }
    return $resultat;
}

function miniatureDepuis($item)
{
    $t = $item['snippet']['thumbnails'] ?? [];
    return $t['medium']['url'] ?? $t['high']['url'] ?? ("https://i.ytimg.com/vi/" . $item['id'] . "/mqdefault.jpg");
}

function estPublique($item)
{
    return ($item['status']['privacyStatus'] ?? '') === 'public'
        && ($item['snippet']['liveBroadcastContent'] ?? 'none') !== 'upcoming';
}

try {
    if (YOUTUBE_API_KEY === '') {
        throw new RuntimeException("Clé API YouTube absente de config/secrets.php.");
    }

    $debut = microtime(true);
    noter("Synchronisation YouTube – " . date('d/m/Y H:i:s') . ($complet ? " (complète)" : ""));

    /*
    |--------------------------------------------------------------------------
    | 1. NOUVELLES VIDÉOS : playlist « uploads » de la chaîne
    |--------------------------------------------------------------------------
    */
    $connues = array_flip($pdo->query("SELECT youtube_id FROM youtube_videos")->fetchAll(PDO::FETCH_COLUMN));

    $playlist_uploads = 'UU' . substr($channel_id, 2);
    $nouvelles_ids = [];
    $page_token = null;
    $pages = 0;

    do {
        $params = ['part' => 'contentDetails', 'playlistId' => $playlist_uploads, 'maxResults' => 50];
        if ($page_token) {
            $params['pageToken'] = $page_token;
        }
        $json = apiYoutube('playlistItems', $params);
        $pages++;

        $page_deja_connue = true;
        foreach ($json['items'] ?? [] as $item) {
            $id = $item['contentDetails']['videoId'] ?? '';
            if ($id === '') continue;
            if (!isset($connues[$id])) {
                $nouvelles_ids[] = $id;
                $page_deja_connue = false;
            }
        }

        $page_token = $json['nextPageToken'] ?? null;

        // Les uploads sont du plus récent au plus ancien : une page entièrement connue suffit à s'arrêter
        if (!$complet && ($page_deja_connue || $pages >= $pages_max)) {
            break;
        }
    } while ($page_token);

    $ajoutees = 0;
    if ($nouvelles_ids) {
        $insert = $pdo->prepare("
            INSERT INTO youtube_videos (
                youtube_id, titre, description, miniature, date_youtube, duree,
                vues_youtube, likes_youtube, commentaires_youtube,
                categorie, vedette, statut, date_ajout, derniere_sync
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'publie', NOW(), NOW())
        ");

        foreach (detailsVideos($nouvelles_ids) as $id => $item) {
            if (!estPublique($item)) continue;
            $s = $item['snippet'];
            $st = $item['statistics'] ?? [];
            $insert->execute([
                $id,
                $s['title'] ?? '',
                $s['description'] ?? '',
                miniatureDepuis($item),
                !empty($s['publishedAt']) ? date('Y-m-d H:i:s', strtotime($s['publishedAt'])) : null,
                $item['contentDetails']['duration'] ?? '',
                (int) ($st['viewCount'] ?? 0),
                (int) ($st['likeCount'] ?? 0),
                (int) ($st['commentCount'] ?? 0),
                $categorie_defaut,
            ]);
            $ajoutees++;
        }
    }
    noter("Nouvelles vidéos ajoutées : $ajoutees");

    /*
    |--------------------------------------------------------------------------
    | 2. MISE À JOUR DES VIDÉOS EXISTANTES
    |--------------------------------------------------------------------------
    | Le titre et la description ne sont pas écrasés : ils peuvent avoir été
    | personnalisés dans l'administration.
    */
    $existantes = $pdo->query("SELECT id, youtube_id, statut FROM youtube_videos WHERE youtube_id IS NOT NULL AND youtube_id != ''")->fetchAll();

    $update = $pdo->prepare("
        UPDATE youtube_videos SET
            vues_youtube = ?, likes_youtube = ?, commentaires_youtube = ?,
            duree = ?, miniature = ?, date_youtube = COALESCE(date_youtube, ?),
            derniere_sync = NOW()
        WHERE id = ?
    ");
    $masquer = $pdo->prepare("UPDATE youtube_videos SET statut = 'brouillon', vedette = 0, derniere_sync = NOW() WHERE id = ?");

    $mises_a_jour = 0;
    $masquees = [];

    foreach (array_chunk($existantes, 50) as $lot) {
        $details = detailsVideos(array_column($lot, 'youtube_id'));

        // Réponse anormalement vide : on ne masque rien par prudence
        $suspect = count($lot) >= 5 && count($details) === 0;

        foreach ($lot as $video) {
            $item = $details[$video['youtube_id']] ?? null;

            // Supprimée ou devenue privée sur YouTube : on la retire du site
            if (!$item || ($item['status']['privacyStatus'] ?? '') === 'private') {
                if ($video['statut'] === 'publie' && !$suspect) {
                    $masquer->execute([$video['id']]);
                    $masquees[] = $video['youtube_id'];
                }
                continue;
            }

            $st = $item['statistics'] ?? [];
            $s = $item['snippet'];
            $update->execute([
                (int) ($st['viewCount'] ?? 0),
                (int) ($st['likeCount'] ?? 0),
                (int) ($st['commentCount'] ?? 0),
                $item['contentDetails']['duration'] ?? '',
                miniatureDepuis($item),
                !empty($s['publishedAt']) ? date('Y-m-d H:i:s', strtotime($s['publishedAt'])) : null,
                $video['id'],
            ]);
            $mises_a_jour++;
        }
    }

    noter("Vidéos mises à jour : $mises_a_jour");
    noter("Vidéos masquées (supprimées/privées sur YouTube) : " . count($masquees) . ($masquees ? " → " . implode(', ', $masquees) : ''));
    noter("Durée : " . round(microtime(true) - $debut, 1) . " s");
    terminer(0);

} catch (Throwable $e) {
    if (!$en_cli) http_response_code(500);
    noter("ERREUR : " . $e->getMessage());
    terminer(1);
}
