<?php
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
$client->setDebugLevel(9);

function addzero($s) {
    if (strlen($s) < 2) {
        $s = '0' . $s;
    }

    return $s;
}


$rows = $client->call('GetCampaignsList', array());

mb_internal_encoding('utf8');

foreach ($rows as $item) {
    if ($item['StatusArchive'] == 'Yes' || $item['StatusModerate'] == 'Pending' || $item['StatusActivating'] == 'Pending') {
        continue;
    }
    $cid = $item['CampaignID'];

    $params = array(
        'CampaignIDS' => array($cid),
        'GetPhrases' => 'WithPrices'
    );

    $attempts = 3;
    $result = null;
    while ($attempts > 0) {
        $result = $client->call('GetBanners', array('params' => $params));

        if (isset($result[0]) === false) {
            --$attempts;
        } else {
            break;
        }
    }

    if (isset($result[0]) === false) {
        echo "<!\n";
        print $client->getError() . "\n";
        echo "XML Request: " . $client->request . "\n";
        echo "XML Response: " . $client->response;
        echo("\nTime: " . date('Y-m-d H:i:s') . "(" . time() . "). Invalid campaign ID $cid.\nInput:" . print_r($params, true) . "\nOutput:\n" . print_r($result, true) . "\n");
        echo "!>\n";
        continue;
    }

    $t = localtime(time() + $days*24*60*60, true);
    $months = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
    $anymonth = implode('|', $months);

    $banners = array();

    for ($i = 0, $n = count($result); $i < $n; ++$i) {
        if ($result[$i]['StatusArchive'] == 'No') {
            $ad = $result[$i]['Text'];

            $new_ad = preg_replace('/до (\d{2})\.(\d{2})/u', 'до ' . addzero($t['tm_mday']) . ' ' . $months[$t['tm_mon']], $ad);

            if ($new_ad != $ad) {
                if (mb_strlen($new_ad) > 75) {
                    $new_ad = preg_replace('/до (\d{2})\.(\d{2})/u', 'до ' . addzero($t['tm_mday']) . '.' . addzero($t['tm_mon'] + 1), $ad);
                }
            } else {
                $new_ad = preg_replace("/до (\d{1,2}) ($anymonth)/u", 'до ' . addzero($t['tm_mday']) . ' ' . $months[$t['tm_mon']], $ad);

                if (mb_strlen($new_ad) > 75) {
                    $new_ad = preg_replace("/до (\d{2}) ($anymonth)/u", 'до ' . addzero($t['tm_mday']) . '.' . addzero($t['tm_mon'] + 1), $ad);
                }
            }

            if ($new_ad != $ad) {
                $result[$i]['Text'] = $new_ad;

                $banners[] = $result[$i];
            }
        } else {
        }
    }

    if (count($banners) > 0) {
        print_r($client->call('CreateOrUpdateBanners', array('params' => $banners)));
        print $client->getError();
    }
}
?>
