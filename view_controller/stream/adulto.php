<?php 
require_once $_SERVER['DOCUMENT_ROOT'].'/controller/cliente/cliente_controller.php';   
// Conteudo adulto: categorias do painel cujo nome contenha "adult" (ex.:
// "Adultos", "ADULT") ou "xxx". A pagina so lista as categorias; os itens
// abrem nas paginas de categoria normais do site.
$adulto_categorias = array();
$adulto_tipos = array(
    'vod_categories' => 'movie',
    'series_categories' => 'series',
    'live_categories' => 'live',
);
foreach ($adulto_tipos as $cat_key => $tipo) {
    foreach (get_stream_category($cat_key) as $cat) {
        if (isset($cat->category_name) && (stripos($cat->category_name, 'adult') !== false || stripos($cat->category_name, 'xxx') !== false)) {
            $adulto_categorias[] = array('tipo' => $tipo, 'categoria' => $cat);
        }
    }
}
$page_title = 'Conteúdo Adulto';
