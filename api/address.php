<?php
require $_SERVER['DOCUMENT_ROOT'] . '/widget/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
use Dadata\DadataClient;
use GuzzleHttp\Client;

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$token = "13f5f6eff01265c9868d04afe599df0f925ec2e4";
$secret = "bc7acf343ae35680acd816ed68930cca099993c4";
$dadata = new Dadata\DadataClient($config['dadata']['token'], $config['dadata']['secret']);

$client = new GuzzleHttp\Client();
$clientGeoplugin = new GuzzleHttp\Client(['base_uri' => 'http://www.geoplugin.net']);
$clientGeohelper = new GuzzleHttp\Client(['base_uri' => 'http://geohelper.info/api/v1/']);

if ($_GET['type'] == 'city') {
    if (isset($_GET['country'])) {
        $data = [
            'apiKey' => $config['geohelper']['token'],
            'locale[lang]' => 'ru',
            'locale[fallbackLang]' => 'en',
            'filter[countryIso]' => $_GET['country'],
            'filter[name]' => $_GET['query'],
            'pagination[limit]' => 100
        ];
        $response = json_decode(file_get_contents("http://geohelper.info/api/v1/cities?".http_build_query($data)), true);
        $response = $response['result'];
        usort($response, function($a, $b) {
            if ($b['localityType']['code'] == 'city-city') {
                return 1;
            } else {
                return -1;
            }
        });

        $result = [];

        $i = 0;
        foreach($response as $item) {
            $result[] = [
                'data' => [
                    'name' => $item['name'],
                    'localityType' => $item['localityType']['name'],
                    'localizedNamesShort' => $item['localityType']['localizedNamesShort']['ru'],
                    'country_iso_code' => $_GET['country']
                ],
                'value' => $item['localityType']['localizedNamesShort']['ru'].' '.$item['name']
            ];

            if ($i == 10) {
                break;
            }

            $i++;
        }
    } else {
        $results = $dadata->suggest("address", $_GET['query'], 25, array('from_bound' => array('value' => 'city'), 'to_bound' => array('value' => 'settlement')));

        $result = array();
        foreach ($results as $res) {
            if ($res['data']['fias_level'] !== '5' && $res['data']['fias_level'] !== '65') {
                $result[] = $res;
            }

            if (count($result) == 5) {
                break;
            }
        }
    }
} else if ($_GET['type'] == 'country') {
    $result = $dadata->suggest("country", $_GET['query']);

    foreach ($result as $i => $res) {
        if (!in_array($res['data']['alfa2'], array('BY', 'RU', 'KZ', 'AM', 'KG'))) {
            unset($result[$i]);
        }
    }
} else if ($_GET['type'] == 'country_ip') {
    $ip = $_SERVER['REMOTE_ADDR'] == '127.0.0.1' ? '91.235.185.142' : $_SERVER['REMOTE_ADDR'];

    $response = $clientGeoplugin->request('GET', 'json.gp', [
        'query' => [
            'ip' => $ip
        ],
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);

    $result = json_decode($response->getBody(), true);

    $result = $dadata->findById("country", $result['geoplugin_countryCode']);
} else if ($_GET['type'] == 'city_geo') {
    $data = [
        'city' => $_GET['city'],
        'countrycodes' => $_GET['country'],
        'limit' => 1,
        'format' => 'json'
    ];
    $referer = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'];
    $opts = [ 'http' => [ 'header' => [ "Referer: $referer\r\n" ]]];
    $context = stream_context_create($opts);
    $response = json_decode(file_get_contents("https://nominatim.openstreetmap.org/search?".http_build_query($data), false, $context), true);

    $result = $response[0];
} else {
    $country = $_GET['c'];
    $city = $_GET['b'];

    if ($country == 'Россия') {
        $locations = [
            'locations' => [
                [
                    'country' => $country,
                    'fias_id' => trim(str_replace('г ', '', $city))
                ]
            ]
        ];
    } else {
        $locations = [
            'locations' => [
                [
                    'country' => $country,
                    'city' => trim(str_replace('г ', '', $city))
                ]
            ],
            'restrict_value' => true
        ];
    }
    $result = $dadata->suggest("address", $_GET['query'], 5, $locations);
}


echo json_encode($result);
?>