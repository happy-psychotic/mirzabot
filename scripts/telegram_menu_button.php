<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$scriptDir = __DIR__;
$repoRoot = dirname($scriptDir);

$args = $_SERVER['argv'];
array_shift($args);

$action = $args[0] ?? null;
if ($action !== null) {
    array_shift($args);
}

$options = [];
foreach ($args as $arg) {
    if (strpos($arg, '--') !== 0) {
        continue;
    }

    $option = substr($arg, 2);
    [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
    $options[$key] = $value;
}

if (isset($options['help']) || !in_array($action, ['get', 'set', 'delete'], true)) {
    $help = <<<TXT
Usage:
  php scripts/telegram_menu_button.php get [--config=/path/to/config.php] [--token=BOT_TOKEN]
  php scripts/telegram_menu_button.php set [--config=/path/to/config.php] [--token=BOT_TOKEN] [--url=https://example.com/app/] [--text=Open]
  php scripts/telegram_menu_button.php delete [--config=/path/to/config.php] [--token=BOT_TOKEN]

Notes:
  - If --token is omitted, the script reads \$APIKEY from the config file.
  - If --url is omitted on "set", the script uses https://<domainhosts>/app/ from the config file.
  - "delete" resets the button to Telegram's default behavior.
TXT;

    fwrite(STDOUT, $help . PHP_EOL);
    exit($action === null ? 1 : 0);
}

$configPath = $options['config'] ?? ($repoRoot . '/config.php');
if (!is_file($configPath)) {
    fwrite(STDERR, "Config file not found: {$configPath}\n");
    exit(1);
}

require $configPath;

$token = $options['token'] ?? ($APIKEY ?? null);
if (!$token || strpos($token, '{') !== false) {
    fwrite(STDERR, "Telegram bot token is missing. Pass --token=... or use a live config.php.\n");
    exit(1);
}

$domain = $domainhosts ?? null;
$defaultUrl = $domain ? 'https://' . trim($domain, '/') . '/app/' : null;
$buttonText = $options['text'] ?? 'Open';
$webAppUrl = $options['url'] ?? $defaultUrl;

if ($action === 'set' && !$webAppUrl) {
    fwrite(STDERR, "Web App URL is missing. Pass --url=... or use a config.php with \$domainhosts.\n");
    exit(1);
}

function telegramMenuButtonRequest(string $token, string $method, array $payload = []): array
{
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);

    if ($ch === false) {
        return [
            'ok' => false,
            'description' => 'Unable to initialise cURL.'
        ];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $rawResponse = curl_exec($ch);
    if ($rawResponse === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => false,
            'description' => $error !== '' ? $error : 'Telegram request failed.'
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error_code' => $httpCode,
            'description' => 'Invalid response received from Telegram.',
            'raw' => $rawResponse,
        ];
    }

    return $decoded;
}

switch ($action) {
    case 'get':
        $result = telegramMenuButtonRequest($token, 'getChatMenuButton');
        break;

    case 'set':
        $result = telegramMenuButtonRequest($token, 'setChatMenuButton', [
            'menu_button' => json_encode([
                'type' => 'web_app',
                'text' => $buttonText,
                'web_app' => [
                    'url' => $webAppUrl,
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        break;

    case 'delete':
        $result = telegramMenuButtonRequest($token, 'setChatMenuButton', [
            'menu_button' => json_encode([
                'type' => 'default',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        break;

    default:
        fwrite(STDERR, "Unsupported action: {$action}\n");
        exit(1);
}

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit(!empty($result['ok']) ? 0 : 1);
