<?php

putenv('MIRZABOT_TESTING=1');
putenv('MIRZABOT_TEST_CONFIG_HOST_REWRITE_STATUS=on');
putenv('MIRZABOT_TEST_CONFIG_HOST_OVERRIDE=203.0.113.10');
require_once __DIR__ . '/Assert.php';
require_once dirname(__DIR__, 2) . '/function.php';

$vless = 'vless://uuid@panel.example.com:443?security=tls#client';
assertSameValue(
    'vless://uuid@203.0.113.10:443?security=tls#client',
    rewriteProxyConfigHost($vless),
    'vless host with port should be rewritten when enabled'
);

$trojan = 'trojan://password@panel.example.com:8443?security=tls#client';
assertSameValue(
    'trojan://password@203.0.113.10:8443?security=tls#client',
    rewriteProxyConfigHost($trojan),
    'trojan host with port should be rewritten when enabled'
);

$vmessConfig = [
    'v' => '2',
    'ps' => 'client',
    'add' => 'panel.example.com',
    'port' => '443',
    'id' => 'uuid',
];
$vmess = 'vmess://' . base64_encode(json_encode($vmessConfig, JSON_UNESCAPED_SLASHES));
$rewrittenVmess = rewriteProxyConfigHost($vmess);
$decodedVmess = json_decode(base64_decode(substr($rewrittenVmess, 8)), true);
assertSameValue('203.0.113.10', $decodedVmess['add'], 'vmess add field should be rewritten');

$httpUrl = 'https://panel.example.com/sub/token';
assertSameValue($httpUrl, rewriteProxyConfigHost($httpUrl), 'http subscription URLs should not be rewritten as proxy configs');

$bundle = implode("\n", [$vless, $trojan]);
$rewrittenBundle = rewriteSubscriptionPayloadHost(base64_encode($bundle));
$decodedBundle = base64_decode($rewrittenBundle);
assertTrueValue(strpos($decodedBundle, '203.0.113.10:443') !== false, 'base64 subscription bundle should be rewritten when enabled');
assertTrueValue(strpos($decodedBundle, '203.0.113.10:8443') !== false, 'all config lines in bundle should be rewritten');

passTest('ConfigHostRewriteEnabledTest');
