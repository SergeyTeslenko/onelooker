<?php
//  
require $_SERVER['DOCUMENT_ROOT'] . '/widget/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
use GuzzleHttp\Client;

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$apiKey = $config['retailcrm']['token'];
$apiPath = $config['retailcrm']['apiPath'];

$client = new GuzzleHttp\Client(['base_uri' => $apiPath]);

$type = $_GET['type'];

if ($type == 'get') {
    $response = $client->request('GET', 'reference/payment-types', [
        'headers' => [
            'X-API-KEY' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);

    if ($response->getStatusCode() == 200) {
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

            $total = 0;
            foreach ($prod_result['products'] as $item) {
                $cnt = $cnt_arr[$item['id']];
                $total += $item['offers'][0]['price'] * $cnt;
            }
        }

        $result = json_decode($response->getBody(), true);
        $payments = [];

        foreach($result['paymentTypes'] as $item) {
            if ($item['code'] == 'credit' || $item['code'] == 'installment') {
                if ($total < $config['tinkoff_credit']['min_price'] || $total > $config['tinkoff_credit']['max_price'] || $_GET['country'] != 'RU') {
                    continue;
                }
            }

            if ($item['code'] == 'imposed' && $_GET['country'] == 'BY') {
                continue;
            }

            if ($item['code'] == 'bank-sbp' && $_GET['country'] != 'RU') {
                continue;
            }

            if ($item['active'] == 1) {
                $payments[] = $item;
            }
        }
        
        echo json_encode([
            'error' => 0,
            'data' => $payments
        ]);
    }
}

?>