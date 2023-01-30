<?php
require $_SERVER['DOCUMENT_ROOT'] . '/widget/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
use GuzzleHttp\Client;

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$localDataProducts = json_decode(file_get_contents('products.json'), true);
$localDataSales = json_decode(file_get_contents('sales.json'), true);

$apiKey = $config['retailcrm']['token'];
$apiPath = $config['retailcrm']['apiPath'];

$client = new GuzzleHttp\Client(['base_uri' => $apiPath, 'http_errors' => false]);

$data = $_POST;

/*$response = $client->request('GET', 'reference/delivery-types', [
    'headers' => [
        'X-API-KEY' => $apiKey,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ]
]);

die( print_r(json_decode($response->getBody(), true)));*/


if ($data['type'] == 'create') {
    $products = [];
    $address = '';
    $type = $data['order']['delivery']['service'] == 'toPoint' ? 'to-point' : 'to-door';

    if ($data['order']['delivery']['service'] == 'toPoint') {
        $address = $data['order']['delivery']['address']['address'];
    } else if ($data['order']['delivery']['service'] == 'toDoor') {
        $address = $data['order']['delivery']['address']['value'];
    }

    foreach($data['order']['products'] as $item) {
        $products[] = [
            'quantity' => $item['count'],
            'offer' => [
                'id' => $item['offerID']
            ]
        ];
    }

    $city = $data['order']['delivery']['address']['data']['city'];

    if (!$city) {
        $city = $data['order']['delivery']['address']['city'];
    }

    if (!$city) {
        $city = $data['order']['delivery']['city']['data']['settlement'];
    }

    if (!$city) {
        $city = $data['order']['delivery']['city']['settlement'];
    }

    $status = 'new-2';

    /*if (in_array($data['payment']['code'], array('bank-online', 'bank-sbp'))) {
        $status = 'waiting-payment';
    }*/

    $response = $client->request('GET', 'orders', [
        'headers' => [
            'X-API-KEY' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);

    $id = 0;
    if ($response->getStatusCode() == 200) {
        $result = json_decode($response->getBody(), true);

        if ($result['success']) {
            $id = (int)$result['orders'][0]['id'];
        }
    }

    if (!$id) {
        $id = rand(1, 9999);
    }

    function randomString($length = 1) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    $order_number = date('dm').str_pad($id, 5, '0', STR_PAD_LEFT).'-'.randomString();

    $order = [
        'shipmentStore' => 'Onlooker',
        'privilegeType' => 'none',
        'countryIso' => $data['order']['delivery']['country']['data']['alfa2'],
        'discountManualAmount' => $data['order']['discount'],
        'lastName' => $data['contacts']['lastName'],
        'firstName' => $data['contacts']['firstName'],
        'phone' => $data['contacts']['phone'],
        'email' => $data['contacts']['email'],
        'customerComment' => $data['contacts']['comment'],
        'orderType' => 'eshop-individual',
        'orderMethod' => 'shopping-cart',
        'number' => $order_number,
        //'managerComment' => $data['order']['margin'],
        'status' => $status,
        'managerId' => 11,
        'contragent' => [
            'contragentType' => 'individual'
        ],
        'items' => $products,
        'delivery' => [
            //'cost' => $data['order']['delivery']['cost'] + $data['order']['margin'],
            'cost' => $data['order']['delivery']['cost'],
            'netCost' => $data['order']['cartCost'],
            'code' => 'apiship',
            'integrationCode' => 'apiship',
            'address' => [
                'text' => $address,
                'city' => $city
            ]
        ],
        'payments' => [
            [
                'type' => $data['payment']['code'],
                'status' => 'not-paid'
            ]
        ],
        'customFields' => [
            'custom_delivery_type' => $type
        ]
    ];

    if ($data['order']['delivery']['service'] == 'toPoint') {
        $order['delivery']['data'] = [
            'locked' => true,
            'tariff' => $data['order']['delivery']['tariffID'],
            'pickuppointId' => $data['order']['delivery']['address']['id'],
            'payerType' => 'sender'
        ];
    } else if ($data['order']['delivery']['service'] == 'toDoor') {
        $order['delivery']['data'] = [
            'locked' => true,
            'tariff' => $data['order']['delivery']['tariffID'],
            'payerType' => 'sender'
        ];
    }

    $response = $client->request('POST', 'orders/create', [
        'headers' => [
            'X-API-KEY' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'site' => $config['retailcrm']['shop'],
            'order' => json_encode($order)
        ])
    ]);

    if ($response->getStatusCode() == 201) {
        $result = json_decode($response->getBody(), true);
        
        if ($result['success']) {
            echo json_encode([
                'error' => 0,
                'data' => [
                    "id" => $result["id"],
                    "number" => $result["order"]["number"]
                ]
            ]);
        } else {
            echo json_encode([
                'error' => 1,
                'message' => 'Не удалось создать заказ'
            ]);
        }
    } else {
        echo json_encode([
            'error' => 1,
            'message' => 'Не удалось создать заказ'
        ]);
    }
} else if ($data['type'] == 'get') {
    sleep(3);
    $numberOrder = $data['number'];
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
            $payment = [];
            $productIDs = [];
            $offerIds = [];
            $discountTotal = 0;

            foreach($order['items'] as $item) {
                $products[] = [
                    'id' => $item['id'],
                    'offerID' => $item['offer']['id'],
                    'name' => $item['offer']['displayName'],
                    'price' => $item['initialPrice'],
                    'count' => $item['quantity'],
                    'image' => ''
                ];
                $offerIds[] = $item['offer']['id'];

                if ($item['discountTotal']) {
                    $discountTotal += $item['discountTotal'] * $item['quantity'];
                }
            }

            $responseProducts = $client->request('GET', 'store/products', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'query' => [
                    'filter' => [
                        'offerIds' => $offerIds
                    ]
                ]
            ]);
            $resultProducts = json_decode($responseProducts->getBody(), true);

            foreach($products as $i => $item) {
                foreach($resultProducts['products'] as $product) {
                    if ($item['offerID'] == $product['offers'][0]['id']) {
                        $products[$i]['image'] = $product['offers'][0]['images'][0];
                    }
                }
            }

            $responseStatuses = $client->request('GET', 'reference/statuses', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
            $resultStatuses = json_decode($responseStatuses->getBody(), true);

            $responsePaymentTypes = $client->request('GET', 'reference/payment-types', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
            $resultPaymentTypes = json_decode($responsePaymentTypes->getBody(), true);

            foreach($order['payments'] as $item) {
                $payment = [
                    'status' => $item['status'],
                    'type' => $item['type'],
                    'typeName' => $resultPaymentTypes['paymentTypes'][$item['type']]['name']
                ];
            }

            //$sale = count($order['items'][0]['discounts']) ? $order['items'][0]['discounts'][0]['amount'] : 0;
            $sale = $discountTotal;
            $data = [
                'id' => $order['id'],
                'products' => $products,
                'sale' => $sale,
                'summ' => $order['summ'] + $sale,
                'totalSumm' => $order['totalSumm'],
                'status' => $order['status'],
                //'margin' => $order['managerComment'],
                'statusName' => $resultStatuses['statuses'][$order['status']]['name'],
                'statusGroup' => $resultStatuses['statuses'][$order['status']]['group'],
                'statusCode' => $resultStatuses['statuses'][$order['status']]['code'],
                'contacts' => [
                    'payer' => $order['lastName'].' '.$order['firstName'],
                    'phone' => $order['phone'],
                    'email' => $order['email']
                ],
                'delivery' => [
                    'address' => $order['customFields']['custom_delivery_type'] == 'to-point' ? ($order['delivery']['data']['pickuppointAddress'] ? $order['delivery']['data']['pickuppointAddress'] : $order['delivery']['data']['shipmentpointAddress']) : $order['delivery']['address']['city'].', '.$order['delivery']['address']['text'],
                    //'cost' => ($order['delivery']['cost'] - (float)$order['managerComment']),
                    'cost' => $order['delivery']['cost'],
                    'payerType' => $order['delivery']['data']['payerType'],
                    'tariff' => $order['delivery']['data']['tariff'],
                    'type' => $order['customFields']['custom_delivery_type'],
                    'days' => $order['delivery']['data']['days'],
                    'tariffName' => $order['customFields']['custom_delivery_type'] == 'to-point' ? ($order['delivery']['data']['tariffName'] ? ' ('.$order['delivery']['data']['tariffName'].')' : '') : '',
                ],
                'payment' => $payment
            ];

            echo json_encode([
                'error' => 0,
                'data' => $data
            ]);
        } else {
            echo json_encode([
                'error' => 1,
                'message' => 'Ошибка получения заказа'
            ]);
        }
    }
} else if ($data['type'] == 'update-status') {
    $status = '';
    if ($data['status'] == 'cancel') {
        $status = 'cancel-other';
    } else {
        $status = $data['status'];
    }

    $order = [
        'status' => $status
    ];

    $response = $client->request('POST', 'orders/'.$data['id'].'/edit', [
        'headers' => [
            'X-API-KEY' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'by' => 'id',
            'site' => $config['retailcrm']['shop'],
            'order' => json_encode($order)
        ])
    ]);

    if ($response->getStatusCode() == 200) {
        echo json_encode([
            'error' => 0
        ]);
    } else {
        echo json_encode([
            'error' => 1,
            'message' => 'Не удалось обновить заказ'
        ]);
    }
}

?>