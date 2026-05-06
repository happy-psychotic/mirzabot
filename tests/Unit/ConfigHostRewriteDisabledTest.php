<?php

putenv('MIRZABOT_TESTING=1');
require_once __DIR__ . '/Assert.php';
require_once dirname(__DIR__, 2) . '/function.php';

$config = 'vless://uuid@panel.example.com:443?security=tls#client';

assertSameValue(
    $config,
    rewriteProxyConfigHost($config),
    'config host rewrite must be disabled by default'
);

$subscription = base64_encode($config);
assertSameValue(
    $subscription,
    rewriteSubscriptionPayloadHost($subscription),
    'base64 subscription must stay unchanged when rewrite is disabled'
);

passTest('ConfigHostRewriteDisabledTest');

