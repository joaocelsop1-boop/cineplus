<?php
// ============================================================================
// PROXY HTTPS DOS STREAMS (2026-11-25)
// ----------------------------------------------------------------------------
// O painel IPTV e alugado e so fala HTTP (porta 80). Como o site roda em
// HTTPS, o video NAO pode vir direto do painel: o navegador bloqueia
// (mixed content) e o player nao carrega. Este proxy resolve isso:
//
//   - o navegador pede o video SEMPRE para o proprio site, em HTTPS;
//   - o servidor busca o video no painel (HTTP, no backend) e repassa;
//   - usuario/senha do painel NUNCA aparecem em URL, HTML, JS ou log.
//
// Rotas (.htaccess):
//   /stream/proxy/movie/{id}.{ext}          -> filme (mp4/mkv/avi/...)
//   /stream/proxy/series/{id}/{ep}.{ext}    -> episodio de serie
//   /stream/proxy/live/{id}.m3u8            -> canal ao vivo (HLS)
//   /stream/proxy/ts/live/{arquivo}.ts      -> segmento .ts do HLS
//   /stream/proxy/pl/live/{arquivo}.m3u8    -> playlist HLS aninhada
//
// Seguranca: exige login + premium ativo (mesma regra de stream_play.php).
// So o host do painel configurado e acessado; qualquer outra URL e recusada.
// ============================================================================
require_once $_SERVER['DOCUMENT_ROOT'].'/controller/cliente/cliente_controller.php';

if (!$cliente_is_premium) {
    http_response_code(403);
    die('Premium inativo.');
}

if (empty(SERVIDOR_IPTV_HOST) || empty(SERVIDOR_IPTV_USUARIO) || empty(SERVIDOR_IPTV_SENHA)) {
    http_response_code(503);
    die('Servidor IPTV nao configurado.');
}

// ---------------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------------

function proxy_content_type($ext) {
    $ext = strtolower($ext);
    $map = array(
        'mp4'  => 'video/mp4',
        'mkv'  => 'video/x-matroska',
        'avi'  => 'video/x-msvideo',
        'mov'  => 'video/quicktime',
        'webm' => 'video/webm',
        'm3u8' => 'application/vnd.apple.mpegurl',
        'ts'   => 'video/MP2T',
        'mp2t' => 'video/MP2T',
        'm2ts' => 'video/MP2T',
        'mpg'  => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'vob'  => 'video/mpeg',
        'flv'  => 'video/x-flv',
        'wmv'  => 'video/x-ms-wmv',
        'asf'  => 'video/x-ms-asf',
        '3gp'  => 'video/3gpp',
        'm4v'  => 'video/x-m4v',
        'f4v'  => 'video/mp4',
        'rm'   => 'application/vnd.rn-realmedia',
        'rmvb' => 'application/vnd.rn-realmedia',
        'divx' => 'video/divx',
        'ogv'  => 'video/ogg',
    );
    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

function proxy_painel_host() {
    $host = parse_url(SERVIDOR_IPTV_HOST, PHP_URL_HOST);
    $port = parse_url(SERVIDOR_IPTV_HOST, PHP_URL_PORT);
    return array('host' => $host, 'port' => $port);
}

function proxy_url_painel($caminho_relativo) {
    // $caminho_relativo ex.: "movie/123.mp4" (sem usuario/senha; eles sao
    // colocados aqui, no servidor, e nunca saem daqui).
    return rtrim(SERVIDOR_IPTV_HOST, '/') . '/' . $caminho_relativo;
}

function proxy_montar_url_painel($tipo, $resto) {
    return proxy_url_painel($tipo . '/' . SERVIDOR_IPTV_USUARIO . '/' . SERVIDOR_IPTV_SENHA . '/' . $resto);
}

// Repassa um arquivo do painel para o navegador em blocos, com suporte a
// Range (o player pede pedacos do video; sem isso nao da para avancar/voltar).
function proxy_repassar($url, $content_type) {
    $http_status = 200;
    $descartar_corpo = false;
    $cabecalhos = array();
    if (!empty($_SERVER['HTTP_RANGE'])) {
        $cabecalhos[] = 'Range: ' . trim($_SERVER['HTTP_RANGE']);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $cabecalhos);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$http_status, &$descartar_corpo) {
        $len = strlen($header);
        $header = trim($header);
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
            $http_status = (int)$m[1];
            if ($http_status === 206) {
                http_response_code(206);
            } elseif ($http_status >= 400) {
                $descartar_corpo = true;
            }
            return $len;
        }
        // Repassa so o necessario para o player; o Content-Type e o nosso.
        if (preg_match('#^(Content-Range|Content-Length|Accept-Ranges):#i', $header)) {
            header($header, false);
        }
        return $len;
    });
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$descartar_corpo) {
        if ($descartar_corpo) {
            return strlen($data);
        }
        echo $data;
        flush();
        return strlen($data);
    });

    ignore_user_abort(true);
    set_time_limit(0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: ' . $content_type);
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-cache');

    $ok = curl_exec($ch);
    curl_close($ch);

    if ($ok === false || $http_status >= 400) {
        // Nada do painel foi exibido (o corpo foi descartado); erro generico,
        // sem expor a URL do painel.
        http_response_code($http_status >= 400 ? $http_status : 502);
        die('Stream indisponivel.');
    }
    exit;
}

// Baixa uma playlist .m3u8 do painel (segue redirect no backend).
function proxy_buscar_playlist($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        return false;
    }
    return array('body' => $body, 'url' => $final ? $final : $url);
}

// Resolve uma URI (absoluta ou relativa) contra a URL base da playlist.
function proxy_resolver_uri($uri, $base) {
    if (preg_match('#^https?://#i', $uri)) {
        return $uri;
    }
    $p = parse_url($base);
    $origem = $p['scheme'] . '://' . $p['host'];
    if (!empty($p['port'])) {
        $origem .= ':' . $p['port'];
    }
    if (substr($uri, 0, 1) === '/') {
        return $origem . $uri;
    }
    $dir = substr($p['path'], 0, strrpos($p['path'], '/') + 1);
    return $origem . $dir . $uri;
}

// Reescreve uma URL do painel para a URL do proxy. URLs de outros hosts
// (ex.: CDN do proprio painel) sao devolvidas como estao.
function proxy_reescrever_url($uri, $base_url) {
    $absoluta = proxy_resolver_uri($uri, $base_url);
    $p = parse_url($absoluta);
    $painel = proxy_painel_host();
    $porta_url = isset($p['port']) ? (int)$p['port'] : null;
    $porta_painel = $painel['port'] !== null ? (int)$painel['port'] : null;
    if (!isset($p['host']) || strtolower($p['host']) !== strtolower($painel['host']) || $porta_url !== $porta_painel) {
        return $absoluta;
    }
    $caminho = ltrim($p['path'], '/');
    // Esperado: {tipo}/{usuario}/{senha}/{resto} — so reescreve se bater.
    if (!preg_match('#^(movie|series|live)/([^/]+)/([^/]+)/(.+)$#', $caminho, $m)) {
        return $absoluta;
    }
    if ($m[2] !== SERVIDOR_IPTV_USUARIO || $m[3] !== SERVIDOR_IPTV_SENHA) {
        return $absoluta;
    }
    $tipo = $m[1];
    $resto = $m[4];
    $query = isset($p['query']) && $p['query'] !== '' ? '?' . $p['query'] : '';
    $ext = strtolower(pathinfo($resto, PATHINFO_EXTENSION));
    if ($ext === 'm3u8') {
        return SITE_URL . '/stream/proxy/pl/' . $tipo . '/' . $resto . $query;
    }
    return SITE_URL . '/stream/proxy/ts/' . $tipo . '/' . $resto . $query;
}

// Reescreve TODAS as URLs de uma playlist para passarem pelo proxy
// (segmentos .ts, playlists aninhadas e chaves #EXT-X-KEY / #EXT-X-MAP).
function proxy_reescrever_playlist($conteudo, $base_url) {
    $linhas = explode("\n", $conteudo);
    $saida = array();
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '') {
            $saida[] = $linha;
            continue;
        }
        if ($linha[0] === '#') {
            if (strpos($linha, 'URI=') !== false) {
                $linha = preg_replace_callback('/URI="([^"]+)"/', function($m) use ($base_url) {
                    return 'URI="' . proxy_reescrever_url($m[1], $base_url) . '"';
                }, $linha);
            }
            $saida[] = $linha;
            continue;
        }
        $saida[] = proxy_reescrever_url($linha, $base_url);
    }
    return implode("\n", $saida);
}

function proxy_servir_playlist($url_painel) {
    $res = proxy_buscar_playlist($url_painel);
    if ($res === false) {
        http_response_code(502);
        die('Playlist indisponivel.');
    }
    $reescrita = proxy_reescrever_playlist($res['body'], $res['url']);
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-cache');
    echo $reescrita;
    exit;
}

// ---------------------------------------------------------------------------
// Roteamento
// ---------------------------------------------------------------------------

$tipo = isset($_GET['stream_type']) ? $_GET['stream_type'] : '';
$id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$episodio = isset($_GET['episodio']) ? intval($_GET['episodio']) : 0;
$ext_url = isset($_GET['ext']) ? strtolower($_GET['ext']) : '';
$kind = isset($_GET['proxy_kind']) ? $_GET['proxy_kind'] : '';
$caminho = isset($_GET['path']) ? $_GET['path'] : '';

// --- Modo B: sub-recurso do HLS (segmento .ts ou playlist aninhada) --------
if ($kind === 'ts' || $kind === 'pl') {
    if (!in_array($tipo, array('movie', 'series', 'live'), true) || $caminho === ''
        || strpos($caminho, '..') !== false
        || !preg_match('#^[A-Za-z0-9][A-Za-z0-9/_\-.]*$#', $caminho)) {
        http_response_code(400);
        die('Requisicao invalida.');
    }
    $url_painel = proxy_montar_url_painel($tipo, $caminho);
    if ($kind === 'pl') {
        proxy_servir_playlist($url_painel);
    }
    proxy_repassar($url_painel, proxy_content_type(pathinfo($caminho, PATHINFO_EXTENSION)));
}

// --- Modo A: stream direto (filme / episodio / ao vivo) --------------------
if (!in_array($tipo, array('movie', 'series', 'live'), true) || $id <= 0) {
    http_response_code(400);
    die('Requisicao invalida.');
}

if ($tipo === 'movie') {
    $movie = stream_get_movie($id);
    if (!empty($movie['data']) && !empty($movie['data']->movie_data) && !empty($movie['data']->movie_data->container_extension)) {
        $ext = $movie['data']->movie_data->container_extension;
    } elseif ($ext_url !== '') {
        // O cache/painel nao devolveram a extensao: tenta com a extensao da
        // propria URL em vez de dar 404 direto; o painel devolve 404 se o
        // arquivo nao existir.
        $ext = $ext_url;
    } else {
        $ext = 'mp4';
    }
    proxy_repassar(proxy_montar_url_painel('movie', $id . '.' . $ext), proxy_content_type($ext));
}

if ($tipo === 'series') {
    $ep = stream_get_serie_episodio($id, $episodio);
    if (empty($ep) || empty($ep->id)) {
        http_response_code(404);
        die('Episodio nao encontrado.');
    }
    $ext = !empty($ep->container_extension) ? $ep->container_extension : 'mp4';
    proxy_repassar(proxy_montar_url_painel('series', $ep->id . '.' . $ext), proxy_content_type($ext));
}

if ($tipo === 'live') {
    $live = stream_get_live($id);
    $ext = 'm3u8';
    if (!empty($live['data']) && !empty($live['data']->movie_data) && !empty($live['data']->movie_data->container_extension)) {
        $ext = $live['data']->movie_data->container_extension;
    }
    // A URL pode pedir .m3u8 explicitamente; o comportamento segue a extensao.
    if ($ext_url !== '' && $ext_url !== 'm3u8') {
        $ext = $ext_url;
    }
    $url_painel = proxy_montar_url_painel('live', $id . '.' . $ext);
    if (strtolower($ext) === 'm3u8') {
        proxy_servir_playlist($url_painel);
    }
    proxy_repassar($url_painel, proxy_content_type($ext));
}

http_response_code(400);
die('Requisicao invalida.');
