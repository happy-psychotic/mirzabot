<?php
require_once 'config.php';
require_once 'x-ui_single.php';
require_once 'request.php';
ini_set('error_log', 'error_log');

function findAlirezaClientMatches($payload, $username, &$matches = [])
{
    if (!is_array($payload)) {
        return $matches;
    }

    $email = $payload['email'] ?? null;
    $remark = $payload['remark'] ?? null;
    if ($email === $username || $remark === $username) {
        $matches[] = $payload;
    }

    foreach ($payload as $value) {
        if (is_array($value)) {
            findAlirezaClientMatches($value, $username, $matches);
            continue;
        }

        if (!is_string($value)) {
            continue;
        }

        $trimmedValue = trim($value);
        if ($trimmedValue === '' || ($trimmedValue[0] !== '{' && $trimmedValue[0] !== '[')) {
            continue;
        }

        $decodedValue = json_decode($trimmedValue, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedValue)) {
            findAlirezaClientMatches($decodedValue, $username, $matches);
        }
    }

    return $matches;
}

function normalizeAlirezaClientMatches(array $matches)
{
    if (empty($matches)) {
        return [];
    }

    $config = $matches[0];
    $stats = $matches[0];
    foreach ($matches as $match) {
        if (isset($match['subId']) || isset($match['enable'])) {
            $config = $match;
        }
        if (isset($match['up']) || isset($match['down']) || isset($match['total'])) {
            $stats = $match;
        }
    }

    return [$config, $stats];
}

function get_clinetsalireza($username,$namepanel){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $cookieFile = getPanelCookieFile($marzban_list_get['code_panel']);
    $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => rtrim($marzban_list_get['url_panel'], '/').'/xui/API/inbounds/',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_SSL_VERIFYHOST =>  false,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_TIMEOUT_MS => 4000,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json'
  ),
  CURLOPT_COOKIEFILE => $cookieFile,
));
$output = [];
$rawResponse = curl_exec($curl);
$decoded = json_decode($rawResponse, true);
$response = is_array($decoded) && isset($decoded['obj']) && is_array($decoded['obj']) ? $decoded['obj'] : [];
if(empty($response)){
    curl_close($curl);
    if (is_file($cookieFile)) {
        @unlink($cookieFile);
    }
    return get_clinetsalireza_fallback($username, $namepanel);
}
foreach ($response as $client){
    $clientdata= json_decode($client['settings'],true)['clients'];
    foreach($clientdata as $clinets){
    if($clinets['email'] == $username){
        $output[] = $clinets;
        break;
    }
    }
    $clientStats= $client['clientStats'];
    foreach($clientStats as $clinetsup){
    if($clinetsup['email'] == $username){
        $output[] = $clinetsup;
        break;
    }
    }
    
}
curl_close($curl);
@unlink($cookieFile);
if (empty($output)) {
    $recursiveMatches = findAlirezaClientMatches($response, $username);
    if (!empty($recursiveMatches)) {
        return normalizeAlirezaClientMatches($recursiveMatches);
    }
    return get_clinetsalireza_fallback($username, $namepanel);
}
return $output;
}
function get_clinetsalireza_fallback($username, $namepanel){
    $fallback = get_clinets($username, $namepanel);
    if (!is_array($fallback) || !empty($fallback['error']) || !isset($fallback['body'])) {
        return [];
    }

    $decoded = json_decode($fallback['body'], true);
    if (!is_array($decoded) || empty($decoded['obj']) || !is_array($decoded['obj'])) {
        return [];
    }

    $client = $decoded['obj'];
    $config = [
        'id' => $client['id'] ?? null,
        'email' => $client['email'] ?? $username,
        'enable' => $client['enable'] ?? true,
        'subId' => $client['subId'] ?? '',
        'expiryTime' => $client['expiryTime'] ?? 0,
        'totalGB' => $client['total'] ?? 0,
    ];
    $stats = [
        'id' => $client['id'] ?? null,
        'email' => $client['email'] ?? $username,
        'enable' => $client['enable'] ?? true,
        'subId' => $client['subId'] ?? '',
        'expiryTime' => $client['expiryTime'] ?? 0,
        'total' => $client['total'] ?? 0,
        'up' => $client['up'] ?? 0,
        'down' => $client['down'] ?? 0,
    ];

    return [$config, $stats];
}
function addClientalireza_singel($namepanel, $usernameac, $Expire,$Total, $Uuid,$Flow,$subid,$inboundid){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $cookieFile = getPanelCookieFile($marzban_list_get['code_panel']);
    $config = array(
        "id" => intval($inboundid),
        'settings' => json_encode(array(
            'clients' => array(
                array(
                "id" => $Uuid,
                "flow" => $Flow,
                "email" => $usernameac,
                "totalGB" => $Total,
                "expiryTime" => $Expire,
                "enable" => true,
                "tgId" => "",
                "subId" => $subid,
                "reset" => 0
            )),
             'decryption' => 'none',
            'fallbacks' => array(),
        ))
        );

    $configpanel = json_encode($config,true);
    $url = $marzban_list_get['url_panel'].'/xui/API/inbounds/addClient';
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie($cookieFile);
    $response = $req->post($configpanel);
    @unlink($cookieFile);
    return $response;
}
function updateClientalireza($namepanel, $username,array $config){
    $UsernameData = get_clinetsalireza($username,$namepanel)[0];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $cookieFile = getPanelCookieFile($marzban_list_get['code_panel']);
    $configpanel = json_encode($config,true);
    $url = $marzban_list_get['url_panel'].'/xui/API/inbounds/updateClient/'.$UsernameData['id'];
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie($cookieFile);
    $response = $req->post($configpanel);
    @unlink($cookieFile);
    return $response;
}
function ResetUserDataUsagealirezasin($usernamepanel, $namepanel){
    $data_user = get_clinetsalireza($usernamepanel,$namepanel)[0];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $cookieFile = getPanelCookieFile($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel']."/xui/API/inbounds/{$marzban_list_get['inboundid']}/resetClientTraffic/".$data_user['email'];
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie($cookieFile);
    $response = $req->post($configpanel);
    @unlink($cookieFile);
    return $response;
}
function removeClientalireza_single($location,$username){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $data_user = get_clinetsalireza($username,$location)[0];
    login($marzban_list_get['code_panel']);
    $cookieFile = getPanelCookieFile($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel']."/xui/API/inbounds/{$marzban_list_get['inboundid']}/delClient/".$data_user['id'];
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie($cookieFile);
    $response = $req->post(array());
    @unlink($cookieFile);
    return $response;
    
}
function get_onlineclialireza($name_panel,$username){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $name_panel,"select");
    login($marzban_list_get['code_panel']);
    $cookieFile = getPanelCookieFile($marzban_list_get['code_panel']);
    $curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds/onlines',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_SSL_VERIFYHOST =>  false,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json'
  ),
  CURLOPT_COOKIEFILE => $cookieFile,
));
$response = json_decode(curl_exec($curl),true)['obj'];
if($response == null)return "offline";
if(in_array($username,$response))return "online";
return "offline";
curl_close($curl);
@unlink($cookieFile);

}
