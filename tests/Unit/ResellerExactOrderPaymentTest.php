<?php

require_once __DIR__ . '/Assert.php';

$root = dirname(__DIR__, 2);

$indexFiles = [
    $root . '/vpnbot/Default/index.php',
    $root . '/vpnbot/update/index.php',
];

foreach ($indexFiles as $file) {
    $source = file_get_contents($file);
    $label = basename(dirname($file));

    assertTrueValue(
        strpos($source, 'savedata("save", "pending_payment_type", "reseller_buy");') !== false,
        $label . ' should mark low-balance reseller purchases as exact-order payments'
    );
    assertTrueValue(
        strpos($source, '$invoice = "reseller_buy|" . $userdate[\'pending_invoice_id\'];') !== false,
        $label . ' should link the payment report to the selected reseller order'
    );
    assertTrueValue(
        strpos($source, 'همان سرویس برای شما ساخته و ارسال شود') !== false,
        $label . ' should tell the user the paid order will be auto-delivered after approval'
    );
}

$funcFiles = [
    $root . '/vpnbot/Default/func.php',
    $root . '/vpnbot/update/func.php',
];

foreach ($funcFiles as $file) {
    $source = file_get_contents($file);
    $label = basename(dirname($file));

    assertTrueValue(
        strpos($source, "if ((\$paymentTarget[0] ?? '') === 'reseller_buy' && !empty(\$paymentTarget[1])) {") !== false,
        $label . ' payment finalizer should detect exact-order reseller payments'
    );
    assertTrueValue(
        strpos($source, 'sendMessageService($marzban_list_get, $configLinks, $output_config_link, $dataoutput[\'username\'], $shoppingInfo, $caption, $invoice[\'id_invoice\'], $invoice[\'id_user\'], $image);') !== false,
        $label . ' payment finalizer should deliver the created reseller service after approval'
    );
}

passTest('ResellerExactOrderPaymentTest');
