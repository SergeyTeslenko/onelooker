<?php
require $_SERVER['DOCUMENT_ROOT'] . '/widget/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

ini_set('display_errors', 0);

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$apiToken = $config['apiship']['token'];
$apiPath = 'https://api.apiship.ru/v1/';

$client = new GuzzleHttp\Client(['base_uri' => $apiPath]);

$type = $_GET['type'];

if ($type == 'cost') {
    $to = [];
    if ($_GET['cityType'] == 'fias') {
        $to['cityGuid'] = $_GET['city'];
    } else if ($_GET['cityType'] == 'name') {
        $to['city'] = $_GET['city'];
        $to['countryCode'] = $_GET['country'];
    }

    $ids = $_GET['ids'];
    $cnts = explode(',', $_GET['cnts']);

    $cnt_arr = array();
    foreach ($cnts as $cnt) {
        $cnt_row = explode('|', $cnt);
        $cnt_arr[$cnt_row[0]] = $cnt_row[1];
    }

    $prod_apiKey = $config['retailcrm']['token'];
    $prod_apiPath = $config['retailcrm']['apiPath'];

    $prod_client = new GuzzleHttp\Client(['base_uri' => $prod_apiPath]);
    $prod_response = $prod_client->request('GET', 'store/products', [
        'query' => [
            'limit' => '100',
            'apiKey' => $prod_apiKey,
            'filter' => [
                'ids' => explode(',', $ids)
            ]
        ]
    ]);

    if ($prod_response->getStatusCode() == 200) {
        $prod_result = json_decode($prod_response->getBody(), true);
        $products = [];

        $weight = 0;
        $height = 0;
        $length = 0;
        $width = 0;
        $total = 0;
        foreach ($prod_result['products'] as $item) {
            $cnt = $cnt_arr[$item['id']];
            $total += $item['offers'][0]['price'] * $cnt;
            $weight += $item['offers'][0]['weight'] * $cnt;
            $length += $item['offers'][0]['length'] * $cnt;
            $width += $item['offers'][0]['width'] * $cnt;
            $height += $item['offers'][0]['height'] * $cnt;
        }
    }

    $codCost = $total;
    if ($_GET['country'] == 'BY') {
        $codCost = 0;
    }

    $response = $client->request('POST', 'calculator', [
        'headers' => [
            'Authorization' => $apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'from' => [
                'cityGuid' => '023484a5-f98d-4849-82e1-b7e0444b54ef'
            ],
            'to' => $to,
            'places' => [[
                'height' => round($height / 10),
                'length' => round($length / 10),
                'width' => round($width / 10),
                'weight' => round($weight)
            ]],
            'providerKeys' => ['dpd', 'cdek', 'rupost'],
            'assessedCost' => $total,
            'codCost' => $codCost,
            'includeFees' => 'true',
            'extraParams' => [
                'dpd.providerConnectId' => '0'
            ]
        ])
    ]);

    if ($response->getStatusCode() == 200) {
        $result = json_decode($response->getBody(), true);

        $deliveries = [
            ['providerKey' => 'cdek'],
            ['providerKey' => 'dpd'],
            ['providerKey' => 'rupost']
            /*['providerKey' => 'ozon']*/
        ];

        $deliveries[0]['deliveryToDoor'] = array_values(array_filter($result['deliveryToDoor'][0]['tariffs'], function($tariff) {
            return $tariff['tariffId'] == 54;
        }))[0];

        if (!$deliveries[0]['deliveryToDoor'] && isset($result['deliveryToDoor'][0]['tariffs'][0])) {
            $deliveries[0]['deliveryToDoor'] = $result['deliveryToDoor'][0]['tariffs'][0];
        }

        $deliveries[1]['deliveryToDoor'] = array_values(array_filter($result['deliveryToDoor'][1]['tariffs'], function($tariff) {
            return $tariff['tariffId'] == 15;
        }))[0];

        if (!$deliveries[1]['deliveryToDoor'] && isset($result['deliveryToDoor'][1]['tariffs'][0])) {
            $deliveries[1]['deliveryToDoor'] = $result['deliveryToDoor'][1]['tariffs'][0];
        }

        /*$deliveries[3]['deliveryToDoor'] = array_values(array_filter($result['deliveryToDoor'][2]['tariffs'], function($tariff) {
            return $tariff['tariffId'] == 660;
        }))[0];

        if (!$deliveries[3]['deliveryToDoor'] && isset($result['deliveryToDoor'][2]['tariffs'][0])) {
            $deliveries[3]['deliveryToDoor'] = $result['deliveryToDoor'][2]['tariffs'][0];
        }*/

        $deliveries[2]['deliveryToDoor'] = array_values(array_filter($result['deliveryToDoor'][2]['tariffs'], function($tariff) {
            return $tariff['tariffId'] == 281;
        }))[0];

        if (!$deliveries[2]['deliveryToDoor'] && isset($result['deliveryToDoor'][2]['tariffs'][0])) {
            $deliveries[2]['deliveryToDoor'] = $result['deliveryToDoor'][2]['tariffs'][0];
        }
        //
        $deliveries[0]['deliveryToPoint'] = array_values(array_filter($result['deliveryToPoint'][0]['tariffs'], function($tariff) {
            return $tariff['tariffId'] == 53;
        }))[0]; 

        if (!$deliveries[0]['deliveryToPoint'] && isset($result['deliveryToPoint'][0]['tariffs'][0])) {
            $deliveries[0]['deliveryToPoint'] = $result['deliveryToPoint'][0]['tariffs'][0];
        }

        $deliveries[1]['deliveryToPoint'] = array_values(array_filter($result['deliveryToPoint'][1]['tariffs'], function($tariff) {
            return $tariff['tariffId'] == 15;
        }))[0];

        if (!$deliveries[1]['deliveryToPoint'] && isset($result['deliveryToPoint'][1]['tariffs'][0])) {
            $deliveries[1]['deliveryToPoint'] = $result['deliveryToPoint'][1]['tariffs'][0];
        }

        /*$deliveries[3]['deliveryToPoint'] = array_values(array_filter($result['deliveryToPoint'][2]['tariffs'], function($tariff) {
            return $tariff['tariffId'] == 662;
        }))[0];

        if (!$deliveries[3]['deliveryToPoint'] && isset($result['deliveryToPoint'][2]['tariffs'][0])) {
            $deliveries[3]['deliveryToPoint'] = $result['deliveryToPoint'][2]['tariffs'][0];
        }*/

        $deliveries[2]['deliveryToPoint'] = null;

        echo json_encode($deliveries);
    }

    // cdek - 56(курьер), 55(самовывоз)
    // dpd - 15
    // rupost - 281
    // ozon - 660(курьер), 662(самовывоз)

} else if($type == 'pvz') {
    $provider = $_GET['provider'];
    $filter = 'providerKey='.$provider.';id=['.$_GET['ids'].'];';

    if ($_GET['cityType'] == 'fias') {
        $filter .= 'cityGuid='.$_GET['city'];
    } else if ($_GET['cityType'] == 'name') {
        $filter .= 'city='.$_GET['city'].';'.'countryCode='.$_GET['country'];
    }

    $response = $client->request('GET', 'lists/points', [
        'query' => [
            'limit' => 100000,
            'filter' => $filter
        ],
        'headers' => [
            'Authorization' => $apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'http_errors' => true
    ]);

    $result = json_decode($response->getBody(), true);

    echo json_encode($result);
}
?>