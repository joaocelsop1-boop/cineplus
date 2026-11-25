<?php
function mercadopago_checkout($title, $unit_price, $quantity, $external_reference, $email, $name, $token, $notification_url){
    // CORRIGIDO 2026-11-25: o corpo era montado colando texto - nome/e-mail iam sem
    // aspas e havia uma virgula sobrando depois de "email", entao o JSON saia
    // invalido e o Mercado Pago recusava a preferencia. Agora o payload e um
    // array PHP serializado com json_encode(), que escapa tudo corretamente.
    // Assinatura mantida: o chamador (controller/mercadopago/stream_premium_checkout.php) nao muda.
    $name = trim((string)$name);
    $parts = preg_split('/\s+/', $name, 2);
    $first_name = $parts[0] !== '' ? $parts[0] : 'Cliente';
    $last_name = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : $first_name;

    $payload = array(
        'back_urls' => array(
            'success' => BASE_CLIENTE . 'pagamentos',
            'pending' => BASE_CLIENTE . 'pagamentos',
            'failure' => BASE_CLIENTE . 'pagamentos',
        ),
        'external_reference' => (string)$external_reference,
        'notification_url' => $notification_url,
        'auto_return' => 'approved',
        'payer' => array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
        ),
        'items' => array(
            array(
                'title' => $title,
                'description' => $title,
                'picture_url' => BASE_IMAGES_URL . 'system/' . SITE_LOGO,
                'quantity' => (int)$quantity,
                'currency_id' => 'BRL',
                'unit_price' => (float)$unit_price,
            ),
        ),
    );

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log('mercadopago_checkout: json_encode falhou: ' . json_last_error_msg());
        return null;
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ),
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);

    curl_close($curl);

    if ($response === false || $curl_error !== '') {
        error_log('mercadopago_checkout: erro cURL: ' . $curl_error);
        return null;
    }

    $obj = json_decode($response);

    if (isset($obj->id) && $obj->id != NULL && !empty($obj->init_point)) {
        return $obj->init_point;
    }

    error_log('mercadopago_checkout: HTTP ' . $http_code . ' resposta: ' . $response);
    return null;
}


function mercadopago_buscar_pagamento($id){

    $curl = curl_init();
        
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/'.$id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array( 
        'Content-Type: application/json',
        'Authorization: Bearer '. SITE_TOKEN_MP
        ),
    )); 

    $response = curl_exec($curl);
    curl_close($curl);

    $payment = json_decode($response);

    if(isset($payment->id)){
        return $payment;
    }
    return null;
}

function mercadopago_tradutor($status){
    $res = array(
        "status" => array(
            "approved" => "<span class='text-green'>Aprovado</span>", 
            "in_process" => "<span class='text-warning'>Pendente</span>", 
            "pending" => "<span class='text-warning'>Pendente</span>", 
            "rejected" => "<span class='text-danger'>Rejeitado</span>", 
            "refunded" => "<span class='text-primary'>Devolvido</span>",
            "cancelled" => "<span class='text-primary'>Cancelado</span>"
        ),
    );
    if(!empty($status)){
        return $res["status"][$status];
    }
}