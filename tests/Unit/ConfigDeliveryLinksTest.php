<?php

putenv('MIRZABOT_TESTING=1');
require_once __DIR__ . '/Assert.php';
require_once dirname(__DIR__, 2) . '/function.php';

$config = 'vless://6fd00d86-d9ae-4374-b9a9-469e6e33a09e@sb.antiban.online:80?encryption=none&security=none&type=ws&host=cg.antiban.online&path=%2F#7356499248_51c9-1.00GB%F0%9F%93%8A-30D%E2%8F%B3';
$subscription = 'https://sb.antiban.online:2096/sub/437d1075dd67ec71';
$expectedEncoded = base64_encode($config . "\n");
$escapedConfig = htmlspecialchars($config, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$message = formatServiceLinksForDelivery([$config], $subscription);

assertTrueValue(strpos($message, "ساب لینک:\n<code>{$subscription}</code>") !== false, 'subscription link should be shown first');
assertTrueValue(strpos($message, "لینک رمز نگاری شده (پیشنهادی)\n<code>{$expectedEncoded}</code>") !== false, 'encoded config should be shown');
assertTrueValue(strpos($message, "اپ آیفون : streisand یا nvp") !== false, 'iPhone app hint should be shown');
assertTrueValue(strpos($message, "لینک :\n<code>{$escapedConfig}</code>") !== false, 'normal config should be shown');

$encodedSubscriptionPayload = base64_encode($config . "\n");
$normalizedLinks = normalizeConfigLinks([$encodedSubscriptionPayload]);
assertSameValue([$config], $normalizedLinks, 'base64 subscription payload should normalize to vless link');

$normalizedMessage = formatConfigLinksForDelivery($normalizedLinks);
assertTrueValue(strpos($normalizedMessage, "لینک :\n<code>{$escapedConfig}</code>") !== false, 'normalized normal config should be shown');

$directBase64Message = formatConfigLinksForDelivery([$encodedSubscriptionPayload]);
assertTrueValue(strpos($directBase64Message, "لینک :\n<code>{$escapedConfig}</code>") !== false, 'direct base64 payload should be displayed as normal config');
assertTrueValue(strpos($directBase64Message, base64_encode($encodedSubscriptionPayload . "\n")) === false, 'encoded output should not be double encoded');

passTest('ConfigDeliveryLinksTest');
