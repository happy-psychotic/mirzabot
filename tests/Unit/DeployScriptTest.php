<?php

require_once __DIR__ . '/Assert.php';

$script = file_get_contents(dirname(__DIR__, 2) . '/scripts/deploy_antibot_from_local.sh');

assertTrueValue(strpos($script, 'DRY_RUN="${DRY_RUN:-0}"') !== false, 'deploy script should support DRY_RUN');
assertTrueValue(strpos($script, "--exclude 'config.php'") !== false, 'deploy script should preserve live config.php');
assertTrueValue(strpos($script, "--exclude 'vpnbot/Default/config.php'") !== false, 'deploy script should preserve vpnbot/Default/config.php');
assertTrueValue(strpos($script, "--exclude 'vpnbot/update/config.php'") !== false, 'deploy script should preserve vpnbot/update/config.php');
assertTrueValue(strpos($script, "--exclude 'storage/'") !== false, 'deploy script should preserve runtime storage');
assertTrueValue(strpos($script, "--exclude 'vpnbot/[0-9]*/'") !== false, 'deploy script should preserve generated numeric reseller bots');
assertTrueValue(strpos($script, "--exclude 'vpnbot/*_bot/'") !== false, 'deploy script should preserve generated named reseller bots');
assertTrueValue(strpos($script, 'sync_reseller_templates.sh') !== false, 'deploy script should run reseller template sync after deploy');

passTest('DeployScriptTest');
