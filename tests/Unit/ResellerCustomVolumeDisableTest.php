<?php

require_once __DIR__ . '/Assert.php';

$root = dirname(__DIR__, 2);
$files = [
    $root . '/vpnbot/Default/index.php',
    $root . '/vpnbot/update/index.php',
];

foreach ($files as $file) {
    $source = file_get_contents($file);
    $label = basename(dirname($file));

    assertTrueValue(
        strpos($source, "\$customVolumeDisabled = !empty(\$setting['disable_custom_volume']);") !== false,
        $label . ' reseller bot should expose a per-bot custom-volume disable flag'
    );
    assertTrueValue(
        strpos($source, "if (\$customVolumeDisabled) {\n        sendmessage(\$from_id, \$textbotlang['Admin']['Product']['nullpProduct'], \$keyboard, 'HTML');\n        step('home', \$from_id);\n        return;\n    }\n    \$userdate = json_decode(\$user['Processing_value'], true);") !== false,
        $label . ' reseller bot should block direct custom-volume callbacks when custom volume is disabled'
    );
    assertTrueValue(
        strpos($source, "if (!\$customVolumeDisabled && \$statuscustomvolume == \"1\" && \$locationproduct['type'] != \"Manualsale\")") !== false,
        $label . ' reseller product keyboard should hide custom-volume entry when disabled'
    );
}

passTest('ResellerCustomVolumeDisableTest');
