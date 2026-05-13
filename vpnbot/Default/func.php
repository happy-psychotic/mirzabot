<?php

function readJsonFileIfExists($path, $default = [])
{
    if (!is_file($path)) {
        return $default;
    }

    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        return $default;
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : $default;
}

function resellerAvailableConfigLinks(array $panel, array $dataUserOut): array
{
    $links = [];
    if (($panel['sublink'] ?? '') === 'onsublink' && !empty($dataUserOut['subscription_url'])) {
        $links[] = (string)$dataUserOut['subscription_url'];
    }
    if (($panel['config'] ?? '') === 'onconfig' && isset($dataUserOut['links']) && is_array($dataUserOut['links'])) {
        foreach ($dataUserOut['links'] as $link) {
            $link = trim((string)$link);
            if ($link !== '') {
                $links[] = $link;
            }
        }
    }
    return array_values(array_unique($links));
}

function resellerDirectLinkUsername(string $text): string
{
    $trimmedText = trim($text);
    if ($trimmedText === '') {
        return '';
    }

    if (preg_match('~^(?:vless|vmess|ss|trojan)://[^\s]+#(\S+)~i', $trimmedText, $cfgMatch)) {
        $cfgFragment = urldecode($cfgMatch[1]);
        return (string)explode('-', $cfgFragment)[0];
    }

    if (strlen($trimmedText) > 32 && filter_var($trimmedText, FILTER_VALIDATE_URL)) {
        $subInfo = outputlinksub($trimmedText);
        if (isset($subInfo)) {
            $subInfo = json_decode($subInfo, true);
            if (isset($subInfo['username'])) {
                return (string)$subInfo['username'];
            }
        }
    }

    return '';
}

function DirectPaymentbot($order_id,$image = 'images.jpg'){
    global $pdo,$ManagePanel,$textbotlang,$keyboardextendfnished,$keyboard,$Confirm_pay,$from_id,$message_id,$datatextbot;
    $setting = select("setting", "*");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id,"select");
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'],"select");
    $Balance_id['Balance'] = json_decode(file_get_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json"),true)['Balance'];
    update("user","Processing_value","0", "id",$Balance_id['id']);
    update("user","Processing_value_one","0", "id",$Balance_id['id']);
    update("user","Processing_value_tow","0", "id",$Balance_id['id']);
    update("user","Processing_value_four","0", "id",$Balance_id['id']);
    $paymentTarget = explode("|", (string) $Payment_report['id_invoice'], 2);
    if (($paymentTarget[0] ?? '') === 'reseller_buy' && !empty($paymentTarget[1])) {
        $invoiceId = $paymentTarget[1];
        $invoice = select("invoice", "*", "id_invoice", $invoiceId, "select");
        $dataBase = select("botsaz", "*", "bot_token", $Payment_report['bottype'], "select");
        $userbot = $dataBase ? select("user", "*", "id", $dataBase['id_user'], "select") : false;
        $settingmain = select("setting", "*");
        $errorreport = select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
        $buyreport = select("topicid", "idreport", "report", "buyreport", "select")['idreport'];

        $userbalance = json_decode(file_get_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json"),true);
        if (!is_array($userbalance)) {
            $userbalance = ['Balance' => 0];
        }
        $userbalance['Balance'] = intval($userbalance['Balance']) + intval($Payment_report['price']);
        file_put_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json",json_encode($userbalance));
        update("Payment_report","payment_Status","paid","id_order",$Payment_report['id_order']);

        if (!$invoice || !$dataBase || !$userbot) {
            sendmessage($Payment_report['id_user'], "💎 مبلغ {$format_price_cart} تومان به کیف پول شما افزوده شد، اما سفارش مرتبط پیدا نشد. با پشتیبانی در ارتباط باشید.", null, 'HTML');
            return;
        }

        $marzban_list_get = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if (!$marzban_list_get) {
            sendmessage($Payment_report['id_user'], "💎 مبلغ {$format_price_cart} تومان به کیف پول شما افزوده شد، اما پنل سفارش در دسترس نیست. با پشتیبانی در ارتباط باشید.", null, 'HTML');
            return;
        }

        $customProductNames = [
            "🛍 حجم دلخواه",
            "⚙️ سرویس دلخواه",
            $textbotlang['users']['customsellvolume']['title']
        ];
        if (in_array($invoice['name_product'], $customProductNames, true)) {
            [$agentGigPrice, $agentDayPrice] = agentPricePerUnit((string)$invoice['id_user'], (string)$userbot['agent'], $marzban_list_get);
            $priceProductMain = (intval($invoice['Volume']) * intval($agentGigPrice)) + (intval($invoice['Service_time']) * intval($agentDayPrice));
            $info_product = [
                'name_product' => $invoice['name_product'],
                'code_product' => 'customvolume',
                'Volume_constraint' => intval($invoice['Volume']),
                'Service_time' => intval($invoice['Service_time']),
                'price_product' => intval($invoice['price_product']),
                'data_limit_reset' => 'no_reset',
            ];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name_product AND agent = :agent AND (Location = :location OR Location = '/all') LIMIT 1");
            $stmt->execute([
                ':name_product' => $invoice['name_product'],
                ':agent' => $userbot['agent'],
                ':location' => $invoice['Service_location'],
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$info_product) {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE agent = :agent AND (Location = :location OR Location = '/all') AND Volume_constraint = :volume AND Service_time = :service_time LIMIT 1");
                $stmt->execute([
                    ':agent' => $userbot['agent'],
                    ':location' => $invoice['Service_location'],
                    ':volume' => $invoice['Volume'],
                    ':service_time' => $invoice['Service_time'],
                ]);
                $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$info_product) {
                sendmessage($Payment_report['id_user'], "💎 مبلغ {$format_price_cart} تومان به کیف پول شما افزوده شد، اما محصول سفارش یافت نشد. با پشتیبانی در ارتباط باشید.", null, 'HTML');
                return;
            }
            $priceProductMain = agentProductPrice((string)$invoice['id_user'], (string)$userbot['agent'], $marzban_list_get, $info_product);
        }

        if (intval($priceProductMain) > intval($userbot['Balance']) && $userbot['agent'] != "n2") {
            sendmessage($Payment_report['id_user'], "💎 مبلغ {$format_price_cart} تومان به کیف پول شما افزوده شد، اما موجودی نماینده برای ساخت سرویس کافی نیست. با پشتیبانی در ارتباط باشید.", null, 'HTML');
            return;
        }

        $username_ac = strtolower((string)$invoice['username']);
        $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
        if (isset($DataUserOut['username'])) {
            $username_ac = rand(1000000, 9999999) . "_" . $username_ac;
        }

        $datetimestep = intval($invoice['Service_time']) == 0 ? 0 : strtotime(date("Y-m-d H:i:s", strtotime("+" . intval($invoice['Service_time']) . "days")));
        $datac = [
            'expire' => $datetimestep,
            'data_limit' => intval($invoice['Volume']) * pow(1024, 3),
            'from_id' => $invoice['id_user'],
            'username' => $Balance_id['username'],
            'type' => 'buy_agent_user_bot'
        ];
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
        if (($dataoutput['status'] ?? null) !== 'successful' || empty($dataoutput['username'])) {
            $errorMessage = $dataoutput['msg'] ?? 'Unknown reseller createUser failure';
            if (is_array($errorMessage) || is_object($errorMessage)) {
                $errorMessage = json_encode($errorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            update("invoice", "Status", "Unsuccessful", "id_invoice", $invoiceId);
            sendmessage($Payment_report['id_user'], "💎 مبلغ {$format_price_cart} تومان به کیف پول شما افزوده شد، اما در ساخت سرویس خطا رخ داد. می‌توانید بعداً دوباره خرید را انجام دهید.", $keyboard, 'HTML');
            if (strlen($settingmain['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $settingmain['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => "⭕️ خطای ساخت اشتراک در ربات نماینده بعد از تایید پرداخت\n✍️ دلیل خطا:\n{$errorMessage}\nآیدی کاربر: {$Balance_id['id']}\nنام کاربری کاربر: @{$Balance_id['username']}\nنام پنل: {$marzban_list_get['name_panel']}",
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? ($dataoutput['subscription_url'] ?? '') : "";
        $configLinks = (isset($dataoutput['configs']) && is_array($dataoutput['configs'])) ? $dataoutput['configs'] : [];
        $config_links_text = $marzban_list_get['config'] == "onconfig" ? formatConfigLinksForDelivery($configLinks) : "";
        $displayTime = intval($invoice['Service_time']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $invoice['Service_time'];
        $displayVolume = intval($invoice['Volume']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $invoice['Volume'];
        $caption = "✅ سرویس با موفقیت ایجاد شد\n\n👤 نام کاربری سرویس : <code>{$dataoutput['username']}</code>\n🌿 نام سرویس: {$invoice['name_product']}\n🇺🇳 لوکیشن: {$marzban_list_get['name_panel']}\n⏳ مدت زمان: {$displayTime} روز\n🗜 حجم سرویس: {$displayVolume} گیگابایت\n\n" . formatSubscriptionLinkForDelivery($output_config_link);
        if ($config_links_text !== '') {
            $caption .= "\n" . $config_links_text;
        }
        if (intval($invoice['Volume']) == 0) {
            $caption = str_replace(' گیگابایت', '', $caption);
        }
        $shoppingInfo = json_encode([
            'inline_keyboard' => [
                [['text' => "📚 مشاهده آموزش استفاده ", 'callback_data' => "helpbtn"]],
            ]
        ]);
        sendMessageService($marzban_list_get, $configLinks, $output_config_link, $dataoutput['username'], $shoppingInfo, $caption, $invoice['id_invoice'], $invoice['id_user'], $image);

        $userbalance['Balance'] = max(0, intval($userbalance['Balance']) - intval($invoice['price_product']));
        file_put_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json",json_encode($userbalance));
        $Balancebot = intval($userbot['Balance']) - intval($priceProductMain);
        update("user", "Balance", $Balancebot, "id", $userbot['id']);
        $invoiceNote = (string)($invoice['note'] ?? '');
        $noteWithMeta = preg_replace('/\|?__m:\d+/', '', $invoiceNote);
        $noteWithMeta = trim($noteWithMeta, '|');
        $noteWithMeta = '__m:' . intval($priceProductMain) . ($noteWithMeta !== '' ? '|' . $noteWithMeta : '');
        update("invoice", "note", $noteWithMeta, "id_invoice", $invoice['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $invoice['id_invoice']);
        update("invoice", "username", $dataoutput['username'], "id_invoice", $invoice['id_invoice']);

        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($Balance_id['number_username']) + 1;
            update("user", "number_username", $value, "id", $Balance_id['id']);
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($settingmain['numbercount']) + 1;
                update("setting", "numbercount", $value);
            }
        }

        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            Editmessagetext($from_id, $message_id, "✅ پرداخت تایید شد و سرویس برای کاربر ساخته شد.\n👤 شناسه کاربر: <code>{$Balance_id['id']}</code>\n🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}\n⚜️ نام کاربری: @{$Balance_id['username']}\n💸 مبلغ پرداختی: {$format_price_cart} تومان\n🌿 محصول: {$invoice['name_product']}", $Confirm_pay);
        }

        if (strlen($settingmain['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $settingmain['Channel_Report'],
                'message_thread_id' => $buyreport,
                'text' => "📣 خرید سرویس در ربات نماینده بعد از تایید پرداخت تکمیل شد.\n▫️آیدی عددی کاربر: <code>{$Balance_id['id']}</code>\n▫️نام کاربری کاربر: @{$Balance_id['username']}\n▫️نام ربات نماینده: @{$dataBase['username']}\n▫️نام کاربری کانفیگ: {$dataoutput['username']}\n▫️محصول: {$invoice['name_product']}\n▫️لوکیشن: {$invoice['Service_location']}\n▫️مبلغ نهایی: {$Payment_report['price']} تومان",
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
        $Balance_confrim = intval($Balance_id['Balance']) + intval($Payment_report['price']);
        $userbalance = json_decode(file_get_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json"),true);
        $userbalance['Balance'] = $Balance_confrim;
        file_put_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json",json_encode($userbalance));
        update("Payment_report","payment_Status","paid","id_order",$Payment_report['id_order']);
        $Payment_report['price'] = number_format($Payment_report['price'], 0);
        $format_price_cart = $Payment_report['price'];
        if($Payment_report['Payment_Method'] == "cart to cart" or   $Payment_report['Payment_Method'] == "arze digital offline"){
        $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
        افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$Payment_report['dec_not_confirmed']}";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$Payment_report['price']} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.
                
🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
}
function channel_check($id_channel){
    global $from_id;
        $channel_link = array();
         $response = telegram('getChatMember',[
                'chat_id' => $id_channel,
                'user_id' => $from_id
                ]);
            if($response['ok']){
        if(!in_array($response['result']['status'], ['member', 'creator', 'administrator'])){
                $channel_link[] = $id_channel;
            }
        }
        
        if(count($channel_link) == 0){
            return [];
        }else{
            return $channel_link;
        }
}
