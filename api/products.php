<?php
require $_SERVER['DOCUMENT_ROOT'] . '/widget/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
use GuzzleHttp\Client;

$localDataProducts = json_decode(file_get_contents('products.json'), true);
$localDataSales = json_decode(file_get_contents('sales.json'), true);

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$apiKey = $config['retailcrm']['token'];
$apiPath = $config['retailcrm']['apiPath'];

$client = new GuzzleHttp\Client(['base_uri' => $apiPath]);
$response = $client->request('GET', 'store/products', [
    'query' => [
        'limit' => '100',
        'apiKey' => $apiKey,
        'filter' => [
            'ids' => explode(',', $_GET['ids'])
        ]
    ]
]);

if ($response->getStatusCode() == 200) {
    $result = json_decode($response->getBody(), true);
    $products = [];

    foreach($result['products'] as $item) {
        $products_attr = [];
        if (isset($localDataProducts[$item['id']]) && isset($localDataProducts[$item['id']]['attr'])) {
            $attr = explode(',', $localDataProducts[$item['id']]['attr']);

            $response2 = $client->request('GET', 'store/products', [
                'query' => [
                    'limit' => '100',
                    'apiKey' => $apiKey,
                    'filter' => [
                        'ids' => $attr
                    ]
                ]
            ]);

            if ($response2->getStatusCode() == 200) {
                $result2 = json_decode($response2->getBody(), true);

                foreach($result2['products'] as $item2) {
                    $products_attr[] = [
                        'id' => $item2['id'],
                        'name' => $item2['name'],
                        'image' => $item2['imageUrl'],
                        'offerID' => $item2['offers'][0]['id'],
                        'price' => $item2['offers'][0]['price'],
                        'weight' => $item2['offers'][0]['weight'],
                        'length' => $item2['offers'][0]['length'],
                        'width' => $item2['offers'][0]['width'],
                        'height' => $item2['offers'][0]['height'],
                        'extra' => isset($localDataProducts[$item2['id']]) ? $localDataProducts[$item2['id']] : null
                    ];
                }
            }
        }

        $products[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'image' => $item['imageUrl'],
            'offerID' => $item['offers'][0]['id'],
            'price' => $item['offers'][0]['price'],
            'weight' => $item['offers'][0]['weight'],
            'length' => $item['offers'][0]['length'],
            'width' => $item['offers'][0]['width'],
            'height' => $item['offers'][0]['height'],
            'attr' => $products_attr,
            'extra' => isset($localDataProducts[$item['id']]) ? $localDataProducts[$item['id']] : null
        ];
    }

    echo json_encode([
        'error' => 0,
        'data' => [
            "products" => $products,
            "sales" => $localDataSales['sales']
        ]
    ]);
} else {
    echo json_encode([
        'error' => 1,
        'message' => 'Ошибка при получении данных'
    ]);
}

?>