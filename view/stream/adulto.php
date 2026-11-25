<?php 
require_once $_SERVER['DOCUMENT_ROOT'].'/view_controller/stream/adulto.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/header.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/navbar.php';
?>

<div class="page-content">
    <div class="container-fluid mb-3">

        <div class="card card-page-title">
            <div class="card-body">
                <h6>Conteúdo Adulto</h6>
                <small>Categorias adultas do catálogo.</small>
            </div>
        </div>

        <?php if(count($adulto_categorias) > 0):?>
        <div class="stream-list">
            <?php foreach($adulto_categorias as $item):?>
                <a href="<?php echo BASE_STREAM.$item['tipo'].'/categoria/'.$item['categoria']->category_id;?>/pagina/1" class="item">
                    <div class="p-3 text-center">
                        <i class="far fa-user-secret fa-2x mb-2"></i><br>
                        <span><?php echo stream_categorie_title(strtolower(str_replace('|', '', $item['categoria']->category_name)));?></span>
                    </div>
                </a>
            <?php endforeach;?>
        </div>
        <?php else:?>
        <div class="card"><div class="card-body text-center">
            <p class="mb-0">Nenhuma categoria adulta encontrada no catálogo.</p>
        </div></div>
        <?php endif;?>

    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/footer.php';?>
