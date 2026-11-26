<?php 
$stream_cache_get = stream_cache_get();
function stream_get_from_server($parametro, $servidor=array()){
    $error = array();

    if(count($servidor) > 0){
        $servidor_iptv_host     = $servidor['servidor_iptv_host'];
        $servidor_iptv_usuario  = $servidor['servidor_iptv_usuario'];
        $servidor_iptv_senha    = $servidor['servidor_iptv_senha'];
        $servidor_iptv_url      = $servidor_iptv_host.'player_api.php?username='.$servidor_iptv_usuario.'&password='.$servidor_iptv_senha.'&action=';
    }else{
        $servidor_iptv_url = SERVIDOR_IPTV_URL;
    }

    // Uma so requisicao (antes o curl_exec era chamado 2 vezes) e com
    // timeout: sem isso o painel lento travava o salvamento do servidor e
    // o carregamento do player.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $servidor_iptv_url.$parametro);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $body = curl_exec($ch);
    curl_close($ch);
    if($body === false) {
        return $error;
    }
    $result = json_decode($body);
    if($result === null){
        return $error;
    }
    return $result;
}
 
function stream_cache_criar($servidor=array()){
    
    $cache = $_SERVER['DOCUMENT_ROOT'].'/cache/stream.php';
    $content = array(
        "movie" => stream_get_from_server('get_vod_streams', $servidor),
        "series" => stream_get_from_server('get_series', $servidor),
        "live" => stream_get_from_server('get_live_streams', $servidor),
        "live_categories" => stream_get_from_server('get_live_categories', $servidor),
        "vod_categories" => stream_get_from_server('get_vod_categories', $servidor),
        "series_categories" => stream_get_from_server('get_series_categories', $servidor),
    );    
    $json_content = json_encode($content);
    file_put_contents($cache, $json_content);
}  

function stream_cache_atualizar(){
    $cache = $_SERVER['DOCUMENT_ROOT'].'/cache/stream.php';
    if(file_exists($cache)){
        $cache_atualizado = date("Y-m-d H:i", filemtime($cache));
        $cache_atualizar = date('Y-m-d H:i', strtotime($cache_atualizado. " + 24 hours"));
        if(date("Y-m-d H:i") >= $cache_atualizar){
            stream_cache_criar();
        }
    }else{
        stream_cache_criar();
    }
}

function stream_cache_get(){

    $error = array();
    if(file_exists($_SERVER['DOCUMENT_ROOT'].'/cache/stream.php')){
        $json_content = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/cache/stream.php');
        return json_decode($json_content);
    }
    return $error; 

}

function stream_cache_remove(){
    $cache = $_SERVER['DOCUMENT_ROOT'].'/cache/stream.php';
    if(file_exists($cache)){
        unlink($cache);
    }
}

function get_stream_category($category){
    global $stream_cache_get;
 
    if(!isset($stream_cache_get->$category) OR !is_array($stream_cache_get->$category) OR !isset($stream_cache_get->$category[0])){
        return array();
    }
    return $stream_cache_get->$category;
} 

function get_stream_by_category($stream_type, $category_id, $pagina){
    global $stream_cache_get;
    $content = (array)$stream_cache_get->$stream_type;
    $array_content = array("stream" => array(), "stream_total" => 0);
    foreach($content as $item){
        if(isset($item->category_id) && $item->category_id == $category_id){
            $stream[] = $item;
        }
    }
    if(isset($stream) && count($stream) > 0){
        $start  = $pagina * SITE_PAGINACAO - SITE_PAGINACAO;
        $array_stream = array_slice($stream, $start, SITE_PAGINACAO);
        $array_content = array("stream" => $array_stream, "stream_total" => count($stream));
        
    }
    return $array_content;
    
}

    
function get_stream_category_home($category){
    $categorias = (array)get_stream_category($category);


    $total_categorias = count($categorias);
    $item = array();

    if($total_categorias > 0){
        $limite = $total_categorias >= 5 ? 5 : $total_categorias;
        $rand = array_rand($categorias, $limite);

        foreach($rand as $cat){
            $item[] = $categorias[$cat];
        }
    }

   
    return $item;
} 

function get_stream_by_category_home($stream_type, $category_id, $pagina, $limite){
    global $stream_cache_get;
    $content = (array)$stream_cache_get->$stream_type;
    
    $array_content = array("stream" => array(), "stream_total" => 0);

    

    foreach($content as $item){
        if(isset($item->category_id) && $item->category_id == $category_id){
            $stream[] = $item;
        }
    }

    if(isset($stream) && count($stream) > 0){
        $start  = $pagina * $limite - $limite;
        $array_stream = array_slice($stream, $start, $limite);
        $array_content = array("stream" => $array_stream, "stream_total" => count($stream));
    }
    return $array_content;
}



function stream_duration($duration){
    $a = explode(":", $duration);
    if($a[0] == "00"){
        return  $a[1] . "min ";
    }
    return substr($a[0],1) . 'h ' . $a[1] . 'min ';
}

function stream_busca($busca, $pagina){ 
    global $stream_cache_get;
    $busca = str_replace('-', ' ', $busca);
    $busca = trim($busca);
    $content = $stream_cache_get;

    $stream = array();
    
    if(isset($content->movie)){
        foreach($content->movie as $m){
            if(isset($m->name) && preg_match("/{$busca}/i", $m->name)){
                $m->stream_type = 'movie';
                $stream[] = $m;
            }
        }
    }

    if(isset($content->series)){
        foreach($content->series as $s){
            if(isset($s->name) && preg_match("/{$busca}/i", $s->name)){
                $s->stream_type = 'series';
                $stream[] = $s;
            }
        }
    }

    if(isset($content->live)){
        foreach($content->live as $l){
            if(isset($l->name) && preg_match("/{$busca}/i", $l->name)){
                $l->stream_type = 'live';
                $stream[] = $l;
            }
        }
    }

    if(count($stream) < 1){
        return array("stream" => array(), "stream_total" => 0);
    }

    $start  = $pagina * SITE_PAGINACAO - SITE_PAGINACAO;
    $array_stream = array_slice($stream, $start, SITE_PAGINACAO);
    $array_content = array("stream" => $array_stream, "stream_total" => count($stream));
    
    return $array_content;

}

function stream_assistindo_cache($cliente_perfil_id, $cliente_id){
    global $stream_cache_get;
    $content = $stream_cache_get;
    $stream = array();

    $stream_assistindo_listar = stream_assistindo_listar($cliente_perfil_id, $cliente_id);

    foreach($stream_assistindo_listar as $item){

        if($item['stream_assistindo_type'] == 'series'){

            foreach($content->series as $s){
                if($s->series_id == $item['stream_assistindo_stream']){
                    $s->stream_type = 'series';
                    $s->stream_assistindo_stream = $item['stream_assistindo_episodio'];
                    $stream[] = $s;
                    
                }
                
            }

        }
        if($item['stream_assistindo_type'] == 'movie'){

            foreach($content->movie as $m){
                if($m->stream_id == $item['stream_assistindo_stream']){
                    
                    $stream[] = $m;
                }
                
            }

        }
        if($item['stream_assistindo_type'] == 'live'){
            
            foreach($content->live as $l){
                if($l->stream_id == $item['stream_assistindo_stream']){
                    $stream[] = $l;
                }
            }

        }


    }

    return $stream;
}


function stream_minha_lista_cache($cliente_perfil_id, $cliente_id){
    global $stream_cache_get;
    $content = $stream_cache_get;
    $stream = array();

    $stream_minha_lista_cache = stream_lista_listar($cliente_perfil_id, $cliente_id);

    foreach($stream_minha_lista_cache as $item){

        if($item['stream_lista_type'] == 'series'){

            foreach($content->series as $s){
                if($s->series_id == $item['stream_lista_stream']){
                    $s->stream_type = 'series';
                    $stream[] = $s;
                }
                
            }

        }
        if($item['stream_lista_type'] == 'movie'){

            foreach($content->movie as $m){
                if($m->stream_id == $item['stream_lista_stream']){
                    $stream[] = $m;
                }
                
            }

        }
        if($item['stream_lista_type'] == 'live'){
            
            foreach($content->live as $l){
                if($l->stream_id == $item['stream_lista_stream']){
                    $stream[] = $l;
                }
            }

        }


    }

    return $stream;
}

function stream_type_tradutor($stream_type, $opcao){
    if($opcao == 'plural'){
        if($stream_type == 'movie'){
            return 'Filmes';
        }
        if($stream_type == 'series'){
            return 'Séries';
        }
        if($stream_type == 'live'){
            return 'Canais';
        }
    }
    if($opcao == 'single'){
        if($stream_type == 'movie'){
            return 'Filme';
        }
        if($stream_type == 'series'){
            return 'Série';
        }
        if($stream_type == 'live'){
            return 'Canal';
        }
    }
}

function stream_live_title($title){
    $title = strtolower($title);
    $title = ucwords($title);
    $title = str_replace('(h265)', '', $title);
    $title = str_replace('[fhd]', 'Full Hd', $title);
    $title = str_replace('[hd]', 'Hd', $title);
    $title = str_replace('[sd]', 'Sd', $title);
    $title = str_replace('[m]', '', $title);
    return $title;
}

function stream_categorie_title($title){
    $title = strtolower($title);
    $title = ucwords($title);
    $title = str_replace('|', '', $title);
    return $title;
}

function stream_get_movie($id){
    // 1) Cache primeiro: o catalogo (get_vod_streams) ja traz stream_id e
    // container_extension. Antes, cada play fazia um get_vod_info AO VIVO no
    // painel; quando essa chamada falhava ou demorava, o proxy devolvia 404
    // e o player mostrava "The media could not be loaded".
    global $stream_cache_get;
    if(!empty($stream_cache_get) && !empty($stream_cache_get->movie)){
        foreach((array)$stream_cache_get->movie as $item){
            if(isset($item->stream_id) && (int)$item->stream_id === (int)$id){
                $ext = !empty($item->container_extension) ? $item->container_extension : 'mp4';
                $movie_data = (object)array('container_extension' => $ext, 'stream_id' => (int)$id);
                return array("status" => "ok", "data" => (object)array("movie_data" => $movie_data));
            }
        }
    }
    // 2) Fallback: get_vod_info ao vivo, com timeout e uma so requisicao.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SERVIDOR_IPTV_URL.'get_vod_info&vod_id='.$id);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $body = curl_exec($ch);
    curl_close($ch);
    if($body === false) {
        return array("status" => "error");
    }
    $result = json_decode($body);
    if($result === null){
        return array("status" => "error");
    }
    return array("status" => "ok", "data" => $result);
}    

function stream_get_live($id){
    // Mesmo motivo do filme: o get_vod_info nao serve para ao vivo e a
    // chamada ao vivo derrubava o play. O cache (get_live_streams) ja tem
    // stream_id e container_extension do canal.
    global $stream_cache_get;
    if(!empty($stream_cache_get) && !empty($stream_cache_get->live)){
        foreach((array)$stream_cache_get->live as $item){
            if(isset($item->stream_id) && (int)$item->stream_id === (int)$id){
                $ext = !empty($item->container_extension) ? $item->container_extension : 'm3u8';
                $movie_data = (object)array('container_extension' => $ext, 'stream_id' => (int)$id);
                return array("status" => "ok", "data" => (object)array("movie_data" => $movie_data));
            }
        }
    }
    // Fallback: get_vod_info ao vivo (o painel nao tem get_live_info).
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SERVIDOR_IPTV_URL.'get_vod_info&vod_id='.$id);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $body = curl_exec($ch);
    curl_close($ch);
    if($body === false) {
        return array("status" => "error");
    }
    $result = json_decode($body);
    if($result === null){
        return array("status" => "error");
    }
    return array("status" => "ok", "data" => $result);
}    

function stream_get_serie($id){
    // Uma so requisicao e com timeout (antes eram duas e sem timeout).
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SERVIDOR_IPTV_URL.'get_series_info&series_id='.$id);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $body = curl_exec($ch);
    curl_close($ch);
    if($body === false) {
        return array("status" => "error", "data" => array());
    }
    $result = json_decode($body);
    if($result === null){
        return array("status" => "error", "data" => array());
    }
    return array("status" => "ok", "data" => $result);
}

function stream_get_serie_episodios($id){
    $series = stream_get_serie($id)['data'];
    $episodes = isset($series->episodes) ? (array) $series->episodes : array();


    $episodios = array();
    for($i= 1; $i <= count($episodes); $i++){
        foreach($episodes[$i] as $item){
            $episodios[] = $item;    
        }
    }
    return array("episodios" => $episodios, "temporadas" => count($episodes));


    
}

function stream_get_serie_episodio($id, $episodio){
    $episodios = stream_get_serie_episodios($id)['episodios'];
  
    if(array_key_exists($episodio, $episodios)){
        return $episodios[$episodio];
        return $episodios[0];
    }
    return array();
}

function stream_count_total($stream_type){
    global $stream_cache_get;
    return count((array)$stream_cache_get->$stream_type);
}

stream_cache_atualizar();