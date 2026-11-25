<?php 
require_once $_SERVER['DOCUMENT_ROOT'].'/view_controller/stream/minha_lista.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/header.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/navbar.php';
?>

<div class="page-content">
    <div class="container-fluid mb-3">

        <div class="card card-page-title">
            <div class="card-body">
                <h6>Minha Lista</h6>
                <small><?php echo count($stream_minha_lista);?> títulos salvos</small>
            </div>
        </div>

        <?php if(count($stream_minha_lista) > 0):?>
        <div class="stream-list"> 
            <?php foreach($stream_minha_lista as $item):?>
                <?php if($item->stream_type == 'movie'):?>
                    <a href="<?php echo BASE_STREAM.'movie/'.$item->stream_id;?>" class="item">
                        <img src="<?php echo $item->stream_icon;?>" class="img-fluid">  
                    </a>
                <?php elseif($item->stream_type == 'series'):?>
                    <a href="<?php echo BASE_STREAM.'series/'.$item->series_id;?>/0" class="item">
                        <img src="<?php echo $item->cover;?>" class="img-fluid">  
                    </a>         
                <?php elseif($item->stream_type == 'live'):?>
                    <a href="<?php echo BASE_STREAM.'live/'.$item->stream_id;?>" class="item">
                        <img src="<?php echo $item->stream_icon;?>" class="img-fluid">  
                    </a>
                <?php endif;?>
            <?php endforeach;?>
        </div>
        <?php else:?>
        <div class="card"><div class="card-body text-center">
            <p class="mb-0">Sua lista está vazia. Toque em "Minha Lista" num título para salvar aqui.</p>
        </div></div>
        <?php endif;?>

    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/footer.php';?>
