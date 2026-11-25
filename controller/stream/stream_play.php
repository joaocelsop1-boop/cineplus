<?php 
require_once $_SERVER['DOCUMENT_ROOT'].'/controller/cliente/cliente_controller.php';

// URL do player: via proxy HTTPS (STREAM_PROXY_ATIVO) para nao expor as
// credenciais do painel e nao cair no bloqueio de mixed content; se o flag
// estiver desligado, volta a URL direta do painel (exige painel com HTTPS).
function stream_player_url($stream_type, $stream_id, $episodio, $ext, $url_direta){
    if (defined('STREAM_PROXY_ATIVO') && STREAM_PROXY_ATIVO) {
        $url = BASE_STREAM . 'proxy/' . $stream_type . '/' . $stream_id;
        if ($stream_type === 'series') {
            $url .= '/' . intval($episodio);
        }
        return $url . '.' . $ext;
    }
    return $url_direta;
}

if(isset($_POST['acao'])){


    if(!isset($_POST['stream_id'])){
        $res = array("status" => "indisponivel");
        die(json_encode($res));    
    }

    if(!$cliente_is_premium){
        $res = array("status" => "premium");
        die(json_encode($res));
    }

    if($_POST['acao'] == 'series_play'){
        if(!isset($_POST['episodio']) OR !is_numeric($_POST['episodio']) OR $_POST['episodio'] < 0){
            $res = array("status" => "indisponivel1");
            die(json_encode($res));   
        }
        
        $episodio = stream_get_serie_episodio($_POST['stream_id'], $_POST['episodio']); 
        $container_extension = !empty($episodio->container_extension) ? $episodio->container_extension : 'mp4';
        $stream_assistindo_get = stream_assistindo_get($_POST['stream_id'], $_POST['episodio'], "series", $cliente_perfil['cliente_perfil_id'], $cliente['user_id']);
        $stream_assistindo_time = !empty($stream_assistindo_get['stream_assistindo_time']) ? $stream_assistindo_get['stream_assistindo_time'] : '';

        $url_direta = SERVIDOR_IPTV_HOST.'series/'.SERVIDOR_IPTV_USUARIO.'/'.SERVIDOR_IPTV_SENHA.'/'.$episodio->id.'.' . $episodio->container_extension;
        $url = stream_player_url('series', $_POST['stream_id'], $_POST['episodio'], $episodio->container_extension, $url_direta);
        $res = array("status" => "ok", "player_url" => $url, "stream_assistindo_time" => $stream_assistindo_time);
        die(json_encode($res));

    }    
   
    if($_POST['acao'] == 'movie_play'){

        $stream_assistindo_get  = stream_assistindo_get($_POST['stream_id'], 0, "movie", $cliente_perfil['cliente_perfil_id'], $cliente['user_id']);
        $stream_assistindo_time = !empty($stream_assistindo_get['stream_assistindo_time']) ? $stream_assistindo_get['stream_assistindo_time'] : '';
        $movie                  = stream_get_movie($_POST['stream_id']);
        $container_extension    = !empty($movie['data']->movie_data->container_extension) ? $movie['data']->movie_data->container_extension : 'mp4';
         
        $url_direta = SERVIDOR_IPTV_HOST.'movie/'.SERVIDOR_IPTV_USUARIO.'/'.SERVIDOR_IPTV_SENHA.'/'.$_POST['stream_id'].'.'.$container_extension;
        $url = stream_player_url('movie', $_POST['stream_id'], 0, $container_extension, $url_direta);
        $res = array("status" => "ok", "player_url" => $url, "stream_assistindo_time" => $stream_assistindo_time);
        die(json_encode($res));

    }

    if($_POST['acao'] == 'live_play'){

        $live                   = stream_get_live($_POST['stream_id']);
        $container_extension    = !empty($live['data']->movie_data->container_extension) ? $live['data']->movie_data->container_extension : 'm3u8';
        
        $url_direta = SERVIDOR_IPTV_HOST.'live/'.SERVIDOR_IPTV_USUARIO.'/'.SERVIDOR_IPTV_SENHA.'/'.$_POST['stream_id'].'.'.$container_extension;
        $url = stream_player_url('live', $_POST['stream_id'], 0, $container_extension, $url_direta);
        $res = array("status" => "ok", "player_url" => $url, "stream_assistindo_time" => '');
        die(json_encode($res));

    }

    die(json_encode(array("status" => "error")));


}else{
    die(json_encode(array("status" => "error")));
}