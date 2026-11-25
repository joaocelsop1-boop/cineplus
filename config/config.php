<?php 
ini_set('default_charset', "utf-8");
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Bahia');

$site_url_env = getenv('SITE_URL');
if (!empty($site_url_env)) {
    define("SITE_URL", rtrim($site_url_env, '/'));
} else {
    // Atras de um proxy reverso (Coolify/Traefik) o Apache ve porta 80 mesmo
    // com HTTPS publico: respeitar X-Forwarded-Proto para o SITE_URL sair https.
    $ssl_proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) : '';
    $ssl = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
    $ssl_status = ($ssl_proto === 'https' || $ssl == 443) ? 'https' : 'http';
    define("SITE_URL", $ssl_status . "://" . $_SERVER['HTTP_HOST']);
}
define("DOCUMENT_ROOT", $_SERVER['DOCUMENT_ROOT']);

// Proxy HTTPS dos streams (controller/stream/stream_proxy.php): quando true, o
// player recebe URLs do proxy (mesma origem, HTTPS, sem as credenciais do
// painel IPTV). Quando false, volta a URL direta do painel (so use se o
// painel tiver HTTPS proprio, senao o navegador bloqueia o video).
define("STREAM_PROXY_ATIVO", true);

require_once DOCUMENT_ROOT.'/config/database.php';

try{ 
    $pdo = new PDO(DB_CON, DB_USER , DB_PASS);
}catch(Exception $error){
    die("Erro de conexão com o banco de dados.");
}

define("SITE_PAGINACAO", 99);
define("BASE_STREAM", SITE_URL.'/stream/');
define("BASE_PUBLIC", SITE_URL.'/');
define("BASE_USER", SITE_URL . '/user/');
define("BASE_CLIENTE", SITE_URL . '/cliente/');
define("BASE_ADMIN", SITE_URL . '/painel-admin/');

define("BASE_JS", SITE_URL . '/assets/js/');
define("BASE_PLUGINS", SITE_URL.'/assets/plugins/'); 
define("BASE_CSS", SITE_URL . '/assets/css/');

define("BASE_IMAGES_PATCH", DOCUMENT_ROOT . '/assets/images/');
define("BASE_IMAGES_URL", SITE_URL . '/assets/images/');

require_once DOCUMENT_ROOT.'/model/system/ajax_response.php';
require_once DOCUMENT_ROOT.'/model/system/diretorio.php';
require_once DOCUMENT_ROOT.'/model/system/hash.php';
require_once DOCUMENT_ROOT.'/model/system/inputs.php';
require_once DOCUMENT_ROOT.'/model/system/smtp.php';
require_once DOCUMENT_ROOT.'/model/system/upload.php';
require_once DOCUMENT_ROOT.'/model/system/site_perfil.php';
require_once DOCUMENT_ROOT.'/model/system/premium.php';

require_once DOCUMENT_ROOT.'/model/stream/servidor_iptv.php';
require_once DOCUMENT_ROOT.'/model/stream/stream.php';
require_once DOCUMENT_ROOT.'/model/stream/stream_assistindo.php';
require_once DOCUMENT_ROOT.'/model/stream/stream_lista.php';

require_once DOCUMENT_ROOT.'/model/user/user.php';
require_once DOCUMENT_ROOT.'/model/user/sessao.php';
require_once DOCUMENT_ROOT.'/model/user/user_recuperar_senha.php';
require_once DOCUMENT_ROOT.'/model/user/template_email.php';

require_once DOCUMENT_ROOT.'/model/cliente/cliente.php';
require_once DOCUMENT_ROOT.'/model/cliente/cliente_premium.php';
require_once DOCUMENT_ROOT.'/model/cliente/cliente_perfil.php';

require_once DOCUMENT_ROOT.'/model/mercadopago/mercadopago.php';
require_once DOCUMENT_ROOT.'/model/venda/venda.php';



