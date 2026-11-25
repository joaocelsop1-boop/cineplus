<?php 
require_once $_SERVER['DOCUMENT_ROOT'].'/controller/cliente/cliente_controller.php';   
$stream_minha_lista = stream_minha_lista_cache($cliente_perfil['cliente_perfil_id'], $cliente['user_id']);
$page_title = 'Minha Lista';
