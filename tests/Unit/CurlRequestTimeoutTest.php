<?php

putenv('MIRZABOT_TESTING=1');
require_once __DIR__ . '/Assert.php';
require_once dirname(__DIR__, 2) . '/request.php';

$request = new CurlRequest('http://127.0.0.1:1');
$reflection = new ReflectionClass($request);
$property = $reflection->getProperty('timeout');
$property->setAccessible(true);

assertSameValue(40000, $property->getValue($request), 'default CurlRequest timeout should be 40 seconds');

passTest('CurlRequestTimeoutTest');
