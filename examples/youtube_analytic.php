<?php
/*
 * 
 * Credenciales:
 * DTA-UTP: dtautp@gmail.com - pass: direccionta
 * PPE-UTP: ppeutp@gmail.com - pass: ppe2015utp
 * 
 */
require_once realpath(dirname(__FILE__) . '/../src/Google/autoload.php');
set_time_limit(0);

class youtube_dta {

    public $htmlBody = "";
    public $OAUTH2_CLIENT_ID = '440100276779-vmg3fc88snk3sf35f9no1m7bn33gasef.apps.googleusercontent.com';    // UTP-DTA
    //public $OAUTH2_CLIENT_ID = '992771450143-ipo08jusblh9ml3nld72rd4ehkpqjuc3.apps.googleusercontent.com';      // UTP-PPE
    public $OAUTH2_CLIENT_SECRET = '5iJJbcEG8N6yrxQcj953dvXG';        // UTP-DTA
    //public $OAUTH2_CLIENT_SECRET = '-54T8H9TiqOBetJxF_IBkixJ';          // UTP-PPE
    public $client;
    public $redirect;
    public $youtube;
    public $id_query = array();
    public $view_query = array();
    public $porcentaje_query = array();
    public $json_file;

    public function __construct() {
        session_start();

        $this->client = new Google_Client();
        $this->client->setClientId($this->OAUTH2_CLIENT_ID);
        $this->client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
        $this->client->setScopes(array('https://www.googleapis.com/auth/youtube.readonly', 'https://www.googleapis.com/auth/yt-analytics.readonly'));
        $this->redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $this->client->setRedirectUri($this->redirect);

        $this->youtube = new Google_Service_YouTube($this->client);

        if (isset($_GET['code'])) {
            $this->client->authenticate($_GET['code']);
            $_SESSION['token'] = $this->client->getAccessToken();
            header('Location: ' . $this->redirect);
        }
        isset($_SESSION['token']) && ($this->client->setAccessToken($_SESSION['token']));

        $this->get_query();
    }

    function get_auth() {
        $state = mt_rand();
        $this->client->setState($state);
        $_SESSION['state'] = $state;

        $authUrl = $this->client->createAuthUrl();
        echo <<<END
            <h3>Autorización requerida</h3>
            <p>Se necesita <a href="$authUrl">autorizar el acceso</a>.<p>
END;
    }

    public function get_query() {

        if ($this->client->getAccessToken()) {
            $analytics = new Google_Service_YouTubeAnalytics($this->client);

            $start_date = (isset($_POST['inicio']) && $_POST['inicio'] != "") ? $_POST['inicio'] : '2015-05-04'; //(date('Y-m')) . '-' . (date('d') - 7);

            $end_date = (isset($_POST['fin']) && $_POST['fin'] != "") ? $_POST['fin'] : '2015-08-19'; //(date('Y-m')) . '-' . (date('d') - 1);

            $id = 'channel==UCq9lG8g0ru5ciCHX1ZxuEug';      // UTP-DTA
            //$id = 'channel==UC3wqL11PkFaWOvX82ibrE4Q';        // UTP-PPE

            $optparams = array(
                'max-results' => 200,
                'sort' => '-views',
                'dimensions' => 'video',
                'start-index' => 1
            );

            $metrics = 'views,averageViewPercentage';

            try {
                $api = $analytics->reports->query($id, $start_date, $end_date, $metrics, $optparams);

                $estadisticas = array($api['rows']);

                foreach ($estadisticas as $vrpt) {
                    foreach ($vrpt as $vid) {
                        $this->id_query[] = $vid[0];
                        $this->view_query[$vid[0]] = $vid[1];
                        $this->porcentaje_query[$vid[0]] = $vid[2];
                    }
                }
                $this->htmlBody = "<h3>Del: " . $start_date . " - Al: " . $end_date . "</h3><br>";
            } catch (Exception $ex) {
                $this->get_auth();
            }
        } else {
            $this->get_auth();
        }
    }

    function play_list() {
        $part = 'snippet';
        $opt_params = array(
            'mine' => true,
            'maxResults' => '50'
        );

        try {
            $api_playlist = $this->youtube->playlists->listPlaylists($part, $opt_params);

            $this->htmlBody .= "<table border='1' cellspacing='0' cellpadding='0'>";
            $this->htmlBody .= "<tr><th style='padding:7px;'>Nro</th>";
            $this->htmlBody .= "<th style='padding:7px;'>Lista de Rep.</th>";
            $this->htmlBody .= "<th style='padding:7px;'>Video</th>" .
                    "<th style='padding:7px;'>Publicaci&oacute;n</th>" .
                    "<th style='padding:7px;'>Duraci&oacute;n</th>" .
                    "<th style='padding:7px;'>Vistas</th>" .
                    "<th style='padding:7px;'>Promedio porcentaje de vistas</th>" .
                    "<th style='padding:7px;'>Comentarios</th>" .
                    "<th style='padding:7px;'>URL</th></tr>";

            foreach ($api_playlist as $v) {
                $id = $v['id'];
                $title_channel = $v['snippet']['title'];

                //$this->htmlBody .= "<h2>Lista de reproduccion: " . $title_channel . "</h2><br>";

                /* $this->htmlBody .= "<table border='1' cellspacing='0' cellpadding='0'>";
                  $this->htmlBody .= "<tr><th style='padding:7px;'>Nro</th>";
                  $this->htmlBody .= "<th style='padding:7px;'>Lista de Rep.</th>";
                  $this->htmlBody .= "<th style='padding:7px;'>Video</th>" .
                  "<th style='padding:7px;'>Publicaci&oacute;n</th>" .
                  "<th style='padding:7px;'>Duraci&oacute;n</th>" .
                  "<th style='padding:7px;'>Vistas</th>" .
                  "<th style='padding:7px;'>Promedio porcentaje de vistas</th>" .
                  "<th style='padding:7px;'>Comentarios</th>".
                  "<th style='padding:7px;'>URL</th></tr>"; */

                $this->play_list_item($title_channel, $id, null, 0);

                /* $this->htmlBody .= "</table><br>";
                  $this->htmlBody .= "<hr>"; */
            }

            $this->htmlBody .= "</table><br>";
            $this->htmlBody .= "<hr>";
        } catch (Exception $ex) {
            $this->get_auth();
        }
    }

    function play_list_item($play_list, $id, $pageToken, $n) {
        $list_videos = "";
        $duracion = "";
        $cnt = $n;

        $optItem = array(
            'playlistId' => $id,
            'maxResults' => '50',
            'pageToken' => $pageToken
        );

        $play_list_item = $this->youtube->playlistItems->listPlaylistItems('snippet', $optItem);

        foreach ($play_list_item['items'] as $vlst) {
            $cnt++;
            $video_title = $vlst['snippet']['title'];
            $video_id = $vlst['snippet']['resourceId']['videoId'];
            $publishedAt = $vlst['snippet']['publishedAt'];

            $list_videos = $this->videos($video_id);

            $duracion = ($list_videos['items'][0]['contentDetails']['duration'] === null) ? "0" : $list_videos['items'][0]['contentDetails']['duration'];

            //$embedHTML = $list_videos['items'][0]['player']['embedHtml'];
            $estadisticas = $list_videos['items'][0]['statistics']['commentCount'];

            if (in_array($video_id, $this->id_query)) {
                $this->htmlBody .= "<tr><td style='padding:7px;'>" . $cnt .
                        "</td><td style='padding:7px;'>" . $play_list .
                        "</td><td style='padding:7px;'>" . $video_title .
                        "</td><td style='padding:7px;'>" . $publishedAt .
                        "</td><td style='padding:7px;'>" . $duracion .
                        "</td><td style='padding:7px;'>" . $this->view_query[$video_id] .
                        "</td><td style='padding:7px;'>" . round($this->porcentaje_query[$video_id]) . "%" .
                        "</td><td style='padding:7px;'>" . $estadisticas .
                        "</td><td style='padding:7px;'>https://www.youtube.com/watch?v=" . $video_id . "</tr>";
                /* "</td><td style='padding:7px;'><pre>" . htmlentities($embedHTML) . "</pre></tr>"; */
            }
        }

        $next = $play_list_item['nextPageToken'];

        $next != null && ($this->play_list_item($play_list, $id, $next, $cnt));
    }

    function videos($video_id) {

        $opt_videos = array(
            'maxResults' => '1',
            'id' => $video_id
        );

        return $this->youtube->videos->listVideos('contentDetails, player, statistics', $opt_videos);
    }

}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Youtube</title>
        <script type="text/javascript" src="js/jquery-1.11.3.js"></script>
        <script type="text/javascript" src="js/jquery-ui.js"></script>
        <script type="text/javascript" src="js/analytics.js"></script>
        <link href="styles/jquery-ui.css" type="text/css" rel="stylesheet" />
        <link href="styles/youtube_analytics.css" type="text/css" rel="stylesheet" />
    </head>
    <body>
        <div id="container">
            <form method="post" action="#">
                <label for="inicio">De: </label>
                <input type="text" id="inicio" name="inicio" />
                <label for="fin">Hasta: </label>
                <input type="text" id="fin" name="fin" /><br><br>
                <!--<label for="channel">Canal: </label>
                <select id="channel" name="channel">
                    <option value="0" selected>Seleccione canal</option>
                    <option value="UCmVPtOPn8o2QuJq2FnL2D5g">DTA UTP</option>
                    <option value="UCq9lG8g0ru5ciCHX1ZxuEug">Direcci&oacute;n de Tecnolog&iacute;as para el Aprendizaje</option>
                    <option value="UCpAPBJHsP6NgYQ42RD1jUng">Ciencias UTP</option>
                    <option value="UCblNfNnkLxdto0_rnZf6EnA">Semipresenciales</option>
                </select>-->
                <input type="submit" value="Consultar" />&nbsp;
                <input type="button" id="btnexportar" value="Exportar" />
            </form>
            <?php
            $yt = new youtube_dta();
            $yt->play_list();
            echo $yt->htmlBody;
            ?>
        </div>
    </body>
</html>