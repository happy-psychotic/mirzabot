<?php

require_once __DIR__ . '/Assert.php';

$root = dirname(__DIR__, 2);
$files = [
    $root . '/botapi.php',
    $root . '/vpnbot/Default/botapi.php',
    $root . '/vpnbot/update/botapi.php',
];

foreach ($files as $file) {
    $source = file_get_contents($file);
    $label = basename(dirname($file));
    if (basename($file) === 'botapi.php' && basename(dirname($file)) === 'AntiBan') {
        $label = 'main';
    }

    assertTrueValue(
        strpos($source, 'function formatHtmlBackticksAsCode($text, $parseMode = null)') !== false,
        $label . ' bot API should define the HTML backtick formatter'
    );
    assertTrueValue(
        strpos($source, "preg_replace_callback('/`([^`]+)`/'") !== false,
        $label . ' bot API should convert backticked text to code tags'
    );
    assertTrueValue(
        strpos($source, '$text = formatHtmlBackticksAsCode($text, $parse_mode);') !== false,
        $label . ' sendmessage/edit path should format HTML backticks before sending'
    );
}

passTest('HtmlBacktickCodeFormattingTest');
