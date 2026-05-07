<?php

require_once __DIR__ . '/Assert.php';

$root = dirname(__DIR__, 2);
$files = [
    $root . '/vpnbot/Default/index.php',
    $root . '/vpnbot/update/index.php',
];

foreach ($files as $file) {
    $source = file_get_contents($file);
    assertTrueValue(
        strpos($source, '$isCustomVolumeAutoUsername = $user[\'step\'] == "getvolumecustomuser";') !== false,
        basename(dirname($file)) . ' should detect custom-volume auto-username purchases'
    );
    assertTrueValue(
        strpos($source, 'if (!$isCustomVolumeAutoUsername) {' . "\n" . '            $code_product = $dataget[1] ?? ($userdate[\'code_product\'] ?? null);') !== false,
        basename(dirname($file)) . ' should not require code_product for custom-volume auto-username purchases'
    );
}

passTest('ResellerCustomVolumeFlowTest');

