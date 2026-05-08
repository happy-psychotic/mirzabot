<?php

require_once __DIR__ . '/Assert.php';

$script = file_get_contents(dirname(__DIR__, 2) . '/scripts/sync_reseller_templates.sh');

// Must skip bots with no config.php — prevents touching orphaned/incomplete installs
assertTrueValue(strpos($script, 'config.php') !== false, 'sync script should skip bots with no config.php');

// Must skip bots that have opted out of auto-sync
assertTrueValue(strpos($script, '.no-sync') !== false, 'sync script should respect .no-sync marker');

// Must never overwrite per-bot unique files
assertTrueValue(strpos($script, 'config.php') === false || strpos($script, 'cp -f') !== false, 'sync script should not copy config.php');
$syncedFiles = [];
preg_match_all('/"([^"]+\.php)"/', $script, $m);
foreach ($m[1] as $f) {
    $syncedFiles[] = $f;
}
assertTrueValue(!in_array('config.php', $syncedFiles), 'sync script FILES list must not include config.php');
assertTrueValue(!in_array('text.json', $syncedFiles), 'sync script FILES list must not include text.json');

// Must print a warning when overwriting a file that differs
assertTrueValue(strpos($script, 'WARNING') !== false, 'sync script should warn when overwriting a differing file');

passTest('ResellerSyncScriptTest');
