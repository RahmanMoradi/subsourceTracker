<?php

require("vendor/autoload.php");


use WpOrg\Requests\Requests;

//Token Here
$TOKEN = "32432432:asdadadadasacsacacacasc";
$ADMIN_IDS = [799041666];
$LANGUAGE = "Farsi/Persian";

function getSubtitles(): array | stdClass
{
    $url = "https://api.subsource.net/api/latestsSubs?page=1";
    $headers = [
        "Accept"=> "application/json, text/plain, */*",
        "Accept-Encoding"=> "gzip, deflate, br",
        "Accept-Language"=> "en-US,en;q=0.5",
        "Connection"=> "keep-alive", 
        "Host"=> "api.subsource.net", 
        "If-None-Match"=> 'W/"1f4c-0jlVP/mLgynBkJj0MJmLwVvMZbs"', 
        "Origin"=> "https://subsource.net", 
        "Referer"=> "https://subsource.net/",
        "Sec-Fetch-Dest"=> "empty",
        "Sec-Fetch-Mode"=> "cors",
        "Sec-Fetch-Site"=> "same-site",
        "User-Agent"=> "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0",
    ];
    $request = Requests::get($url, $headers);
    $content = json_decode($request->body);
    if (!$content->success){
        //TODO: Send a bug message to the developer
        die("couldn't get the subtitles from subsource.net");
    }

    return $content->latests;
}


function getTrackedSubtitles(string $fileName = "ids.json"): array | stdClass
{
    /*
        structure of ids.json :
        [
            10001916,
            10002342,
                ....
        ]
    */
    $file = file_get_contents($fileName);
    return json_decode($file);
}


function updateTrackedSubtitles(array $data, string $fileName = "ids.json"): int|bool
{   
    $data = json_encode($data);
    return file_put_contents($fileName, $data);
}


function getMovie($id, $lang, $movie, $justSubName = false)
{
    $url = "https://api.subsource.net/api/getSub";
    $headers = [
        "Accept"=> "application/json, text/plain, */*",
        "Accept-Encoding"=> "gzip, deflate, br",
        "Accept-Language"=> "en-US,en;q=0.5",
        "Connection"=> "keep-alive", 
        "Host"=> "api.subsource.net", 
        "If-None-Match"=> 'W/"1f4c-0jlVP/mLgynBkJj0MJmLwVvMZbs"', 
        "Origin"=> "https://subsource.net", 
        "Referer"=> "https://subsource.net/",
        "Sec-Fetch-Dest"=> "empty",
        "Sec-Fetch-Mode"=> "cors",
        "Sec-Fetch-Site"=> "same-site",
        "User-Agent"=> "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0",
    ];
    $data = [
        "id" => $id,
        "lang" => $lang,
        "movie" => $movie,
    ];

    $request = Requests::post($url, $headers, $data);
    $content = json_decode($request->body);
    if ($justSubName){
        return $content->sub->ri[0];
    }
    return $content->movie;
}


function getImdb($id, $lang, $movie): array
{   
    $movie = getMovie($id, $lang, $movie);
    return ["slug" => $movie->imdbLink, "url" => "https://imdb.com/title/" . $movie->imdbLink];
}


function searchMovieInSerfil($imdbSlug, $isSeries): bool|string
{
    if ($isSeries){
        $url = "https://serfil.top/series/$imdbSlug"; 
    }
    else {
        $url = "https://serfil.top/movies/$imdbSlug";
    }

    $request = Requests::get($url);
    if ($request->status_code == 200) {
        return $url;
    }
    return false;
}

define('BOT_TOKEN', $TOKEN);
function sendMessage($parameters): \WpOrg\Requests\Response
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage?text=' . $parameters["text"] . '&chat_id=' . $parameters["chat_id"];
    return Requests::post($url, [], $parameters);
}


function sendSubtitle(string $text)
{
    global $ADMIN_IDS;

    foreach ($ADMIN_IDS as $adminID){
        $parameters = [
            'chat_id' => $adminID, 
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];
        sendMessage($parameters);
    }
}


function getNewSubtitles()
{
    global $LANGUAGE;

    $subtitles = getSubtitles();
    $trackedSubtitles = getTrackedSubtitles();

    $newSubtitles = [];
    foreach ($subtitles as $subtitle){
        //if subtitle language wasn't the one i want => pass
        if ($subtitle->lang != $LANGUAGE){
            continue;
        }

        $subtitleLink = explode("/", $subtitle->full_link);
        $subtitleID = end($subtitleLink);
        $subtitleName = $subtitleLink[2];
        $subtitleLang = $subtitleLink[3];

        //if subtitle already tracked => pass
        if (in_array($subtitleID, $trackedSubtitles)){
            continue;
        }

        $subName = getMovie(id: $subtitleID, lang:$subtitleLang, movie:$subtitleName, justSubName: true);
        $imdb = getImdb(id: $subtitleID, lang:$subtitleLang, movie:$subtitleName);
        $isSeries = $subtitle->type == "TV-Series" ? "🚺🚺🚺🚺🚺" : "";
        $serif = searchMovieInSerfil($imdb["slug"], $isSeries);
        $serifText = $serif ? "\n\nserfil: `yes` ✅ [SerFil](" . $serif . ")" : "\n\nserfil: `no` 🔴 ";

        $text = $isSeries .
        "\n\nname: `" . $subName . "`" .
        "\n\nimdb link: [imdb](" . $imdb['url'] . ")" .
        "\n\ntype: `" . $subtitle->type . "`" .
        $serifText .
        "\n\nsite url: [link](https://subsource.net" . $subtitle->full_link . ")";

        sendSubtitle($text); //send to telegram

        $newSubtitles[] = $subtitle;
        $trackedSubtitles[] = $subtitleID;
    }

    updateTrackedSubtitles($trackedSubtitles);

    return $newSubtitles;
}

print_r(getNewSubtitles());