<?php

putenv('MIRZABOT_TESTING=1');
require_once __DIR__ . '/Assert.php';
require_once dirname(__DIR__, 2) . '/function.php';

putenv('MIRZABOT_TEST_HELP_CONTENT_STATUS=off');
assertSameValue(false, hasUsableHelpContent(), 'help content should be unavailable when test override is off');

putenv('MIRZABOT_TEST_HELP_CONTENT_STATUS=on');
assertSameValue(true, hasUsableHelpContent(), 'help content should be available when test override is on');

$functionSource = file_get_contents(dirname(__DIR__, 2) . '/function.php');
assertTrueValue(
    strpos($functionSource, '!check_active_btn($setting[\'keyboardmain\'], "text_help") || !hasUsableHelpContent()') !== false,
    'service delivery help button should be hidden when help content is unavailable'
);

$indexSource = file_get_contents(dirname(__DIR__, 2) . '/index.php');
assertTrueValue(
    strpos($indexSource, '!check_active_btn($setting[\'keyboardmain\'], "text_help") || !hasUsableHelpContent()') !== false,
    'help command should be disabled when help content is unavailable'
);

passTest('HelpContentAvailabilityTest');

