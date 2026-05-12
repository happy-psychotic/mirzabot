<?php

require_once __DIR__ . '/Assert.php';

$root = dirname(__DIR__, 2);
$files = [
    $root . '/vpnbot/Default/admin.php',
    $root . '/vpnbot/update/admin.php',
];

foreach ($files as $file) {
    $source = file_get_contents($file);
    $label = basename(dirname($file));

    assertTrueValue(
        strpos($source, "'request_user' => ['request_id' => 1, 'user_is_bot' => false]") !== false,
        $label . ' reseller admin should offer Telegram contact/user picker for search'
    );
    assertTrueValue(
        strpos($source, "strpos(\$text, \"/id \") !== false") !== false,
        $label . ' reseller admin should support /id search commands'
    );
    assertTrueValue(
        strpos($source, "isset(\$update['message']['user_shared']['user_id'])") !== false,
        $label . ' reseller admin should accept Telegram user_shared payloads'
    );
    assertTrueValue(
        strpos($source, "strpos(\$text, '@') === 0") !== false,
        $label . ' reseller admin should support username-based search'
    );
}

passTest('ResellerUserSearchTest');
