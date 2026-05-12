<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
#----------------[  admin section  ]------------------#

$textadmin = ["panel", "/panel", "پنل مدیریت", "ادمین", "👨‍💼 پنل مدیریت"];
if (!in_array($from_id, $admin_idsmain) and !in_array($from_id, $admin_ids)) {
    return;
}
if (in_array($text, $textadmin) || $datain == "admin") {
    $text_admin = "Version Bot : $version
Panel Admin";
    sendmessage($from_id, $text_admin, $keyboardadmin, 'HTML');
    step("home", $from_id);
    return;
}
if ($text == "بازگشت به منوی ادمین") {
    sendmessage($from_id, "به منوی ادمین بازگشتید", $keyboardadmin, 'HTML');
    step("home", $from_id);
    return;
}
if ($text == "📞 تنظیم نام کاربری پشتیبانی") {
    sendmessage($from_id, "📌 نام کاربری جدید خود را بدون @ ارسال کنید", $backadmin, 'HTML');
    step("getusernamesupport", $from_id);
} elseif ($user['step'] == "getusernamesupport") {
    sendmessage($from_id, "✅ نام کاربری پشتیبانی برای شما با موفقیت تنظیم گردید.", $keyboardadmin, 'HTML');
    step("home", $from_id);
    $setting['support_username'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
} elseif ($text == "🔋 قیمت حجم") {
    sendmessage($from_id, "📌 قیمت هر گیگ حجم را ارسال نمایید. 
قیمت پایه حجم. : {$setting['minpricevolume']} تومان
قیمت فعلی حجم. : {$setting['pricevolume']} تومان", $backadmin, 'HTML');
    step("getpricvolumeadmin", $from_id);
} elseif ($user['step'] == "getpricvolumeadmin") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) < intval($setting['minpricevolume'])) {
        sendmessage($from_id, "❌ قیمت حجم باید بزرگ تر از قیمت پایه حجم باشد.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ قیمت حجم با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');
    step("home", $from_id);
    $setting['pricevolume'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
} elseif ($text == "⌛️ قیمت زمان") {
    sendmessage($from_id, "
📌 قیمت هر روز زمان را ارسال نمایید.
 قیمت پایه زمان. : {$setting['minpricetime']} تومان
قیمت فعلی شما : {$setting['pricetime']} تومان", $backadmin, 'HTML');
    step("getpricvtimeadmin", $from_id);
} elseif ($user['step'] == "getpricvtimeadmin") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) < intval($setting['minpricetime'])) {
        sendmessage($from_id, "❌ قیمت زمان باید بزرگ تر از قیمت پایه زمان باشد.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ قیمت زمان با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');
    step("home", $from_id);
    $setting['pricetime'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
} elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget)) {
    $order_id = $dataget[1];
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [],
            [
                ['text' => "✅ تایید شده", 'callback_data' => "confirmpaid"],
            ]
        ]
    ]);
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $textconfrom = "✅. پرداخت توسط ادمین دیگری تایید شده
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
    💸 مبلغ پرداختی: $format_price_cart تومان
";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        return;
    }
    DirectPaymentbot($order_id);
    $Payment_report['price'] = number_format($Payment_report['price']);
    $text_report = "📣 نماینده رسیبد پرداخت کارت به کارت را تایید کرد.
        
اطلاعات :
👤آیدی عددی  ادمین تایید کننده : $from_id
💰 مبلغ پرداخت : {$Payment_report['price']}
👤 ایدی عددی کاربر : <code>{$Payment_report['id_user']}</code>
👤 نام کاربری کاربر : @{$Balance_id['username']} 
        کد پیگیری پرداحت : $order_id";
    if (strlen($settingmain['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $settingmain['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
    update("user", "Processing_value_one", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "none", "id", $Balance_id['id']);
} elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr)) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $id_order);

    sendmessage($from_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $backadmin, 'HTML');
    step('reject-dec', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "reject-dec") {
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
    update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
    $text_reject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.
✍️ $text
🛒 کد پیگیری پرداخت: {$user['Processing_value_one']}
                ";
    sendmessage($from_id, $textbotlang['Admin']['Payment']['Rejected'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $text_reject, null, 'HTML');
    step('home', $from_id);
    $text_report = "❌ یک ادمین رسید پرداخت کارت به کارت را رد کرد.
        
اطلاعات :
👤آیدی عددی  ادمین تایید کننده : $from_id
نام کاربری ادمین تایید کننده : @$username
💰 مبلغ پرداخت : {$Payment_report['price']}
دلیل رد کردن : $text
👤 ایدی عددی کاربر: {$Payment_report['id_user']}";
    if (strlen($settingmain['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $settingmain['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "👨‍🔧  مدیریت ادمین ها") {
    $keyboardadmin = ['inline_keyboard' => []];
    foreach ($admin_ids as $admin) {
        $keyboardadmin['inline_keyboard'][] = [
            ['text' => "❌", 'callback_data' => "removeadmin_" . $admin],
            ['text' => $admin, 'callback_data' => "adminlist"],
        ];
    }
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "👨‍💻 اضافه کردن ادمین", 'callback_data' => "addnewadmin"],
    ];
    $keyboardadmin = json_encode($keyboardadmin);
    sendmessage($from_id, "📌 در بخش زیر می توانید لیست ادمین ها را مشاهده کنید همچنین با زدن دکمه ضربدر می توانید یک ادمین را حذف کنید", $keyboardadmin, 'HTML');
} elseif ($datain == "addnewadmin") {
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif ($user['step'] == "addadmin") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
    step('home', $from_id);
    $admin_ids[] = $text;
    update("botsaz", "admin_ids", json_encode($admin_ids), "bot_token", $ApiToken);
} elseif (preg_match('/removeadmin_(\w+)/', $datain, $dataget)) {
    $idadmin = $dataget[1];
    $count = 0;
    foreach ($admin_ids as $admin) {
        if ($admin == $idadmin) {
            unset($admin_ids[$count]);
            break;
        }
        $count += 1;
    }
    unset($admin_ids[$idadmin]);
    $admin_ids = array_values($admin_ids);
    update("botsaz", "admin_ids", json_encode($admin_ids), "bot_token", $ApiToken);
    sendmessage($from_id, "✅ ادمین با موفقیت حذف گردید", null, 'HTML');
} elseif ($text == "🔍 جستجو کاربر") {
    $keyboardSearchUser = json_encode([
        'keyboard' => [
            [['text' => "👤 انتخاب از مخاطبین", 'request_user' => ['request_id' => 1, 'user_is_bot' => false]]],
            [['text' => "🏠 بازگشت به منوی اصلی"]],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ]);
    sendmessage($from_id, "🔍 شناسه کاربر را ارسال کنید:\n\n• آیدی عددی (مثال: 123456789)\n• یوزرنیم تلگرام (مثال: @username)\n• یا از دکمه زیر کاربر را انتخاب کنید", $keyboardSearchUser, 'HTML');
    step('show_info', $from_id);
} elseif ($user['step'] == "show_info" || strpos($text, "/user ") !== false || strpos($text, "/id ") !== false) {
    if (explode(" ", $text)[0] == "/user") {
        $id_user = explode(" ", $text)[1];
    } elseif (explode(" ", $text)[0] == "/id") {
        $id_user = explode(" ", $text)[1];
    } elseif ($contact_id != 0) {
        $id_user = strval($contact_id);
    } elseif (isset($update['message']['user_shared']['user_id'])) {
        $id_user = strval($update['message']['user_shared']['user_id']);
    } elseif (strpos($text, '@') === 0) {
        $search_username = ltrim($text, '@');
        $found = $pdo->prepare("SELECT id FROM user WHERE username = ? LIMIT 1");
        $found->execute([$search_username]);
        $id_user = $found->fetchColumn();
        if (!$id_user) {
            sendmessage($from_id, "❌ کاربری با این یوزرنیم پیدا نشد.", $keyboardadmin, 'HTML');
            step('home', $from_id);
            return;
        }
    } else {
        $id_user = $text;
    }
    if (!in_array($id_user, $users_ids)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    $date = date("Y-m-d");
    $dayListSell = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user' AND bottype = '$ApiToken'"));
    $balanceall = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = '$id_user' AND Payment_Method != 'low balance by admin' AND bottype = '$ApiToken'"));
    $subbuyuser = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user' AND bottype = '$ApiToken'"));
    $invoicecount = mysqli_fetch_assoc(mysqli_query($connect, "SELECT count(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user' AND bottype = '$ApiToken'"))['count(*)'];
    if ($invoicecount == 0) {
        $sumvolume['SUM(Volume)'] = 0;
    } else {
        $sumvolume = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user' AND name_product != 'سرویس تست'"));
    }
    $user = select("user", "*", "id", $id_user, "select");
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$user['roll_Status']];
    if ($subbuyuser['SUM(price_product)'] == null)
        $subbuyuser['SUM(price_product)'] = 0;
    $user['Balance'] = number_format($user['Balance']);
    if ($user['register'] != "none") {
        if ($user['register'] == null)
            return;
        $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    } else {
        $userjoin = "نامشخص";
    }
    if ($user['last_message_time'] == null) {
        $lastmessage = "";
    } else {
        $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
    }
    $datefirst = time() - 86400;
    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn();
    if ($suminvoicehours == null) {
        $suminvoicehours = "0";
    }
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn();
    $userbalance = number_format(json_decode(file_get_contents("data/$id_user/$id_user.json"), true)['Balance']);
    if ($suminvoicemonth == null) {
        $suminvoicemonth = "0";
    }
    $blockBtnText = $user['User_Status'] == "block" ? "✅ رفع مسدودی" : "🚫 مسدود کردن";
    $blockCallback = $user['User_Status'] == "block" ? "resellerunbanuser_$id_user" : "resellerbanuser_$id_user";
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "افزایش موجودی", 'callback_data' => 'addbalanceuser_' . $id_user],
                ['text' => "کم کردن موجودی", 'callback_data' => 'lowbalanceuser_' . $id_user],
            ],
            [
                ['text' => "💸 درصد تخفیف", 'callback_data' => 'resellersetdiscount_' . $id_user],
                ['text' => "📦 مشاهده سفارشات", 'callback_data' => 'resellervieworders_' . $id_user],
            ],
            [
                ['text' => $blockBtnText, 'callback_data' => $blockCallback],
            ],
        ]
    ]);
    $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$user['User_Status']}
⭕️ نام کاربری کاربر : @{$user['username']}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$user['limit_usertest']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}

💎 گزارشات مالی

🔰 موجودی کاربر : $userbalance
🔰 درصد تخفیف کاربر : {$user['pricediscount']}%
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$balanceall['SUM(price)']}
🔰 جمع کل خرید : {$subbuyuser['SUM(price_product)']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : $suminvoicehours تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : $suminvoicemonth تومان
";
    sendmessage($from_id, $textinfouser, $keyboardmanage, 'HTML');
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "🔎 جستجو سرویس") {
    sendmessage($from_id, "🔎 نام کاربری سرویس یا لینک کانفیگ را ارسال کنید:", $backadmin, 'html');
    step('searchservicereseller', $from_id);
} elseif ($user['step'] == "searchservicereseller" || preg_match('/manageinvoicereseller_(\w+)/', $datain, $datagetr2)) {
    if (isset($datagetr2[1])) {
        $OrderUser = select("invoice", "*", "id_invoice", $datagetr2[1], "select");
        if (!$OrderUser || $OrderUser['bottype'] !== $ApiToken) {
            sendmessage($from_id, "❌ سرویسی یافت نشد.", $keyboardadmin, 'html');
            return;
        }
        $results = [$OrderUser];
    } else {
        step('home', $from_id);
        $searchInput = trim($text);
        if (preg_match('~^(?:vless|vmess|ss|trojan)://[^#]+#(.+)$~i', $searchInput, $linkMatch)) {
            $fragment = urldecode($linkMatch[1]);
            $searchInput = explode('-', $fragment)[0];
        }
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :q, '%') OR note LIKE CONCAT('%', :q2, '%')) AND bottype = :bottype LIMIT 10");
        $stmt->execute([':q' => $searchInput, ':q2' => $searchInput, ':bottype' => $ApiToken]);
        if ($stmt->rowCount() == 0) {
            sendmessage($from_id, "❌ سرویسی یافت نشد.", $keyboardadmin, 'html');
            return;
        }
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($results as $OrderUser) {
        $keyboardlists = ['inline_keyboard' => []];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "♻️ بروزرسانی", 'callback_data' => "manageinvoicereseller_" . $OrderUser['id_invoice']],
        ];
        if (isset($OrderUser['time_sell'])) {
            $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
        } else {
            $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
        }
        if ($OrderUser['name_product'] == "سرویس تست") {
            $st = $OrderUser['Service_time'] . "ساعته";
            $sv = $OrderUser['Volume'] . "مگابایت";
        } else {
            $st = $OrderUser['Service_time'] . "روزه";
            $sv = $OrderUser['Volume'] . "گیگابایت";
        }
        $text_order = "
🛒 شماره سفارش  :  <code>{$OrderUser['id_invoice']}</code>
🛒  وضعیت سفارش در ربات : <code>{$OrderUser['Status']}</code>
🙍‍♂️ شناسه کاربر : <code>{$OrderUser['id_user']}</code>
👤 نام کاربری اشتراک :  <code>{$OrderUser['username']}</code>
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$OrderUser['price_product']} تومان
⚜️ حجم سرویس خریداری شده : $sv
⏳ زمان سرویس خریداری شده : $st
📆 تاریخ خرید : $datatime
";
        $DataUserOut = $ManagePanel->DataUser($OrderUser['Service_location'], $OrderUser['username']);
        if ($DataUserOut['status'] == "Unsuccessful") {
            sendmessage($from_id, "کاربر در پنل وجود ندارد", null, 'html');
            sendmessage($from_id, $text_order, json_encode($keyboardlists), 'HTML');
            continue;
        }
        if ($DataUserOut['online_at'] == "online") {
            $lastonline = 'آنلاین';
        } elseif ($DataUserOut['online_at'] == "offline") {
            $lastonline = 'آفلاین';
        } else {
            if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
                $lastonline = jdate('Y/m/d H:i:s', strtotime($DataUserOut['online_at']));
            } else {
                $lastonline = "متصل نشده";
            }
        }
        $status = $DataUserOut['status'];
        $status_var = [
            'active' => $textbotlang['users']['stateus']['active'],
            'limited' => $textbotlang['users']['stateus']['limited'],
            'disabled' => $textbotlang['users']['stateus']['disabled'],
            'expired' => $textbotlang['users']['stateus']['expired'],
            'on_hold' => $textbotlang['users']['stateus']['on_hold'],
            'Unknown' => $textbotlang['users']['stateus']['Unknown'],
            'deactivev' => $textbotlang['users']['stateus']['disabled'],
        ][$status] ?? $status;
        $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];
        $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];
        $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
        $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";
        $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];
        $timeDiff = $DataUserOut['expire'] - time();
        $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];
        $lastupdate = "";
        if ($DataUserOut['sub_updated_at'] !== null) {
            $dateTime = new DateTime($DataUserOut['sub_updated_at'], new DateTimeZone('UTC'));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
            $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
        }
        $limitValue = isset($DataUserOut['data_limit']) ? (float)$DataUserOut['data_limit'] : 0;
        $usedTrafficValue = isset($DataUserOut['used_traffic']) ? (float)$DataUserOut['used_traffic'] : 0;
        $Percent = $limitValue > 0 ? round(abs(($limitValue - $usedTrafficValue) * 100 / $limitValue), 2) : 100;
        $text_order .= "
 وضعیت سرویس : $status_var

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
        $namestatus = $DataUserOut['status'] == "active" ? '❌ خاموش کردن اکانت' : '💡 روشن کردن اکانت';
        $keyboardlists['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extendadmin_' . $OrderUser['id_invoice']],
            ['text' => $namestatus, 'callback_data' => 'changestatusreselleradmin_' . $OrderUser['id_invoice']],
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => '📲 دریافت کانفیگ', 'callback_data' => 'adminconfig_' . $OrderUser['id_invoice']],
            ['text' => '🔗 لینک اشتراک', 'callback_data' => 'subscriptionurl_' . $OrderUser['id_invoice']],
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => '✏️ تغییر نام سرویس', 'callback_data' => 'renameservice_' . $OrderUser['id_invoice']],
            ['text' => '🔄 تغییر لینک', 'callback_data' => 'changelink_' . $OrderUser['id_invoice']],
        ];
        sendmessage($from_id, $text_order, json_encode($keyboardlists), 'HTML');
    }
    if (!isset($datagetr2[1])) {
        sendmessage($from_id, "✅ جستجو پایان یافت.", $keyboardadmin, 'html');
    }
} elseif (preg_match('/removeservicereseller-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (!$info_product || $info_product['bottype'] !== $ApiToken) return;
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $id_invoice);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif (preg_match('/removeserviceresellerandback-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (!$info_product || $info_product['bottype'] !== $ApiToken) return;
    if ($info_product['Status'] == "removebyadmin") {
        sendmessage($from_id, "❌ سرویس از قبل حذف شده است", $keyboardadmin, 'HTML');
        return;
    }
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $id_invoice);
    // Refund user's balance in reseller bot (stored in data JSON file)
    if (intval($info_product['price_product']) > 0) {
        $uid = $info_product['id_user'];
        $userJsonPath = "data/$uid/$uid.json";
        if (file_exists($userJsonPath)) {
            $userJsonData = json_decode(file_get_contents($userJsonPath), true);
            $userJsonData['Balance'] = ($userJsonData['Balance'] ?? 0) + intval($info_product['price_product']);
            file_put_contents($userJsonPath, json_encode($userJsonData));
        }
        $textadd = "💎 کاربر عزیز مبلغ {$info_product['price_product']} تومان به موجودی کیف پول تان اضافه گردید.";
        sendmessage($uid, $textadd, null, 'HTML');
    }
    // Refund reseller's main-bot balance using stored __m: value in note
    preg_match('/__m:(\d+)/', $info_product['note'] ?? '', $metaMatch);
    $priceMain = isset($metaMatch[1]) ? intval($metaMatch[1]) : 0;
    if ($priceMain > 0) {
        $botbalance = select("botsaz", "*", "bot_token", $ApiToken, "select");
        $userbotbalance = select("user", "*", "id", $botbalance['id_user'], "select");
        $newMainBalance = $userbotbalance['Balance'] + $priceMain;
        update("user", "Balance", $newMainBalance, "id", $userbotbalance['id']);
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif (preg_match('/extendadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (!$nameloc || $nameloc['bottype'] !== $ApiToken) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ کاربر هنوز به سرویس متصل نشده است.", null, 'html');
        return;
    }
    savedata("clear", "id_invoice", $id_invoice);
    savedata("save", "name_panel", $nameloc['Service_location']);
    savedata("save", "admin_extend", "1");
    deletemessage($from_id, $message_id);
    $query = "SELECT * FROM product WHERE (Location = '{$nameloc['Service_location']}' OR Location = '/all') AND agent = '{$userbot['agent']}'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    if ($stmt->rowCount() > 0 && $setting['show_product'] == false) {
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$userbot['agent']];
        $statuscustom = ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale");
        $query = "SELECT * FROM product WHERE (Location = '{$marzban_list_get['name_panel']}' OR Location = '/all') AND agent = '{$userbot['agent']}'";
        $prodcut = KeyboardProduct($marzban_list_get['name_panel'], $query, 0, "selectproductextends_", $statuscustom, "backadmin", null, $customvolume = "customvolumeextend");
        sendmessage($from_id, "🛍️ سرویسی که می‌خواهید تمدید کنید را انتخاب کنید:", $prodcut, 'HTML');
    } else {
        $custompricevalue = $setting['pricevolume'];
        $mainvolume = json_decode($marzban_list_get['mainvolume'], true)[$userbot['agent']];
        $maxvolume = json_decode($marzban_list_get['maxvolume'], true)[$userbot['agent']];
        $textcustom = "📌 حجم درخواستی را ارسال کنید.\n🔔 قیمت هر گیگ حجم $custompricevalue تومان.\n🔔 حداقل $mainvolume گیگ، حداکثر $maxvolume گیگ.";
        sendmessage($from_id, $textcustom, $backadmin, 'html');
        step('gettimecustomvolextend', $from_id);
    }
} elseif (preg_match('/changestatusreselleradmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (!$nameloc || $nameloc['bottype'] !== $ApiToken) return;
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ هنوز به کانفیگ متصل نشده است و امکان تغییر وضعیت سرویس وجود ندارد.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmkbd = json_encode([
            'inline_keyboard' => [
                [['text' => '✅ تایید و غیرفعال کردن کانفیگ', 'callback_data' => "confirmstatusreseller_" . $id_invoice]],
                [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoicereseller_" . $id_invoice]],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ خاموش و دیگر امکان اتصال وجود ندارد.", $confirmkbd);
    } else {
        $confirmkbd = json_encode([
            'inline_keyboard' => [
                [['text' => '✅ تایید و فعال کردن کانفیگ', 'callback_data' => "confirmstatusreseller_" . $id_invoice]],
                [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoicereseller_" . $id_invoice]],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ روشن خواهد شد.", $confirmkbd);
    }
} elseif (preg_match('/confirmstatusreseller_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (!$nameloc || $nameloc['bottype'] !== $ApiToken) return;
    $bakinfos = json_encode([
        'inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoicereseller_" . $id_invoice]]]
    ]);
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        update("invoice", "Status", "active", "id_invoice", $id_invoice);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        update("invoice", "Status", "disablebyadmin", "id_invoice", $id_invoice);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/addbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
    step('addbalanceusercurrent', $from_id);
} elseif ($user['step'] == "addbalanceusercurrent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,bottype) VALUES (?,?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "add balance by admin";
    $invoice = null;
    $stmt->bind_param("ssssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice, $ApiToken);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanced'], $keyboardadmin, 'html');
    $userbalance = json_decode(file_get_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json"), true);
    $Balance_add_user = $userbalance['Balance'] + $text;
    $userbalance['Balance'] = $Balance_add_user;
    file_put_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json", json_encode($userbalance));
    $heibalanceuser = number_format($text, 0);
    $textadd = "💎 کاربر عزیز مبلغ $heibalanceuser تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($user['Processing_value'], $textadd, null, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/lowbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['lowbalanceuserdec'], $backadmin, 'html');
    step('addbalanceuser', $from_id);
} elseif ($user['step'] == "addbalanceuser") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,bottype) VALUES (?,?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "low balance by admin";
    $invoice = null;
    $stmt->bind_param("ssssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice, $ApiToken);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['lowbalanced'], $keyboardadmin, 'html');
    $userbalance = json_decode(file_get_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json"), true);
    $Balance_add_user = intval($userbalance['Balance']) - intval($text);
    $userbalance['Balance'] = $Balance_add_user;
    file_put_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json", json_encode($userbalance));
    $lowbalanceuser = number_format($text, 0);
    $textkam = "❌ کاربر عزیز مبلغ $lowbalanceuser تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    $statistics = select("user", "*", "bottype", $ApiToken, "count");
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
} elseif ($text == "📊 آمار ربات") {
    $statistics = select("user", "*", "bottype", $ApiToken, "count");
    $stmt2 = $pdo->prepare("SELECT COUNT( DISTINCT id_user) as count FROM `invoice` WHERE name_product = 'سرویس تست' AND  bottype = '$ApiToken'");
    $stmt2->execute();
    $statisticsorder = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product = 'سرویس تست' AND bottype = '$ApiToken'");
    $stmt->execute();
    $count_usertest = $stmt->rowCount();
    $sql1 = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست' AND bottype = '$ApiToken'";
    $stmt1 = $pdo->query($sql1);
    $invoice = $stmt1->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sql2 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست' AND bottype = '$ApiToken'";
    $stmt2 = $pdo->query($sql2);
    $invoicesum = number_format($stmt2->fetch(PDO::FETCH_ASSOC)['total_price'], 0);
    $statisticsall = "
📊 آمار کلی ربات  

📌 تعداد کاربران : $statistics نفر
📌 تعداد کاربرانی که خرید داشتند : $statisticsorder نفر
📌 تعداد اکانت های تست گرفته شده : $count_usertest نفر
📌 تعداد فروش کل : $invoice عدد
📌 جمع فروش کل : $invoicesum تومان
";
    sendmessage($from_id, $statisticsall, null, 'HTML');
} elseif ($text == "💰 تنظیم قیمت محصول") {
    if (!is_file('product.json')) {
        file_put_contents('product.json', "{}");
    }
    $product = [];
    $getdataproduct = mysqli_query($connect, "SELECT * FROM product WHERE agent = '{$userbot['agent']}'");
    while ($row = mysqli_fetch_assoc($getdataproduct)) {
        $panel = select("marzban_panel", "*", "name_panel", $row['Location'], "select");
        if (in_array($panel['name_panel'], $hide_panel))
            continue;
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => "بازگشت به منوی ادمین"],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
    sendmessage($from_id, "از لیست زیر محصولی که می خواهید قیمت تنظیم نمایید را انتخاب کنید", $json_list_product_list_admin, 'HTML');
    step("selectproductprice", $from_id);
} elseif ($user['step'] == "selectproductprice") {
    $product = select("product", "*", "name_product", $text, "select");
    if ($product == false) {
        sendmessage($from_id, "❌ محصول انتخابی وجود ندارد.", null, 'HTML');
        return;
    }
    savedata("clear", "code_product", $product['code_product']);
    step("getpriceproduct", $from_id);
    if (intval($userbot['pricediscount']) != 0) {
        $resultper = ($product['price_product'] * $userbot['pricediscount']) / 100;
        $product['price_product'] = $product['price_product'] - $resultper;
    }
    sendmessage($from_id, "📌  قیمت خود را ارسال کنید
قیمت پایه :{$product['price_product']}", $backadmin, 'HTML');
} elseif ($user['step'] == "getpriceproduct") {
    $userdata = json_decode($user['Processing_value'], true);
    $product = select("product", "*", "code_product", $userdata['code_product'], "select");
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], null, 'HTML');
        return;
    }
    if (intval($text) < intval($product['price_product'])) {
        sendmessage($from_id, "❌ قیمت شما کوچیک تر از قیمت پایه است.", null, 'HTML');
        return;
    }
    $productlist = json_decode(file_get_contents('product.json'), true);
    $productlist[$product['code_product']] = intval($text);
    file_put_contents('product.json', json_encode($productlist));
    step("home", $from_id);
    sendmessage($from_id, "✅ قیمت با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');
} elseif ($text == "💰 تنظیمات فروشگاه") {
    sendmessage($from_id, "📌 یک گزینه را انتخاب کنید.", $keyboardprice, 'HTML');
} elseif ($text == "⚙️ وضعیت قابلیت ها") {
    $status_custom = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['show_product']];
    $status_note = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['active_step_note']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $status_custom, 'callback_data' => "editstsuts-statusvolume-{$setting['show_product']}"],
                ['text' => "🛍 فروش  حجم دلخواه", 'callback_data' => "statuscustomvolume"],
            ],
            [
                ['text' => $status_note, 'callback_data' => "editstsuts-statusnote-{$setting['active_step_note']}"],
                ['text' => "✏️ یادداشت ", 'callback_data' => "statusnote"],
            ]
        ]
    ]);
    sendmessage($from_id, "در این بخش می توانید قابلیت های زیر را خاموش یا روشن کنید", $Bot_Status, 'HTML');
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusvolume") {
        if ($value == false) {
            $valuenew = true;
        } else {
            $valuenew = false;
        }
        $setting['show_product'] = $valuenew;
        update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    } elseif ($type == "statusnote") {
        if ($value == false) {
            $valuenew = true;
        } else {
            $valuenew = false;
        }
        $setting['active_step_note'] = $valuenew;
        update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    }
    $dataBase = select("botsaz", "*", "bot_token", $ApiToken, "select");
    $setting = json_decode($dataBase['setting'], true);
    $status_custom = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['show_product']];
    $status_note = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['active_step_note']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $status_custom, 'callback_data' => "editstsuts-statusvolume-{$setting['show_product']}"],
                ['text' => "🛍 فروش  حجم دلخواه", 'callback_data' => "statuscustomvolume"],
            ],
            [
                ['text' => $status_note, 'callback_data' => "editstsuts-statusnote-{$setting['active_step_note']}"],
                ['text' => "✏️ یادداشت ", 'callback_data' => "statusnote"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "در این بخش می توانید قابلیت های زیر را خاموش یا روشن کنید", $Bot_Status);
} elseif ($text == "📝 تنظیم متون") {
    sendmessage($from_id, "📌 برای تغییر متن یکی از گزینه های زیر را انتخاب نمایید", $keyboard_change_price, 'HTML');
} elseif ($text == "💎 متن کارت") {
    sendmessage($from_id, "📌 جهت تنظیم متن شماره کارت متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $setting['cart_info'], $backadmin, 'HTML');
    step("getcartinfo", $from_id);
} elseif ($user['step'] == "getcartinfo") {
    sendmessage($from_id, "✅ توضیحات با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $setting['cart_info'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    step("home", $from_id);
} elseif ($text == "🛍 دکمه خرید") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['buy'], $backadmin, 'HTML');
    step("gettext_buy", $from_id);
} elseif ($user['step'] == "gettext_buy") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['buy'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "🔑 دکمه تست") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['test'], $backadmin, 'HTML');
    step("gettext_test", $from_id);
} elseif ($user['step'] == "gettext_test") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['test'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "🛒 دکمه سرویس های من") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['my_service'], $backadmin, 'HTML');
    step("gettext_my_service", $from_id);
} elseif ($user['step'] == "gettext_my_service") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['my_service'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "👤 دکمه حساب کاربری") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['wallet'], $backadmin, 'HTML');
    step("gettext_wallet", $from_id);
} elseif ($user['step'] == "gettext_wallet") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['wallet'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "☎️ متن دکمه پشتیبانی") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['support'], $backadmin, 'HTML');
    step("gettext_support", $from_id);
} elseif ($user['step'] == "gettext_support") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['support'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "💸 متن مرحله افزایش موجودی") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['text_account']['add_balance'], $backadmin, 'HTML');
    step("gettext_add_balance", $from_id);
} elseif ($user['step'] == "gettext_add_balance") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['text_account']['add_balance'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "📣 جوین اجباری") {
    sendmessage($from_id, "📌 کانال خود را جهت تنظیم جوین اجباری ارسال کنید
⚠️ ربات باید ادمین کانال باشد در غیراینصورت این قابلیت فعال نخواهد شد
⚠️ نام کاربری کانال باید بدون @ ارسال شود", $backadmin, 'HTML');
    step("get_channel_id", $from_id);
} elseif ($user['step'] == "get_channel_id") {
    sendmessage($from_id, "✅ کانال با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
    $setting['channel'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    step("home", $from_id);
} elseif ($text == "✏️ تنظیم نام محصول") {
    if (!is_file('product_name.json')) {
        file_put_contents('product_name.json', "{}");
    }
    $product = [];
    $getdataproduct = mysqli_query($connect, "SELECT * FROM product WHERE agent = '{$userbot['agent']}'");
    while ($row = mysqli_fetch_assoc($getdataproduct)) {
        $panel = select("marzban_panel", "*", "name_panel", $row['Location'], "select");
        if (in_array($panel['name_panel'], $hide_panel))
            continue;
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_product['keyboard'][] = [
        ['text' => "بازگشت به منوی ادمین"],
    ];
    $json_list_product_list_admin = json_encode($list_product);
    sendmessage($from_id, "از لیست زیر محصولی که می خواهید نام تنظیم نمایید را انتخاب کنید", $json_list_product_list_admin, 'HTML');
    step("get_product_for_edit_name", $from_id);
} elseif ($user['step'] == "get_product_for_edit_name") {
    $product = select("product", "*", "name_product", $text, "select");
    if ($product == false) {
        sendmessage($from_id, "❌ محصول انتخابی وجود ندارد.", null, 'HTML');
        return;
    }
    savedata("clear", "code_product", $product['code_product']);
    step("get_new_name", $from_id);
    sendmessage($from_id, "📌  نام خود را ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "get_new_name") {
    $userdata = json_decode($user['Processing_value'], true);
    $product = select("product", "*", "code_product", $userdata['code_product'], "select");
    $productlist = json_decode(file_get_contents('product_name.json'), true);
    $productlist[$product['code_product']] = $text;
    file_put_contents('product_name.json', json_encode($productlist));
    step("home", $from_id);
    sendmessage($from_id, "✅ نام با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');

// ─── User management from reseller search ───────────────────────────────────

} elseif (preg_match('/resellerbanuser_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $targetuser = select("user", "*", "id", $id_user, "select");
    if (!$targetuser || $targetuser['bottype'] !== $ApiToken) return;
    if ($targetuser['User_Status'] == "block") {
        sendmessage($from_id, "❌ این کاربر از قبل مسدود است.", null, 'HTML');
        return;
    }
    $confirmkbd = json_encode(['inline_keyboard' => [
        [['text' => "✅ تایید مسدودی", 'callback_data' => "resellerconfirmban_$id_user"]],
        [['text' => "❌ انصراف", 'callback_data' => "none"]],
    ]]);
    sendmessage($from_id, "⚠️ آیا از مسدود کردن کاربر <a href=\"tg://user?id=$id_user\">$id_user</a> مطمئن هستید؟", $confirmkbd, 'HTML');

} elseif (preg_match('/resellerconfirmban_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $targetuser = select("user", "*", "id", $id_user, "select");
    if (!$targetuser || $targetuser['bottype'] !== $ApiToken) return;
    update("user", "User_Status", "block", "id", $id_user);
    sendmessage($from_id, "✅ کاربر با موفقیت مسدود شد.", $keyboardadmin, 'HTML');
    sendmessage($id_user, "⛔️ حساب کاربری شما در این ربات مسدود شده است.", null, 'HTML');

} elseif (preg_match('/resellerunbanuser_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $targetuser = select("user", "*", "id", $id_user, "select");
    if (!$targetuser || $targetuser['bottype'] !== $ApiToken) return;
    if ($targetuser['User_Status'] != "block") {
        sendmessage($from_id, "❌ این کاربر مسدود نیست.", null, 'HTML');
        return;
    }
    update("user", "User_Status", "Active", "id", $id_user);
    update("user", "description_blocking", " ", "id", $id_user);
    sendmessage($from_id, "✅ کاربر با موفقیت از مسدودی خارج شد.", $keyboardadmin, 'HTML');
    sendmessage($id_user, "✳️ حساب کاربری شما از مسدودی خارج شد. اکنون می‌توانید از ربات استفاده کنید.", null, 'HTML');

} elseif (preg_match('/resellersetdiscount_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $targetuser = select("user", "*", "id", $id_user, "select");
    if (!$targetuser || $targetuser['bottype'] !== $ApiToken) return;
    update("user", "Processing_value", $id_user, "id", $from_id);
    $botinfo = select("botsaz", "*", "bot_token", $ApiToken, "select");
    $userbotbalance = select("user", "*", "id", $botinfo['id_user'], "select");
    $minPrice = min($setting['minpricevolume'], $setting['minpricetime']) > 0
        ? "حداقل قیمت هر گیگابایت: {$setting['minpricevolume']} تومان / هر روز: {$setting['minpricetime']} تومان\n\n"
        : "";
    sendmessage($from_id, "💸 درصد تخفیف فعلی کاربر: <b>{$targetuser['pricediscount']}%</b>\n\n{$minPrice}📌 درصد تخفیف جدید را وارد کنید (عدد بین ۰ تا ۱۰۰):", $backadmin, 'HTML');
    step('resellergetdiscount', $from_id);

} elseif ($user['step'] == "resellergetdiscount") {
    $id_user = $user['Processing_value'];
    $targetuser = select("user", "*", "id", $id_user, "select");
    if (!$targetuser || $targetuser['bottype'] !== $ApiToken) {
        step('home', $from_id);
        return;
    }
    if (!ctype_digit($text) || intval($text) < 0 || intval($text) > 100) {
        sendmessage($from_id, "❌ عدد نامعتبر است. عدد بین ۰ تا ۱۰۰ وارد کنید.", $backadmin, 'HTML');
        return;
    }
    $discount = intval($text);
    if ($discount > 0) {
        $violatingProduct = null;
        // Check fixed products
        $productlist_prices = json_decode(file_get_contents('product.json'), true) ?: [];
        $stmt2 = $pdo->prepare("SELECT * FROM product WHERE agent = :agent");
        $stmt2->bindParam(':agent', $userbot['agent']);
        $stmt2->execute();
        $hasProducts = false;
        while ($prod = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $hasProducts = true;
            $sellPrice = isset($productlist_prices[$prod['code_product']]) ? floatval($productlist_prices[$prod['code_product']]) : floatval($prod['price_product']);
            $minPrice = floatval($prod['price_product']);
            $discountedPrice = $sellPrice - ($sellPrice * $discount / 100);
            if ($discountedPrice < $minPrice) {
                $maxAllowedForProd = $sellPrice > 0 ? floor((1 - $minPrice / $sellPrice) * 100) : 0;
                if ($violatingProduct === null || $maxAllowedForProd < $violatingProduct['max']) {
                    $violatingProduct = ['name' => $prod['name_product'], 'max' => $maxAllowedForProd];
                }
            }
        }
        // Check per-GB/per-day pricing (used when no fixed products, or alongside them)
        $priceVol = floatval($setting['pricevolume']);
        $minPriceVol = floatval($setting['minpricevolume']);
        $priceTime = floatval($setting['pricetime']);
        $minPriceTime = floatval($setting['minpricetime']);
        if ($priceVol > 0 && $minPriceVol > 0) {
            $effectiveVol = $priceVol - ($priceVol * $discount / 100);
            if ($effectiveVol < $minPriceVol) {
                $maxVol = floor((1 - $minPriceVol / $priceVol) * 100);
                if ($violatingProduct === null || $maxVol < $violatingProduct['max']) {
                    $violatingProduct = ['name' => 'قیمت هر گیگابایت', 'max' => $maxVol];
                }
            }
        }
        if ($priceTime > 0 && $minPriceTime > 0) {
            $effectiveTime = $priceTime - ($priceTime * $discount / 100);
            if ($effectiveTime < $minPriceTime) {
                $maxTime = floor((1 - $minPriceTime / $priceTime) * 100);
                if ($violatingProduct === null || $maxTime < $violatingProduct['max']) {
                    $violatingProduct = ['name' => 'قیمت هر روز', 'max' => $maxTime];
                }
            }
        }
        if ($violatingProduct !== null) {
            sendmessage($from_id, "❌ این درصد تخفیف باعث می‌شود قیمت نهایی از حداقل قیمت پایه کمتر شود.\n\n({$violatingProduct['name']})\nحداکثر درصد تخفیف مجاز: <b>{$violatingProduct['max']}%</b>", $backadmin, 'HTML');
            return;
        }
    }
    update("user", "pricediscount", $discount, "id", $id_user);
    sendmessage($from_id, "✅ درصد تخفیف کاربر به <b>{$discount}%</b> تنظیم شد.", $keyboardadmin, 'HTML');
    step('home', $from_id);

} elseif (preg_match('/resellervieworders_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $targetuser = select("user", "*", "id", $id_user, "select");
    if (!$targetuser || $targetuser['bottype'] !== $ApiToken) return;
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = 0;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :uid AND bottype = :bottype ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute([':uid' => $id_user, ':bottype' => $ApiToken]);
    $keyboardlists = ['inline_keyboard' => []];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "none"],
        ['text' => "وضعیت", 'callback_data' => "none"],
        ['text' => "نام کاربری", 'callback_data' => "none"],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "مشاهده", 'callback_data' => "manageinvoicereseller_" . $row['id_invoice']],
            ['text' => $row['Status'], 'callback_data' => "none"],
            ['text' => $row['username'], 'callback_data' => "none"],
        ];
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "⬅️ قبلی", 'callback_data' => "resellerordersprev_{$id_user}"],
        ['text' => "➡️ بعدی", 'callback_data' => "resellernext_{$id_user}"],
    ];
    update("user", "Processing_value", $id_user, "id", $from_id);
    sendmessage($from_id, "📦 سفارشات کاربر <a href=\"tg://user?id=$id_user\">$id_user</a>:", json_encode($keyboardlists), 'HTML');

} elseif (preg_match('/resellernext_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $page = intval($user['pagenumber']) + 1;
    update("user", "pagenumber", $page, "id", $from_id);
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :uid AND bottype = :bottype ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute([':uid' => $id_user, ':bottype' => $ApiToken]);
    if ($stmt->rowCount() == 0) {
        sendmessage($from_id, "❌ صفحه بیشتری وجود ندارد.", null, 'HTML');
        return;
    }
    $keyboardlists = ['inline_keyboard' => []];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "none"],
        ['text' => "وضعیت", 'callback_data' => "none"],
        ['text' => "نام کاربری", 'callback_data' => "none"],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "مشاهده", 'callback_data' => "manageinvoicereseller_" . $row['id_invoice']],
            ['text' => $row['Status'], 'callback_data' => "none"],
            ['text' => $row['username'], 'callback_data' => "none"],
        ];
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "⬅️ قبلی", 'callback_data' => "resellerordersprev_{$id_user}"],
        ['text' => "➡️ بعدی", 'callback_data' => "resellernext_{$id_user}"],
    ];
    Editmessagetext($from_id, $message_id, "📦 سفارشات کاربر (صفحه $page):", json_encode($keyboardlists));

} elseif (preg_match('/resellerordersprev_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $page = max(1, intval($user['pagenumber']) - 1);
    update("user", "pagenumber", $page, "id", $from_id);
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :uid AND bottype = :bottype ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute([':uid' => $id_user, ':bottype' => $ApiToken]);
    $keyboardlists = ['inline_keyboard' => []];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "none"],
        ['text' => "وضعیت", 'callback_data' => "none"],
        ['text' => "نام کاربری", 'callback_data' => "none"],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "مشاهده", 'callback_data' => "manageinvoicereseller_" . $row['id_invoice']],
            ['text' => $row['Status'], 'callback_data' => "none"],
            ['text' => $row['username'], 'callback_data' => "none"],
        ];
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "⬅️ قبلی", 'callback_data' => "resellerordersprev_{$id_user}"],
        ['text' => "➡️ بعدی", 'callback_data' => "resellernext_{$id_user}"],
    ];
    Editmessagetext($from_id, $message_id, "📦 سفارشات کاربر (صفحه $page):", json_encode($keyboardlists));
}
