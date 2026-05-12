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
        strpos($source, '$invoice = "reseller_buy|" . $randomString;') !== false,
        $label . ' should create an exact-order payment report immediately for low-balance reseller purchases'
    );
    assertTrueValue(
        strpos($source, 'savedata("clear", "id_order", $paymentOrderId);') !== false,
        $label . ' should jump directly to receipt collection after creating the exact-order payment'
    );
    assertTrueValue(
        strpos($source, 'بعد از پرداخت، فقط رسید را بفرستید') !== false,
        $label . ' should no longer ask the user to manually type the known shortage amount'
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
    assertTrueValue(
        strpos($source, 'sendmessage($Payment_report[\'id_user\'], $textbotlang[\'users\'][\'selectoption\'], $keyboard, \'HTML\');') === false,
        $label . ' payment finalizer should not send an extra generic menu prompt after delivery'
    );
}

passTest('ResellerExactOrderPaymentTest');
