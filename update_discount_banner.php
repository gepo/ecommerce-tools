<?php
$campaings = array('1111111'); // place list of CampaingnIDS here
$days = 1; // how much days add to current

define('ROOT_DIR', './');

require_once ROOT_DIR . 'nusoap/lib/nusoap.php';

$wsdlurl = "http://soap.direct.yandex.ru/wsdl/v4/";
$client = new nusoap_client($wsdlurl, 'wsdl'); 

$client->authtype = 'certificate';
$client->decode_utf8 = 0;
$client->soap_defencoding = 'UTF-8';
$client->certRequest['sslcertfile'] = './cert.crt'; 
$client->certRequest['sslkeyfile'] = './private.key'; 
$client->certRequest['cainfofile'] = './cacert.pem';

$params = array(
    'CampaignIDS' => $campaings,
    'GetPhrases' => 'WithPrices',
);

$result = $client->call('GetBanners', array('params' => $params));

if (isset($result[0]) === false) {
    die('Invalid Campaign IDs');
}

function addzero($s) {
    if (strlen($s) < 2) {
        $s = '0' . $s;
    }

    return $s;
}

$t = localtime(time() + $days*24*60*60, true);
$months = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
$anymonth = implode('|', $months);

$banners = array();

for ($i = 0, $n = count($result); $i < $n; ++$i) {
    if ($result[$i]['IsActive'] == 'Yes') { // skip archived and moderated ads
        $ad = $result[$i]['Text'];

        $new_ad = preg_replace('/до (\d{2})\.(\d{2})/u', 'до ' . addzero($t['tm_mday']) . '.' . addzero($t['tm_mon'] + 1), $ad);

        if ($new_ad == $ad) {
            $new_ad = preg_replace("/до (\d{1,2}) ($anymonth)/u", 'до ' . addzero($t['tm_mday']) . ' ' . $months[$t['tm_mon']], $ad);
        }

        if ($new_ad != $ad) {
            $result[$i]['Text'] = $new_ad;

        //    echo "was: $ad\nnow: $new_ad\n\n";
            $banners[] = $result[$i];
        }
    }
}

print_r($client->call('CreateOrUpdateBanners', array('params' => $banners)));
print $client->getError();
?>
