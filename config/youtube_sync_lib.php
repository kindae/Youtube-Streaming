<?php
/*
|--------------------------------------------------------------------------
| SYNCHRONISATION YOUTUBE (utilisée par le cron et l'administration)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/secrets_loader.php';

const YT_CHANNEL_ID = "UC003RZcnMJvfe1M9sVJtPOg";
const YT_SYNC_LOG = __DIR__ . '/youtube_sync.log';
const YT_EXCLUSIONS = __DIR__ . '/videos_exclues.json';

// Vidéos supprimées depuis l'administration : la synchronisation ne les réimporte pas
function idsExclus()
{
    $ids = is_file(YT_EXCLUSIONS) ? json_decode((string) file_get_contents(YT_EXCLUSIONS), true) : [];
    return is_array($ids) ? $ids : [];
}

function modifierExclusions(array $ajouter, array $retirer = [])
{
    $ids = array_values(array_diff(array_unique(array_merge(idsExclus(), $ajouter)), $retirer));
    @file_put_contents(YT_EXCLUSIONS, json_encode($ids));
}

function apiYoutube($ressource, array $params)
{
    if (YOUTUBE_API_KEY === '') {
        throw new RuntimeException("Clé API YouTube absente de config/secrets.php.");
    }
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

// Détails complets des vidéos (par lots de 50), indexés par ID
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

// Accepte un lien YouTube (watch, youtu.be, shorts, embed, live) ou un ID brut
function extraireIdYoutube($texte)
{
    $texte = trim((string) $texte);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $texte)) {
        return $texte;
    }
    if (preg_match('~(?:youtu\.be/|[?&]v=|/(?:shorts|embed|live|v)/)([A-Za-z0-9_-]{11})~', $texte, $m)) {
        return $m[1];
    }
    return null;
}

// Insère des vidéos depuis l'API. Retourne [ajoutées, ignorées (déjà présentes / non publiques)]
function importerVideos(PDO $pdo, array $ids, $categorie = '', $statut = 'publie', $seulement_publiques = true)
{
    $insert = $pdo->prepare("
        INSERT INTO youtube_videos (
            youtube_id, titre, description, miniature, date_youtube, duree,
            vues_youtube, likes_youtube, commentaires_youtube,
            categorie, vedette, statut, date_ajout, derniere_sync
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())
    ");
    $existe = $pdo->prepare("SELECT 1 FROM youtube_videos WHERE youtube_id = ? LIMIT 1");

    $ajoutees = [];
    $ignorees = [];
    foreach (detailsVideos($ids) as $id => $item) {
        $existe->execute([$id]);
        if ($existe->fetchColumn() || ($seulement_publiques && !estPublique($item))) {
            $ignorees[] = $id;
            continue;
        }
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
            $categorie,
            $statut,
        ]);
        $ajoutees[] = $id;
    }
    return [$ajoutees, $ignorees];
}

/*
|--------------------------------------------------------------------------
| SYNCHRONISATION COMPLÈTE
|--------------------------------------------------------------------------
| 1. Ajoute les nouvelles vidéos publiques de la chaîne
| 2. Met à jour vues, likes, commentaires, durée et miniature
|    (le titre et la description ne sont pas écrasés : ils peuvent être personnalisés)
| 3. Masque les vidéos supprimées ou devenues privées sur YouTube
| Coût API : ~1 unité par tranche de 50 vidéos.
| Retourne un tableau : ok, ajoutees, mises_a_jour, masquees, duree, erreur, lignes
*/
function synchroniserYoutube(PDO $pdo, $complet = false, $categorie_defaut = '', $pages_max = 3)
{
    $verrou = fopen(sys_get_temp_dir() . '/congonet24_youtube_sync.lock', 'c');
    if (!$verrou || !flock($verrou, LOCK_EX | LOCK_NB)) {
        return ['ok' => false, 'erreur' => "Une synchronisation est déjà en cours.", 'lignes' => ["Une synchronisation est déjà en cours."]];
    }

    $debut = microtime(true);
    $r = ['ok' => true, 'ajoutees' => 0, 'mises_a_jour' => 0, 'masquees' => [], 'erreur' => null, 'lignes' => []];
    $r['lignes'][] = "Synchronisation YouTube – " . date('d/m/Y H:i:s') . ($complet ? " (complète)" : "");

    try {
        /* 1. Nouvelles vidéos : playlist « uploads » de la chaîne */
        $connues = array_flip(array_merge(
            $pdo->query("SELECT youtube_id FROM youtube_videos")->fetchAll(PDO::FETCH_COLUMN),
            idsExclus()
        ));
        $nouvelles_ids = [];
        $page_token = null;
        $pages = 0;

        do {
            $params = ['part' => 'contentDetails', 'playlistId' => 'UU' . substr(YT_CHANNEL_ID, 2), 'maxResults' => 50];
            if ($page_token) {
                $params['pageToken'] = $page_token;
            }
            $json = apiYoutube('playlistItems', $params);
            $pages++;

            $page_deja_connue = true;
            foreach ($json['items'] ?? [] as $item) {
                $id = $item['contentDetails']['videoId'] ?? '';
                if ($id !== '' && !isset($connues[$id])) {
                    $nouvelles_ids[] = $id;
                    $page_deja_connue = false;
                }
            }
            $page_token = $json['nextPageToken'] ?? null;

            // Les uploads vont du plus récent au plus ancien : une page entièrement connue suffit
            if (!$complet && ($page_deja_connue || $pages >= $pages_max)) {
                break;
            }
        } while ($page_token);

        if ($nouvelles_ids) {
            $r['ajoutees'] = count(importerVideos($pdo, $nouvelles_ids, $categorie_defaut)[0]);
        }
        $r['lignes'][] = "Nouvelles vidéos ajoutées : " . $r['ajoutees'];

        /* 2 et 3. Mise à jour des vidéos existantes */
        $existantes = $pdo->query("SELECT id, youtube_id, statut FROM youtube_videos WHERE youtube_id IS NOT NULL AND youtube_id != ''")->fetchAll();

        $update = $pdo->prepare("
            UPDATE youtube_videos SET
                vues_youtube = ?, likes_youtube = ?, commentaires_youtube = ?,
                duree = ?, miniature = ?, date_youtube = COALESCE(date_youtube, ?),
                derniere_sync = NOW()
            WHERE id = ?
        ");
        $masquer = $pdo->prepare("UPDATE youtube_videos SET statut = 'brouillon', vedette = 0, derniere_sync = NOW() WHERE id = ?");

        foreach (array_chunk($existantes, 50) as $lot) {
            $details = detailsVideos(array_column($lot, 'youtube_id'));

            // Réponse anormalement vide : on ne masque rien par prudence
            $suspect = count($lot) >= 5 && count($details) === 0;

            foreach ($lot as $video) {
                $item = $details[$video['youtube_id']] ?? null;

                if (!$item || ($item['status']['privacyStatus'] ?? '') === 'private') {
                    if ($video['statut'] === 'publie' && !$suspect) {
                        $masquer->execute([$video['id']]);
                        $r['masquees'][] = $video['youtube_id'];
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
                $r['mises_a_jour']++;
            }
        }

        $r['lignes'][] = "Vidéos mises à jour : " . $r['mises_a_jour'];
        $r['lignes'][] = "Vidéos masquées (supprimées/privées sur YouTube) : " . count($r['masquees'])
            . ($r['masquees'] ? " → " . implode(', ', $r['masquees']) : '');
    } catch (Throwable $e) {
        $r['ok'] = false;
        $r['erreur'] = $e->getMessage();
        $r['lignes'][] = "ERREUR : " . $e->getMessage();
    }

    $r['duree'] = round(microtime(true) - $debut, 1);
    $r['lignes'][] = "Durée : " . $r['duree'] . " s";

    // Journal (200 dernières exécutions)
    $lignes = is_file(YT_SYNC_LOG) ? file(YT_SYNC_LOG, FILE_IGNORE_NEW_LINES) : [];
    $lignes[] = '[' . date('Y-m-d H:i:s') . '] ' . ($r['ok'] ? 'OK' : 'ÉCHEC') . ' | ' . implode(' | ', array_slice($r['lignes'], 1));
    @file_put_contents(YT_SYNC_LOG, implode("\n", array_slice($lignes, -200)) . "\n");

    flock($verrou, LOCK_UN);
    return $r;
}

// Dernières entrées du journal, de la plus récente à la plus ancienne
function journalSynchronisation($nombre = 20)
{
    if (!is_file(YT_SYNC_LOG)) {
        return [];
    }
    $sorties = [];
    foreach (array_reverse(file(YT_SYNC_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) as $ligne) {
        if (!preg_match('/^\[([^\]]+)\] (?:(OK|ÉCHEC) \| )?(.*)$/u', $ligne, $m)) continue;
        $sorties[] = ['date' => $m[1], 'ok' => ($m[2] ?? '') !== 'ÉCHEC' && stripos($m[3], 'ERREUR') === false, 'texte' => $m[3]];
        if (count($sorties) >= $nombre) break;
    }
    return $sorties;
}
