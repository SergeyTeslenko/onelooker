<?php
require_once(__DIR__.'/tinkoffapibasic.php');
require_once(__DIR__.'/tinkoffmerchantapi.php');
require_once(__DIR__.'/../../../vendor/autoload.php');
header('Content-Type: application/json; charset=utf-8');

use GuzzleHttp\Client;

$config = json_decode(file_get_contents(__DIR__ . '/../../config.json'), true);

$apiKey = $config['retailcrm']['token'];
$apiPath = $config['retailcrm']['apiPath'];

$client = new GuzzleHttp\Client(['base_uri' => $apiPath, 'http_errors' => false]);

$numberOrder = $_POST['id'];
$type = $_POST['type'];

$response = $client->request('GET', 'orders', [
    'headers' => [
        'X-API-KEY' => $apiKey,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ],
    'query' => [
        'filter' => [
            'numbers' => [$numberOrder]
        ]
    ]
]);

if ($response->getStatusCode() == 200) {
    $result = json_decode($response->getBody(), true);

    if ($result['success']) {
        $order = $result['orders'][0];
        $products = [];

        foreach($order['items'] as $item) {
            $products[] = [
                'id' => $item['id'],
                'name' => $item['offer']['displayName'],
                'price' => $item['initialPrice'],
                'discount' => $item['discountTotal'],
                'count' => $item['quantity']
            ];
        }

        $responseStatuses = $client->request('GET', 'reference/statuses', [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);
        $resultStatuses = json_decode($responseStatuses->getBody(), true);

        $data = [
            'products' => $products,
            'totalSumm' => $order['totalSumm'],
            'statusName' => $resultStatuses['statuses'][$order['status']]['name'],
            'contacts' => [
                'payer' => $order['lastName'].' '.$order['firstName'],
                'phone' => $order['phone'],
                'email' => $order['email']
            ],
            'delivery' => [
                'cost' => ($order['delivery']['cost'] - (float)$order['managerComment'])
            ]
        ];
    } else {
        echo json_encode([
            'error' => 1,
            'message' => 'Ошибка получения заказа'
        ]);

        die();
    }
} else {
    echo json_encode([
        'error' => 1,
        'message' => 'Ошибка получения заказа'
    ]);

    die();
}

$phone = $data['contacts']['phone'];
$email = $data['contacts']['email'];

$total = number_format($data['totalSumm'], 2, '.', '') * 100;
$requestData = array(
    'OrderId' => $numberOrder,
    'Amount' => $total,
    'DATA' => array(
        'Phone' => $phone,
        'name' => $data['contacts']['payer']
    ),
);

if ($email) {
    $requestData['DATA']['Email'] = $email;
}

$requestData['Receipt'] = array(
    'EmailCompany' => mb_substr($config['tinkoff']['email_company'], 0, 64),
    'Phone' => $phone,
    'Taxation' => $config['tinkoff']['taxation'],
    'Payments' => array(
        'Electronic' => $total
    ),
    'Items' => array()
);

$desc = 'Оплата за товар(ы): ';
$payment_method = $config['tinkoff']['payment_method'];
$payment_object = $config['tinkoff']['payment_object'];

$max_price = 0;
$max_id = 0;
foreach ($data['products'] as $product) {
    if ($max_price < $product['price']) {
        $max_price = $product['price'];
        $max_id = $product['name'];
    }
}

$delivery_cost = $data['delivery']['cost'] * 100;
foreach ($data['products'] as $product) {
    $price = number_format($product['price'] - $product['discount'], 2, '.', '') * 100;

    $name = mb_substr($product['name'], 0, 64);
    if ($delivery_cost > 0 && $max_id == $product['name']) {
        $price += number_format(($delivery_cost / $product['count']), 2, '.', '');
        $name .= ' (с доставкой заказа '.$numberOrder.')';
    }

    $requestData['Receipt']['Items'][] = array(
        'Name' => $name,
        'Price' => $price,
        'Quantity' => $product['count'],
        'Amount' => $price * $product['count'],
        'PaymentMethod' => $payment_method,
        'PaymentObject' => $payment_object,
        'Tax' => 'none'
    );

    $desc .= $product['name'].', ';
}

$requestData['Description'] = trim($desc, ', ');
$requestData['Language'] = $config['tinkoff']['language'];
$requestData['SuccessURL'] = 'https://on-looker.ru/widget/api/callback.php?type=1&stat=1&on='.$numberOrder;
$requestData['FailURL'] = 'https://on-looker.ru/widget/api/callback.php?type=1&stat=0&on='.$numberOrder;
$requestData['NotificationURL'] = 'https://on-looker.ru/widget/api/callback.php?type=1&stat=2&on='.$numberOrder;

$Tinkoff = new TinkoffMerchantAPI($config['tinkoff']['terminal_id'], $config['tinkoff']['password']);
$request = $Tinkoff->buildQuery('Init', $requestData);

$request = json_decode($request);

if ($type == 1) {
    if (isset($request->PaymentURL)) {
        echo json_encode([
            'error' => 0,
            'url' => $request->PaymentURL
        ]);
    } else {
        if ($request->ErrorCode == 8) {
            $txt = $request->Details;
        } else {
            $txt = 'Запрос к платежному сервису был отправлен некорректно';
        }

        echo json_encode([
            'error' => 1,
            'message' => $txt
        ]);
    }
}

if ($type == 2) {
    if (isset($request->PaymentId)) {
        $requestData = array(
            'PaymentId' => $request->PaymentId
        );

        $request = $Tinkoff->buildQuery('GetQr', $requestData);

        $request = json_decode($request);

        if (isset($request->Data)) {
            echo json_encode([
                'error' => 0,
                'url' => $request->Data
            ]);
        } else {
            echo json_encode([
                'error' => 1,
                'message' => $request->Message
            ]);
        }
    } else {
        if ($request->ErrorCode == 8) {
            $txt = $request->Details;
        } else {
            $txt = 'Запрос к платежному сервису был отправлен некорректно';
        }

        echo json_encode([
            'error' => 1,
            'message' => $txt
        ]);
    }
}