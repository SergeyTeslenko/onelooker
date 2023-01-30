<?php
require_once(__DIR__.'/../vendor/autoload.php');

use GuzzleHttp\Client;

$type = $_GET['type'];
$on = isset($_GET['on']) ? $_GET['on'] : (isset($_GET['order']) ? $_GET['order'] : 0);
$stat = $_GET['stat'];

if (!$on) {
    die();
}

if (in_array($stat, array(1, 0))) {
    header('Location: https://on-looker.ru/test-vidzheta?order='.$on.'&type='.$type.'&stat='.$stat);
}

if ($stat == 2 || $stat == 3 || $stat == 4 || $stat == 5) {
    $config = json_decode(file_get_contents(__DIR__.'/config.json'), true);

    $apiKey = $config['retailcrm']['token'];
    $apiPath = $config['retailcrm']['apiPath'];

    $client = new GuzzleHttp\Client(['base_uri' => $apiPath, 'http_errors' => false]);

    $response = $client->request('GET', 'orders', [
        'headers' => [
            'X-API-KEY' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'query' => [
            'filter' => [
                'numbers' => [$on]
            ]
        ]
    ]);

    $result = json_decode($response->getBody(), true);
    $order = $result['orders'][0];

    $payment_id = array_key_first($order['payments']);

    if ($stat == 5) {
        $client = new GuzzleHttp\Client(['base_uri' => 'https://online.atol.ru/possystem/v4/', 'http_errors' => false]);

        $response = $client->request('POST', 'getToken', [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'json' => [
                'login' => $config['atol']['login'],
                'pass' => $config['atol']['pass']
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        if (isset($result['token'])) {
            $token = $result['token'];
            $items = [];
            $total = bcdiv($order['totalSumm'], 1, 2);
            $payment_object = $config['tinkoff']['payment_object'];
            $payment_method = 'full_payment';

            $max_price = 0;
            $max_id = 0;
            foreach ($order['items'] as $item) {
                if ($max_price < $item['initialPrice']) {
                    $max_price = $item['initialPrice'];
                    $max_id = $item['id'];
                }
            }

            $delivery_cost = $order['delivery']['cost'] - (float)$order['managerComment'];
            foreach ($order['items'] as $item) {
                $price = bcdiv($item['initialPrice'] - $item['discountTotal'], 1, 2);

                $name = mb_substr($item['offer']['displayName'], 0, 128);
                if ($delivery_cost > 0 && $max_id == $item['id']) {
                    $price += bcdiv(($delivery_cost / $item['quantity']), 1, 2);
                    $name .= ' (с доставкой заказа ' . $on . ')';
                }

                $sum = $price * $item['quantity'];
                $items[] = '{"name":"' . $name . '","price":' . $price . ',"quantity":' . $item['quantity'] . ',"sum":' . $sum . ',"payment_method":"' . $payment_method . '","payment_object":"' . $payment_object . '","vat":{"type":"none"}}';
            }

            $email = '';
            if ($order['email']) {
                $email = ',"email":"' . $order['email'] . '"';
            }

            $json = '{"external_id":"' . $on . '","receipt":{"client":{"phone":"' . $order['phone'] . '","name":"' . $order['firstName'] . ' ' . $order['lastName'] . '"' . $email . '},"company":{"email":"' . $config['tinkoff']['email_company'] . '","sno":"' . $config['tinkoff']['taxation'] . '","inn":"' . $config['atol']['inn'] . '","payment_address":"https:\/\/' . $_SERVER['HTTP_HOST'] . '\/"},"items":[' . implode(',', $items) . '],"payments":[{"type":2,"sum":' . $total . '}],"total":' . $total . '},"timestamp":"' . date('d.m.Y H:i:s') . '"}';

            $response = $client->request('POST', $config['atol']['group'] . '/sell', [
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Token' => $token
                ],
                'body' => $json
            ]);

            $result = json_decode($response->getBody(), true);

            die();
        }
    }

    if ($stat == 3) {
        require_once(__DIR__ . '/payments/tinkoff/tinkoffapibasic.php');
        require_once(__DIR__ . '/payments/tinkoff/tinkoffmerchantapi.php');

        $Tinkoff = new TinkoffMerchantAPI($config['tinkoff']['terminal_id'], $config['tinkoff']['password']);
        $request = $Tinkoff->buildQuery('CheckOrder', array('OrderId' => $on));
        $request = json_decode($request);

        if (!isset($request->Payments[0]->PaymentId)) {
            die();
        }

        $total = number_format($order['totalSumm'], 2, '.', '') * 100;

        $requestData = array(
            'PaymentId' => $request->Payments[0]->PaymentId
        );

        $requestData['Receipt'] = array(
            'EmailCompany' => mb_substr($config['tinkoff']['email_company'], 0, 64),
            'Phone' => $order['phone'],
            'Taxation' => $config['tinkoff']['taxation'],
            'Payments' => array(
                'Electronic' => 0,
                'AdvancePayment' => $total
            ),
            'Items' => array()
        );

        if ($order['email']) {
            $requestData['Receipt']['Email'] = $order['email'];
        }

        $payment_object = $config['tinkoff']['payment_object'];

        $max_price = 0;
        $max_id = 0;
        foreach ($order['items'] as $item) {
            if ($max_price < $item['initialPrice']) {
                $max_price = $item['initialPrice'];
                $max_id = $item['id'];
            }
        }

        $delivery_cost = $order['delivery']['cost'] - (float)$order['managerComment'];
        foreach ($order['items'] as $item) {
            $price = number_format($item['initialPrice'] - $item['discountTotal'], 2, '.', '') * 100;

            $name = mb_substr($item['offer']['displayName'], 0, 64);
            if ($delivery_cost > 0 && $max_id == $item['id']) {
                $price += number_format(($delivery_cost / $item['quantity']), 2, '.', '') * 100;
                $name .= ' (с доставкой заказа '.$on.')';
            }

            $requestData['Receipt']['Items'][] = array(
                'Name' => $name,
                'Price' => $price,
                'Quantity' => $item['quantity'],
                'Amount' => $price * $item['quantity'],
                'PaymentMethod' => 'full_payment',
                'PaymentObject' => $payment_object,
                'Tax' => 'none'
            );
        }

        $request = $Tinkoff->buildQuery('SendClosingReceipt', $requestData);

        $request = json_decode($request);

        die();
    }

    if ($type == 1) {
        $request = json_decode(file_get_contents('php://input'));

        if (!empty($request)) {
            $request->Success = $request->Success ? 'true' : 'false';
            $data = array();

            foreach ($request as $key => $item) {
                $data[$key] = $item;
            }

            $data['Password'] = $config['tinkoff']['password'];
            $token = $data['Token'];

            ksort($data);
            unset($data['Token']);
            $values = implode('', array_values($data));
            $new_token = hash('sha256', $values);

            if ($token == $new_token && in_array($data['Status'], array('CONFIRMED', 'REFUNDED', 'REJECTED'))) {
                $status_order = 'send-to-assembling';

                if ($data['Status'] == 'CONFIRMED') {
                    $status = 'paid';
                }

                if ($data['Status'] == 'REFUNDED') {
                    $status = 'returned';
                }

                if ($data['Status'] == 'REJECTED') {
                    $status = 'fail';
                }

                $order_body = [
                    'status' => $status_order
                ];

                $response = $client->request('POST', 'orders/' . $on . '/edit', [
                    'headers' => [
                        'X-API-KEY' => $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'by' => 'id',
                        'site' => $config['retailcrm']['shop'],
                        'order' => json_encode($order_body)
                    ])
                ]);

                $payments = [
                    'status' => $status
                ];

                $response = $client->request('POST', 'orders/payments/' . $payment_id . '/edit', [
                    'headers' => [
                        'X-API-KEY' => $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'by' => 'id',
                        'site' => $config['retailcrm']['shop'],
                        'payment' => json_encode($payments)
                    ])
                ]);

                exit('OK');
            }

            exit('NOTOK');
        }
    }

    if ($type == 2 || $type == 3) {
        if ($stat == 4) {
            $payments = [
                'status' => 'canceled'
            ];

            $response = $client->request('POST', 'orders/payments/'.$payment_id.'/edit', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'by' => 'id',
                    'site' => $config['retailcrm']['shop'],
                    'payment' => json_encode($payments)
                ])
            ]);

            header('Location: https://on-looker.ru/test-vidzheta?order='.$on.'&type='.$type.'&stat='.$stat);
            die();
        }

        $request = json_decode(file_get_contents('php://input'));

        if (!empty($request)) {
            if ($request->status == 'approved') {
                $status = 'wait-approved';
                $payment_method = 'full_prepayment';
            }

            if ($request->status == 'signed') {
                $payment_method = 'full_payment';
                $status_order = 'send-to-assembling';
                $status = 'credit-approved';

                $order_body = [
                    'status' => $status_order
                ];

                $response = $client->request('POST', 'orders/'.$on.'/edit', [
                    'headers' => [
                        'X-API-KEY' => $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'by' => 'id',
                        'site' => $config['retailcrm']['shop'],
                        'order' => json_encode($order_body)
                    ])
                ]);
            }

            if ($request->status == 'rejected') {
                $status = 'rejected-credit';
            }

            if ($request->status == 'canceled') {
                $status = 'canceled';
            }

            $payments = [
                'status' => $status
            ];

            $response = $client->request('POST', 'orders/payments/'.$payment_id.'/edit', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'by' => 'id',
                    'site' => $config['retailcrm']['shop'],
                    'payment' => json_encode($payments)
                ])
            ]);

            if ($request->status == 'approved' || $request->status == 'signed') {
                $client = new GuzzleHttp\Client(['base_uri' => 'https://online.atol.ru/possystem/v4/', 'http_errors' => false]);

                $response = $client->request('POST', 'getToken', [
                    'headers' => [
                        'Content-Type' => 'application/json; charset=utf-8'
                    ],
                    'json' => [
                        'login' => $config['atol']['login'],
                        'pass' => $config['atol']['pass']
                    ]
                ]);

                $result = json_decode($response->getBody(), true);

                if (isset($result['token'])) {
                    $token = $result['token'];
                    $items = [];
                    $total = bcdiv($order['totalSumm'], 1,2);
                    $payment_object = $config['tinkoff']['payment_object'];

                    $max_price = 0;
                    $max_id = 0;
                    foreach ($order['items'] as $item) {
                        if ($max_price < $item['initialPrice']) {
                            $max_price = $item['initialPrice'];
                            $max_id = $item['id'];
                        }
                    }

                    $delivery_cost = $order['delivery']['cost'] - (float)$order['managerComment'];
                    foreach ($order['items'] as $item) {
                        $price = bcdiv($item['initialPrice'] - $item['discountTotal'], 1,2);

                        $name = mb_substr($item['offer']['displayName'], 0, 128);
                        if ($delivery_cost > 0 && $max_id == $item['id']) {
                            $price += bcdiv(($delivery_cost / $item['quantity']), 1,2);
                            $name .= ' (с доставкой заказа ' . $on . ')';
                        }

                        /*$items[] = [
                            'name' => $name,
                            'price' => $price,
                            'quantity' => $item['quantity'],
                            'sum' => $price * $item['quantity'],
                            'payment_method' => $payment_method,
                            'payment_object' => $payment_object,
                            'vat' => [
                                'type' => 'none'
                            ]
                        ];*/

                        $sum = $price * $item['quantity'];
                        $items[] = '{"name":"'.$name.'","price":'.$price.',"quantity":'.$item['quantity'].',"sum":'.$sum.',"payment_method":"'.$payment_method.'","payment_object":"'.$payment_object.'","vat":{"type":"none"}}';
                    }

                    /*$json = [
                        'external_id' => $on,
                        'receipt' => [
                            'client' => [
                                'phone' => $order['phone'],
                                'name' => $order['firstName'].' '.$order['lastName']
                            ],
                            'company' => [
                                'email' => $config['tinkoff']['email_company'],
                                'sno' => $config['tinkoff']['taxation'],
                                'inn' => $config['atol']['inn'],
                                'payment_address' => 'https://'.$_SERVER['HTTP_HOST'].'/'
                            ],
                            'items' => $items,
                            'payments' => [
                                [
                                    'type' => 2,
                                    'sum' => $total
                                ]
                            ],
                            'total' => $total
                        ],
                        'timestamp' => date('d.m.Y H:i:s')
                    ];*/

                   /* if ($order['email']) {
                        $json['receipt']['client']['email'] = $order['email'];
                    }*/

                    $email = '';
                    if ($order['email']) {
                        $email = ',"email":"'.$order['email'].'"';
                    }

                    $json = '{"external_id":"'.$on.'","receipt":{"client":{"phone":"'.$order['phone'].'","name":"'.$order['firstName'].' '.$order['lastName'].'"'.$email.'},"company":{"email":"'.$config['tinkoff']['email_company'].'","sno":"'.$config['tinkoff']['taxation'].'","inn":"'.$config['atol']['inn'].'","payment_address":"https:\/\/'.$_SERVER['HTTP_HOST'].'\/"},"items":['.implode(',', $items).'],"payments":[{"type":2,"sum":'.$total.'}],"total":'.$total.'},"timestamp":"'.date('d.m.Y H:i:s').'"}';

                    $response = $client->request('POST', $config['atol']['group'].'/sell', [
                        'headers' => [
                            'Content-Type' => 'application/json; charset=utf-8',
                            'Token' => $token
                        ],
                        'body' => $json
                    ]);

                    $result = json_decode($response->getBody(), true);
                }
            }
        }
    }
}