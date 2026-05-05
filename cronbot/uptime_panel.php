<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

function isPanelUrlReachable($url)
{
    if (!is_string($url) || trim($url) === '') {
        return false;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mirzabot uptime check',
    ]);

    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    curl_close($ch);

    return $curlError === 0 && $httpCode > 0;
}

$admin_ids = select("admin", "id_admin",null,null,"FETCH_COLUMN");
$marzbanlist = select("marzban_panel", "*",null ,null ,"fetchAll");
$setting = select("setting", "*");
$status_cron = json_decode($setting['cron_status'],true);
if(!$status_cron['uptime_panel'])return;
$inbounds = [];
foreach($marzbanlist as $location){
    $parsed_url = parse_url($location['url_panel']);
    if ($parsed_url && isset($parsed_url['host'])) {
    $address = $parsed_url['host'];
    $scheme = $parsed_url['scheme'] ?? 'https';
    $defaultPort = $scheme === 'http' ? 80 : 443;
    $port = empty($parsed_url['port']) ? $defaultPort : $parsed_url['port'];
    $isReachable = checkConnection($address, $port) || isPanelUrlReachable($location['url_panel']);
    if (!$isReachable) {
       foreach ($admin_ids as $admin) {
            $textnode = "🚨 ادمین عزیز پنل با اسم <code>{$location['name_panel']}</code> متصل نیست.";
        sendmessage($admin, $textnode, null, 'html');
    }
    }
    }
}
