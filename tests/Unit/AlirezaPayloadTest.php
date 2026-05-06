<?php

putenv('MIRZABOT_TESTING=1');
require_once __DIR__ . '/Assert.php';
require_once dirname(__DIR__, 2) . '/function.php';
require_once dirname(__DIR__, 2) . '/alireza_single.php';

$inbound = [
    'settings' => json_encode([
        'clients' => [
            [
                'id' => 'uuid-1',
                'flow' => '',
                'email' => 'target_user',
                'totalGB' => 1073741824,
                'expiryTime' => 1893456000000,
                'enable' => true,
                'subId' => 'sub-1',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES),
    'clientStats' => [
        [
            'id' => 10,
            'email' => 'target_user',
            'total' => 1073741824,
            'up' => 100,
            'down' => 200,
            'expiryTime' => 1893456000000,
            'enable' => true,
        ],
    ],
];

$match = extractAlirezaClientFromInbound($inbound, 'target_user');
assertSameValue('uuid-1', $match[0]['id'], 'client config should be extracted from inbound settings');
assertSameValue(10, $match[1]['id'], 'client stats should be extracted from inbound stats');

$recursivePayload = [
    'obj' => [
        [
            'settings' => json_encode([
                'clients' => [
                    [
                        'id' => 'uuid-2',
                        'email' => 'nested_user',
                        'subId' => 'sub-2',
                        'enable' => true,
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES),
        ],
        [
            'clientStats' => [
                [
                    'id' => 20,
                    'email' => 'nested_user',
                    'total' => 500,
                    'up' => 50,
                    'down' => 75,
                ],
            ],
        ],
    ],
];

$recursiveMatches = findAlirezaClientMatches($recursivePayload, 'nested_user');
$normalized = normalizeAlirezaClientMatches($recursiveMatches);
assertSameValue('nested_user', $normalized[0]['email'], 'recursive config match should preserve email');
assertSameValue(20, $normalized[1]['id'], 'recursive stats match should preserve stats id');

passTest('AlirezaPayloadTest');

