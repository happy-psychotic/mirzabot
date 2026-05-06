<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Smoke tests must run from CLI.\n");
    exit(1);
}

$baseUrl = rtrim((string) getenv('ALIREZA_XUI_BASE_URL'), '/');
$username = (string) getenv('ALIREZA_XUI_USERNAME');
$password = (string) getenv('ALIREZA_XUI_PASSWORD');
$inboundId = (int) getenv('ALIREZA_XUI_INBOUND_ID');

if ($baseUrl === '' || $username === '' || $password === '' || $inboundId <= 0) {
    fwrite(STDERR, "Set ALIREZA_XUI_BASE_URL, ALIREZA_XUI_USERNAME, ALIREZA_XUI_PASSWORD, and ALIREZA_XUI_INBOUND_ID.\n");
    exit(2);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "PHP curl extension is required for this smoke test.\n");
    exit(2);
}

$clientUuid = trim((string) file_get_contents('/proc/sys/kernel/random/uuid'));
if ($clientUuid === '') {
    $clientUuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff)
    );
}

$clientEmail = 'mirzabot_smoke_' . date('YmdHis');
$subId = 'smoke' . bin2hex(random_bytes(4));
$cookie = tempnam(sys_get_temp_dir(), 'xui_smoke_cookie_');

function smokeRequest($method, $url, $cookie, $payload = null, $form = false)
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        if (!$form) {
            $headers[] = 'Content-Type: application/json';
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        throw new RuntimeException("curl error for {$url}: {$error}");
    }

    return [$status, (string) $body, json_decode((string) $body, true)];
}

function smokeRequireSuccess($label, $result)
{
    [$status, $body, $decoded] = $result;
    if ($status !== 200 || !is_array($decoded) || ($decoded['success'] ?? false) !== true) {
        throw new RuntimeException("{$label} failed: HTTP {$status} body={$body}");
    }

    echo "PASS {$label}\n";
}

try {
    smokeRequireSuccess(
        'login',
        smokeRequest(
            'POST',
            "{$baseUrl}/login",
            $cookie,
            'username=' . rawurlencode($username) . '&password=' . rawurlencode($password),
            true
        )
    );

    $addPayload = [
        'id' => $inboundId,
        'settings' => json_encode([
            'clients' => [[
                'id' => $clientUuid,
                'flow' => '',
                'email' => $clientEmail,
                'totalGB' => 1073741824,
                'expiryTime' => 0,
                'enable' => true,
                'tgId' => '',
                'subId' => $subId,
                'reset' => 0,
            ]],
            'decryption' => 'none',
            'fallbacks' => [],
        ], JSON_UNESCAPED_SLASHES),
    ];
    smokeRequireSuccess('addClient', smokeRequest('POST', "{$baseUrl}/xui/API/inbounds/addClient", $cookie, json_encode($addPayload, JSON_UNESCAPED_SLASHES)));

    [$status, $body, $decoded] = smokeRequest('GET', "{$baseUrl}/xui/API/inbounds/", $cookie);
    smokeRequireSuccess('listAfterAdd', [$status, $body, $decoded]);

    $found = false;
    foreach (($decoded['obj'] ?? []) as $inbound) {
        foreach (($inbound['clientStats'] ?? []) as $stats) {
            if (($stats['email'] ?? '') === $clientEmail) {
                $found = true;
            }
        }
    }
    if (!$found) {
        throw new RuntimeException('created client was not found in clientStats');
    }
    echo "PASS findCreatedClient email={$clientEmail} uuid={$clientUuid} subId={$subId}\n";

    $updatePayload = [
        'id' => $inboundId,
        'settings' => json_encode([
            'clients' => [[
                'id' => $clientUuid,
                'flow' => '',
                'email' => $clientEmail,
                'totalGB' => 2147483648,
                'expiryTime' => 0,
                'enable' => false,
                'tgId' => '',
                'subId' => $subId,
            ]],
            'decryption' => 'none',
            'fallbacks' => [],
        ], JSON_UNESCAPED_SLASHES),
    ];
    smokeRequireSuccess('updateClient', smokeRequest('POST', "{$baseUrl}/xui/API/inbounds/updateClient/{$clientUuid}", $cookie, json_encode($updatePayload, JSON_UNESCAPED_SLASHES)));

    [$status, $body, $decoded] = smokeRequest('GET', "{$baseUrl}/xui/API/inbounds/", $cookie);
    smokeRequireSuccess('listAfterUpdate', [$status, $body, $decoded]);

    $updated = false;
    foreach (($decoded['obj'] ?? []) as $inbound) {
        $settings = json_decode($inbound['settings'] ?? '', true);
        foreach (($settings['clients'] ?? []) as $client) {
            if (($client['email'] ?? '') === $clientEmail) {
                $updated = (($client['enable'] ?? true) === false) && (($client['totalGB'] ?? 0) === 2147483648);
            }
        }
    }
    if (!$updated) {
        throw new RuntimeException('updated client fields were not preserved as expected');
    }
    echo "PASS verifyUpdatedClient\n";
} finally {
    try {
        [$deleteStatus, $deleteBody, $deleteDecoded] = smokeRequest('POST', "{$baseUrl}/xui/API/inbounds/{$inboundId}/delClient/{$clientUuid}", $cookie);
        if ($deleteStatus === 200 && is_array($deleteDecoded) && ($deleteDecoded['success'] ?? false) === true) {
            echo "PASS cleanupDeleteClient\n";
        } else {
            echo "WARN cleanupDeleteClient failed HTTP {$deleteStatus} body={$deleteBody}\n";
        }
    } catch (Throwable $cleanupError) {
        echo 'WARN cleanupDeleteClient exception: ' . $cleanupError->getMessage() . "\n";
    }
    @unlink($cookie);
}

