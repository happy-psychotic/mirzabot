<?php

require_once __DIR__ . '/Assert.php';

$root = dirname(__DIR__, 2);

$functionSource = file_get_contents($root . '/function.php');
assertTrueValue(
    strpos($functionSource, 'function syncResellerBotMinPrices(string $agent_user_id, ?int $gigPrice = null, ?int $dayPrice = null): void') !== false,
    'function.php should expose a helper to sync reseller min prices into botsaz settings'
);
assertTrueValue(
    strpos($functionSource, "SELECT setting FROM botsaz WHERE id_user = :uid ORDER BY id DESC LIMIT 1") !== false,
    'agentPricePerUnit should read reseller min prices from botsaz settings first'
);
assertTrueValue(
    strpos($functionSource, "array_key_exists('minpricetime', \$botSetting)") !== false,
    'agentPricePerUnit should honor explicit zero day pricing from botsaz settings'
);

$adminSource = file_get_contents($root . '/admin.php');
assertTrueValue(
    strpos($adminSource, "syncResellerBotMinPrices((string)\$userdate['id_user'], \$minPriceVolume, null);") !== false,
    'admin min volume updates should sync into reseller bot settings'
);
assertTrueValue(
    strpos($adminSource, "syncResellerBotMinPrices((string)\$userdate['id_user'], null, \$minPriceTime);") !== false,
    'admin min time updates should sync into reseller bot settings'
);

passTest('ResellerMinPriceSyncTest');
