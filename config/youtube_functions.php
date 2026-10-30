<?php
require_once __DIR__ . '/secrets_loader.php';


/*
|--------------------------------------------------------------------------
| CLÉ API YOUTUBE
|--------------------------------------------------------------------------
*/

function getYoutubeApiKey()
{
    return YOUTUBE_API_KEY;
}


/*
|--------------------------------------------------------------------------
| EXTRAIRE L'ID YOUTUBE
|--------------------------------------------------------------------------
*/

function extraireYoutubeId($url)
{
    $url = trim($url);


    if (empty($url)) {

        return false;

    }


    /*
    | YouTube classique
    | https://www.youtube.com/watch?v=XXXXXXXXXXX
    */

    if (
        preg_match(
            '/[?&]v=([a-zA-Z0-9_-]{11})/',
            $url,
            $matches
        )
    ) {

        return $matches[1];

    }


    /*
    | YouTube court
    | https://youtu.be/XXXXXXXXXXX
    */

    if (
        preg_match(
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            $url,
            $matches
        )
    ) {

        return $matches[1];

    }


    /*
    | YouTube Shorts
    | https://www.youtube.com/shorts/XXXXXXXXXXX
    */

    if (
        preg_match(
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            $url,
            $matches
        )
    ) {

        return $matches[1];

    }


    /*
    | YouTube Embed
    | https://www.youtube.com/embed/XXXXXXXXXXX
    */

    if (
        preg_match(
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            $url,
            $matches
        )
    ) {

        return $matches[1];

    }


    return false;

}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER UNE VIDÉO YOUTUBE
|--------------------------------------------------------------------------
*/

function recupererVideoYouTube($youtube_id)
{

    $api_key =
        getYoutubeApiKey();


    /*
    | Vérifier la clé API
    */

    if (
        empty($api_key)
        ||
        $api_key === "TON_API_KEY_YOUTUBE"
    ) {

        return [

            "success" => false,

            "error" =>
                "La clé API YouTube n'est pas configurée."

        ];

    }


    /*
    | Vérifier l'ID
    */

    if (
        empty($youtube_id)
    ) {

        return [

            "success" => false,

            "error" =>
                "ID YouTube vide."

        ];

    }


    /*
    | URL API
    */

    $url =
        "https://www.googleapis.com/youtube/v3/videos"
        . "?part=snippet,contentDetails,statistics"
        . "&id="
        . urlencode($youtube_id)
        . "&key="
        . urlencode($api_key);


    /*
    | CURL
    */

    $ch = curl_init();


    curl_setopt(
        $ch,
        CURLOPT_URL,
        $url
    );


    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_TIMEOUT,
        20
    );


    curl_setopt(
        $ch,
        CURLOPT_SSL_VERIFYPEER,
        true
    );


    $response =
        curl_exec($ch);


    /*
    | Erreur CURL
    */

    if (
        $response === false
    ) {

        $error =
            curl_error($ch);


        curl_close($ch);


        return [

            "success" => false,

            "error" =>
                "Erreur CURL : "
                . $error

        ];

    }


    /*
    | Code HTTP
    */

    $http_code =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    /*
    | Erreur HTTP
    */

    if (
        $http_code !== 200
    ) {

        $json_error =
            json_decode(
                $response,
                true
            );


        $message =
            $json_error["error"]["message"]
            ??
            $response;


        return [

            "success" => false,

            "error" =>
                "Erreur YouTube API HTTP "
                . $http_code
                . " : "
                . $message

        ];

    }


    /*
    | JSON
    */

    $json =
        json_decode(
            $response,
            true
        );


    /*
    | Vérifier JSON
    */

    if (
        json_last_error()
        !== JSON_ERROR_NONE
    ) {

        return [

            "success" => false,

            "error" =>
                "Réponse JSON invalide."

        ];

    }


    /*
    | Vidéo inexistante
    */

    if (
        empty(
            $json["items"]
        )
    ) {

        return [

            "success" => false,

            "error" =>
                "Vidéo YouTube introuvable ou inaccessible."

        ];

    }


    /*
    | Données
    */

    $item =
        $json["items"][0];


    $snippet =
        $item["snippet"]
        ?? [];


    $statistics =
        $item["statistics"]
        ?? [];


    $contentDetails =
        $item["contentDetails"]
        ?? [];


    /*
    | Miniature
    */

    $miniature = "";


    if (
        isset(
            $snippet["thumbnails"]["maxres"]["url"]
        )
    ) {

        $miniature =
            $snippet["thumbnails"]["maxres"]["url"];

    } elseif (
        isset(
            $snippet["thumbnails"]["high"]["url"]
        )
    ) {

        $miniature =
            $snippet["thumbnails"]["high"]["url"];

    } elseif (
        isset(
            $snippet["thumbnails"]["medium"]["url"]
        )
    ) {

        $miniature =
            $snippet["thumbnails"]["medium"]["url"];

    } else {

        $miniature =
            "https://img.youtube.com/vi/"
            . $youtube_id
            . "/hqdefault.jpg";

    }


    /*
    | Date YouTube
    */

    $date_youtube = null;


    if (
        !empty(
            $snippet["publishedAt"]
        )
    ) {

        $date_youtube =
            date(
                "Y-m-d H:i:s",
                strtotime(
                    $snippet["publishedAt"]
                )
            );

    }


    /*
    | Retour
    */

    return [

        "success" => true,

        "titre" =>
            $snippet["title"]
            ?? "",

        "description" =>
            $snippet["description"]
            ?? "",

        "miniature" =>
            $miniature,

        "date_youtube" =>
            $date_youtube,

        "duree" =>
            $contentDetails["duration"]
            ?? "",

        "vues_youtube" =>
            intval(
                $statistics["viewCount"]
                ?? 0
            ),

        "likes_youtube" =>
            intval(
                $statistics["likeCount"]
                ?? 0
            ),

        "commentaires_youtube" =>
            intval(
                $statistics["commentCount"]
                ?? 0
            )

    ];

}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES VIDÉOS D'UNE CHAÎNE YOUTUBE
|--------------------------------------------------------------------------
*/

function recupererVideosChaineYouTube($channel_id, $max_results = 20)
{
    $api_key = getYoutubeApiKey();

    if (
        empty($api_key)
        ||
        $api_key === "TON_API_KEY_YOUTUBE"
    ) {

        return [
            "success" => false,
            "error" => "La clé API YouTube n'est pas configurée."
        ];

    }


    if (empty($channel_id)) {

        return [
            "success" => false,
            "error" => "L'ID de la chaîne YouTube est vide."
        ];

    }


    /*
    |--------------------------------------------------------------------------
    | URL API
    |--------------------------------------------------------------------------
    */

    $url =
        "https://www.googleapis.com/youtube/v3/search"
        . "?part=snippet"
        . "&channelId="
        . urlencode($channel_id)
        . "&maxResults="
        . intval($max_results)
        . "&order=date"
        . "&type=video"
        . "&key="
        . urlencode($api_key);


    /*
    |--------------------------------------------------------------------------
    | CURL
    |--------------------------------------------------------------------------
    */

    $ch = curl_init();

    curl_setopt(
        $ch,
        CURLOPT_URL,
        $url
    );

    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_TIMEOUT,
        20
    );

    curl_setopt(
        $ch,
        CURLOPT_SSL_VERIFYPEER,
        true
    );


    $response =
        curl_exec($ch);


    if (
        $response === false
    ) {

        $error =
            curl_error($ch);

        curl_close($ch);

        return [
            "success" => false,
            "error" => "Erreur CURL : " . $error
        ];

    }


    $http_code =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | ERREUR API
    |--------------------------------------------------------------------------
    */

    if (
        $http_code !== 200
    ) {

        $json_error =
            json_decode(
                $response,
                true
            );


        $message =
            $json_error["error"]["message"]
            ??
            $response;


        return [
            "success" => false,
            "error" =>
                "Erreur YouTube API HTTP "
                . $http_code
                . " : "
                . $message
        ];

    }


    /*
    |--------------------------------------------------------------------------
    | JSON
    |--------------------------------------------------------------------------
    */

    $json =
        json_decode(
            $response,
            true
        );


    if (
        json_last_error()
        !== JSON_ERROR_NONE
    ) {

        return [
            "success" => false,
            "error" => "Réponse JSON invalide."
        ];

    }


    /*
    |--------------------------------------------------------------------------
    | RÉSULTAT
    |--------------------------------------------------------------------------
    */

    $videos = [];


    if (
        !empty(
            $json["items"]
        )
    ) {

        foreach (
            $json["items"]
            as $item
        ) {

            if (
                empty(
                    $item["id"]["videoId"]
                )
            ) {

                continue;

            }


            $videos[] = [

                "youtube_id" =>
                    $item["id"]["videoId"],

                "titre" =>
                    $item["snippet"]["title"]
                    ?? "",

                "description" =>
                    $item["snippet"]["description"]
                    ?? "",

                "miniature" =>
                    $item["snippet"]["thumbnails"]["high"]["url"]
                    ??
                    "https://img.youtube.com/vi/"
                    . $item["id"]["videoId"]
                    . "/hqdefault.jpg",

                "date_youtube" =>
                    !empty(
                        $item["snippet"]["publishedAt"]
                    )
                    ?
                    date(
                        "Y-m-d H:i:s",
                        strtotime(
                            $item["snippet"]["publishedAt"]
                        )
                    )
                    :
                    null

            ];

        }

    }


    return [

        "success" => true,

        "videos" => $videos

    ];

}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER TOUTES LES VIDÉOS D'UNE CHAÎNE YOUTUBE
| PREMIÈRE IMPORTATION COMPLÈTE
|--------------------------------------------------------------------------
*/

function recupererToutesVideosChaineYouTube(
    $channel_id,
    $max_results = 50
) {

    $api_key =
        getYoutubeApiKey();


    /*
    |--------------------------------------------------------------------------
    | VÉRIFIER LA CLÉ API
    |--------------------------------------------------------------------------
    */

    if (
        empty($api_key)
        ||
        $api_key === "TON_API_KEY_YOUTUBE"
    ) {

        return [

            "success" => false,

            "error" =>
                "La clé API YouTube n'est pas configurée."

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | VÉRIFIER L'ID DE LA CHAÎNE
    |--------------------------------------------------------------------------
    */

    if (
        empty($channel_id)
    ) {

        return [

            "success" => false,

            "error" =>
                "L'ID de la chaîne YouTube est vide."

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | MAXIMUM 50 PAR REQUÊTE
    |--------------------------------------------------------------------------
    */

    $max_results =
        intval(
            $max_results
        );


    if (
        $max_results < 1
    ) {

        $max_results = 50;

    }


    if (
        $max_results > 50
    ) {

        $max_results = 50;

    }


    /*
    |--------------------------------------------------------------------------
    | TABLEAU FINAL DES VIDÉOS
    |--------------------------------------------------------------------------
    */

    $videos = [];


    /*
    |--------------------------------------------------------------------------
    | TOKEN DE PAGINATION
    |--------------------------------------------------------------------------
    */

    $page_token = "";


    /*
    |--------------------------------------------------------------------------
    | PARCOURIR TOUTES LES PAGES
    |--------------------------------------------------------------------------
    */

    do {


        /*
        |--------------------------------------------------------------------------
        | CONSTRUIRE L'URL
        |--------------------------------------------------------------------------
        */

        $url =
            "https://www.googleapis.com/youtube/v3/search"

            . "?part=snippet"

            . "&channelId="

            . urlencode(
                $channel_id
            )

            . "&maxResults="

            . $max_results

            . "&order=date"

            . "&type=video"

            . "&key="

            . urlencode(
                $api_key
            );


        /*
        |--------------------------------------------------------------------------
        | AJOUTER PAGE TOKEN
        |--------------------------------------------------------------------------
        */

        if (
            !empty($page_token)
        ) {

            $url .=

                "&pageToken="

                .

                urlencode(
                    $page_token
                );

        }


        /*
        |--------------------------------------------------------------------------
        | CURL
        |--------------------------------------------------------------------------
        */

        $ch =
            curl_init();


        curl_setopt(
            $ch,
            CURLOPT_URL,
            $url
        );


        curl_setopt(
            $ch,
            CURLOPT_RETURNTRANSFER,
            true
        );


        curl_setopt(
            $ch,
            CURLOPT_TIMEOUT,
            30
        );


        curl_setopt(
            $ch,
            CURLOPT_SSL_VERIFYPEER,
            true
        );


        $response =
            curl_exec(
                $ch
            );


        /*
        |--------------------------------------------------------------------------
        | ERREUR CURL
        |--------------------------------------------------------------------------
        */

        if (
            $response === false
        ) {

            $error =
                curl_error(
                    $ch
                );


            curl_close(
                $ch
            );


            return [

                "success" => false,

                "error" =>
                    "Erreur CURL : "
                    .
                    $error

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | CODE HTTP
        |--------------------------------------------------------------------------
        */

        $http_code =
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );


        curl_close(
            $ch
        );


        /*
        |--------------------------------------------------------------------------
        | ERREUR API
        |--------------------------------------------------------------------------
        */

        if (
            $http_code !== 200
        ) {

            $json_error =
                json_decode(
                    $response,
                    true
                );


            $message =
                $json_error["error"]["message"]
                ??
                $response;


            return [

                "success" => false,

                "error" =>
                    "Erreur YouTube API HTTP "
                    .
                    $http_code
                    .
                    " : "
                    .
                    $message

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | DÉCODER LE JSON
        |--------------------------------------------------------------------------
        */

        $json =
            json_decode(
                $response,
                true
            );


        if (
            json_last_error()
            !== JSON_ERROR_NONE
        ) {

            return [

                "success" => false,

                "error" =>
                    "Réponse JSON invalide."

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | RÉCUPÉRER LES VIDÉOS
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $json["items"]
            )
        ) {


            foreach (
                $json["items"]
                as $item
            ) {


                /*
                |--------------------------------------------------------------------------
                | ID VIDÉO
                |--------------------------------------------------------------------------
                */

                $youtube_id =
                    $item["id"]["videoId"]
                    ??
                    "";


                if (
                    empty($youtube_id)
                ) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | AJOUTER LA VIDÉO
                |--------------------------------------------------------------------------
                */

                $videos[] = [

                    "youtube_id" =>
                        $youtube_id,

                    "titre" =>
                        $item["snippet"]["title"]
                        ??
                        "",

                    "description" =>
                        $item["snippet"]["description"]
                        ??
                        "",

                    "miniature" =>
                        $item["snippet"]["thumbnails"]["high"]["url"]
                        ??
                        "https://img.youtube.com/vi/"
                        .
                        $youtube_id
                        .
                        "/hqdefault.jpg",

                    "date_youtube" =>
                        !empty(
                            $item["snippet"]["publishedAt"]
                        )
                        ?
                        date(
                            "Y-m-d H:i:s",
                            strtotime(
                                $item["snippet"]["publishedAt"]
                            )
                        )
                        :
                        null

                ];

            }

        }


        /*
        |--------------------------------------------------------------------------
        | RÉCUPÉRER LA PAGE SUIVANTE
        |--------------------------------------------------------------------------
        */

        $page_token =

            $json["nextPageToken"]

            ??

            "";


    } while (

        !empty(
            $page_token
        )

    );


    /*
    |--------------------------------------------------------------------------
    | RETOUR FINAL
    |--------------------------------------------------------------------------
    */

    return [

        "success" => true,

        "videos" => $videos

    ];

}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES STATISTIQUES DE PLUSIEURS VIDÉOS
| MAXIMUM 50 VIDÉOS PAR REQUÊTE
|--------------------------------------------------------------------------
*/

function recupererStatistiquesVideosYouTube($youtube_ids)
{

    $api_key =
        getYoutubeApiKey();


    /*
    |--------------------------------------------------------------------------
    | VÉRIFIER LA CLÉ API
    |--------------------------------------------------------------------------
    */

    if (
        empty($api_key)
        ||
        $api_key === "TON_API_KEY_YOUTUBE"
    ) {

        return [

            "success" => false,

            "error" =>
                "La clé API YouTube n'est pas configurée."

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | VÉRIFIER LES IDS
    |--------------------------------------------------------------------------
    */

    if (
        empty($youtube_ids)
        ||
        !is_array($youtube_ids)
    ) {

        return [

            "success" => false,

            "error" =>
                "Aucun ID YouTube fourni."

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | NETTOYER LES IDS
    |--------------------------------------------------------------------------
    */

    $youtube_ids =
        array_filter(
            array_map(
                "trim",
                $youtube_ids
            )
        );


    /*
    |--------------------------------------------------------------------------
    | SUPPRIMER LES DOUBLONS
    |--------------------------------------------------------------------------
    */

    $youtube_ids =
        array_unique(
            $youtube_ids
        );


    /*
    |--------------------------------------------------------------------------
    | MAXIMUM 50 IDS
    |--------------------------------------------------------------------------
    */

    $youtube_ids =
        array_slice(
            $youtube_ids,
            0,
            50
        );


    /*
    |--------------------------------------------------------------------------
    | CONSTRUIRE LA LISTE DES IDS
    |--------------------------------------------------------------------------
    */

    $ids =
        implode(
            ",",
            $youtube_ids
        );


    /*
    |--------------------------------------------------------------------------
    | URL API
    |--------------------------------------------------------------------------
    */

    $url =
        "https://www.googleapis.com/youtube/v3/videos"

        . "?part=statistics"

        . "&id="

        . urlencode(
            $ids
        )

        . "&key="

        . urlencode(
            $api_key
        );


    /*
    |--------------------------------------------------------------------------
    | CURL
    |--------------------------------------------------------------------------
    */

    $ch =
        curl_init();


    curl_setopt(
        $ch,
        CURLOPT_URL,
        $url
    );


    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_TIMEOUT,
        30
    );


    curl_setopt(
        $ch,
        CURLOPT_SSL_VERIFYPEER,
        true
    );


    $response =
        curl_exec(
            $ch
        );


    /*
    |--------------------------------------------------------------------------
    | ERREUR CURL
    |--------------------------------------------------------------------------
    */

    if (
        $response === false
    ) {

        $error =
            curl_error(
                $ch
            );


        curl_close(
            $ch
        );


        return [

            "success" => false,

            "error" =>
                "Erreur CURL : "
                .
                $error

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | CODE HTTP
    |--------------------------------------------------------------------------
    */

    $http_code =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close(
        $ch
    );


    /*
    |--------------------------------------------------------------------------
    | ERREUR API
    |--------------------------------------------------------------------------
    */

    if (
        $http_code !== 200
    ) {

        $json_error =
            json_decode(
                $response,
                true
            );


        $message =
            $json_error["error"]["message"]
            ??
            $response;


        return [

            "success" => false,

            "error" =>
                "Erreur YouTube API HTTP "
                .
                $http_code
                .
                " : "
                .
                $message

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | DÉCODER JSON
    |--------------------------------------------------------------------------
    */

    $json =
        json_decode(
            $response,
            true
        );


    if (
        json_last_error()
        !== JSON_ERROR_NONE
    ) {

        return [

            "success" => false,

            "error" =>
                "Réponse JSON invalide."

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | TABLEAU DES STATISTIQUES
    |--------------------------------------------------------------------------
    */

    $statistiques = [];


    /*
    |--------------------------------------------------------------------------
    | PARCOURIR LES RÉSULTATS
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $json["items"]
        )
    ) {


        foreach (
            $json["items"]
            as $item
        ) {


            $youtube_id =
                $item["id"]
                ??
                "";


            if (
                empty(
                    $youtube_id
                )
            ) {

                continue;

            }


            $statistics =
                $item["statistics"]
                ??
                [];


            /*
            |--------------------------------------------------------------------------
            | STOCKER LES STATISTIQUES
            |--------------------------------------------------------------------------
            */

            $statistiques[
                $youtube_id
            ] = [

                "vues_youtube" =>

                    intval(
                        $statistics["viewCount"]
                        ??
                        0
                    ),


                "likes_youtube" =>

                    intval(
                        $statistics["likeCount"]
                        ??
                        0
                    ),


                "commentaires_youtube" =>

                    intval(
                        $statistics["commentCount"]
                        ??
                        0
                    )

            ];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RETOUR
    |--------------------------------------------------------------------------
    */

    return [

        "success" => true,

        "statistiques" =>
            $statistiques

    ];

}