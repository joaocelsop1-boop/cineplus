<?php 
require_once $_SERVER['DOCUMENT_ROOT'].'/controller/cliente/cliente_controller.php';   
$stream_assistindo_cache = stream_assistindo_cache($cliente_perfil['cliente_perfil_id'], $cliente['user_id']);
$page_title = 'Histórico';
