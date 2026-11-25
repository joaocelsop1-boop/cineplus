<?php 
require_once $_SERVER['DOCUMENT_ROOT'].'/view_controller/stream/historico.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/header.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/navbar.php';
?>

<div class="page-content">
    <div class="container-fluid mb-3">

        <div class="card card-page-title">
            <div class="card-body">
                <h6>Histórico</h6>
                <small><?php echo count($stream_assistindo_cache);?> títulos assistidos</small>
            </div>
        </div>

        <?php if(count($stream_assistindo_cache) > 0):?>
        <div class="stream-list"> 
            <?php foreach($stream_assistindo_cache as $item):?>
                <?php if($item->stream_type == 'movie'):?>
                    <a href="<?php echo BASE_STREAM.'movie/'.$item->stream_id;?>" class="item">
                        <img src="<?php echo $item->stream_icon;?>" class="img-fluid">  
                    </a>
                <?php elseif($item->stream_type == 'series'):?>
                    <a href="<?php echo BASE_STREAM.'series/'.$item->series_id.'/'.$item->stream_assistindo_stream;?>" class="item">
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
            <p class="mb-0">Nada por aqui ainda. O que você assistir aparece nesta página.</p>
        </div></div>
        <?php endif;?>

    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'].'/view/stream/footer.php';?>
