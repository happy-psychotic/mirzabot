<?php
// Reseller (agent n/n2) panel — scoped strictly to their own services and level-1 sub-users.
// Triggered from index.php when $user['agent'] is 'n' or 'n2'.

if (!in_array($user['agent'], ['n', 'n2']))
    return;

// ── helpers ───────────────────────────────────────────────────────────────────

function ap_keyboard_main(): string
{
    return json_encode([
        'keyboard' => [
            [['text' => "🔍 جستجو سرویس"], ['text' => "🔍 جستجو کاربر"]],
            [['text' => "🏠 بازگشت به منوی اصلی"]],
        ],
        'resize_keyboard' => true,
    ]);
}

function ap_back_to_panel(): string
{
    return json_encode([
        'inline_keyboard' => [
            [['text' => "↩️ بازگشت به پنل نماینده", 'callback_data' => "ap_panel"]],
        ],
    ]);
}

// Verify an invoice belongs to this reseller.
function ap_own_invoice(string $id_invoice): array|false
{
    global $pdo, $from_id;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND id_user = :uid LIMIT 1");
    $stmt->execute([':id' => $id_invoice, ':uid' => $from_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

// Verify a user is a level-1 sub-user of this reseller.
function ap_own_subuser(string $target_id): array|false
{
    global $pdo, $from_id;
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = :id AND affiliates = :agent LIMIT 1");
    $stmt->execute([':id' => $target_id, ':agent' => $from_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

// Verify an invoice belongs to a specific sub-user of this reseller.
function ap_subuser_invoice(string $id_invoice, string $sub_id): array|false
{
    global $pdo, $from_id;
    $stmt = $pdo->prepare(
        "SELECT i.* FROM invoice i
         JOIN user u ON u.id = i.id_user
         WHERE i.id_invoice = :id AND i.id_user = :uid AND u.affiliates = :agent LIMIT 1"
    );
    $stmt->execute([':id' => $id_invoice, ':uid' => $sub_id, ':agent' => $from_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

// Build service info text + action keyboard for a reseller-owned invoice.
function ap_service_info_keyboard(array $invoice, array $dataUser, string $back_cb = "ap_panel"): array
{
    $status_map = [
        'active'    => '✅ فعال',
        'limited'   => '⚠️ محدود شده',
        'disabled'  => '❌ غیرفعال',
        'expired'   => '⏰ منقضی',
        'on_hold'   => '⏸ در انتظار اتصال',
        'Unknown'   => '❓ نامشخص',
        'deactivev' => '❌ غیرفعال',
    ];
    $status_label = $status_map[$dataUser['status']] ?? $dataUser['status'];

    $expire   = $dataUser['expire'] ? jdate('Y/m/d', $dataUser['expire']) : 'نامحدود';
    $timeDiff = $dataUser['expire'] ? floor(($dataUser['expire'] - time()) / 86400) : '∞';
    $dayLabel = is_numeric($timeDiff) ? "{$timeDiff} روز" : $timeDiff;

    $limit     = $dataUser['data_limit']   ? formatBytes($dataUser['data_limit'])   : 'نامحدود';
    $used      = $dataUser['used_traffic'] ? formatBytes($dataUser['used_traffic']) : '0';
    $remaining = $dataUser['data_limit']
        ? formatBytes(max(0, $dataUser['data_limit'] - $dataUser['used_traffic']))
        : 'نامحدود';
    $sub = $dataUser['subscription_url'] ?? '';

    $displayName = (!empty($invoice['note'])) ? " ({$invoice['note']})" : '';

    $text = "📋 <b>اطلاعات سرویس</b>{$displayName}\n\n" .
        "🔑 نام کاربری: <code>{$invoice['username']}</code>\n" .
        "📍 موقعیت: {$invoice['Service_location']}\n" .
        "📦 محصول: {$invoice['name_product']}\n" .
        "🔄 وضعیت: {$status_label}\n\n" .
        "🔋 حجم کل: {$limit}\n" .
        "📥 مصرف شده: {$used}\n" .
        "💢 باقی مانده: {$remaining}\n" .
        "📅 انقضا: {$expire} ({$dayLabel})\n\n" .
        "🔗 لینک اشتراک:\n<code>{$sub}</code>";

    $toggle_text = ($dataUser['status'] === 'active')
        ? '❌ خاموش کردن اکانت'
        : '💡 روشن کردن اکانت';
    $id = $invoice['id_invoice'];

    $keyboard = [
        'inline_keyboard' => [
            [['text' => "♻️ بروزرسانی اطلاعات",  'callback_data' => "ap_refresh_{$id}"]],
            [['text' => "⚙️ دریافت همه کانفیگ ها", 'callback_data' => "ap_allconfigs_{$id}"]],
            [['text' => "🔄 تغییر لینک اشتراک",   'callback_data' => "ap_resetlink_{$id}"]],
            [['text' => "✏️ تغییر نام سرویس",      'callback_data' => "ap_rename_{$id}"]],
            [['text' => $toggle_text,               'callback_data' => "ap_toggle_{$id}"]],
            [['text' => "⏳ تمدید سرویس",            'callback_data' => "ap_extend_{$id}"]],
            [['text' => "🔀 انتقال سرویس",           'callback_data' => "ap_transfer_{$id}"]],
            [['text' => "↩️ بازگشت", 'callback_data' => $back_cb]],
        ],
    ];

    return [$text, json_encode($keyboard)];
}

// Send all configs as text + QR photo (same as rest of bot).
function ap_send_all_configs(int $chat_id, array $links, string $back_cb): void
{
    global $from_id;
    $lastIndex = count($links) - 1;
    foreach ($links as $i => $link) {
        $urlimage = runtimeTempPath("ap_qr_{$from_id}_{$i}", '.png');
        $qrCode   = createqrcode($link);
        file_put_contents($urlimage, $qrCode->getString());
        addBackgroundImage($urlimage, $qrCode, 'images.jpg');
        $caption = formatConfigLinksForDelivery([$link]);
        $replyKb = null;
        if ($i === $lastIndex) {
            $replyKb = json_encode([
                'inline_keyboard' => [
                    [['text' => "↩️ بازگشت به سرویس", 'callback_data' => $back_cb]],
                ],
            ]);
        }
        telegram('sendphoto', [
            'chat_id'      => $chat_id,
            'photo'        => new CURLFile($urlimage),
            'caption'      => $caption,
            'parse_mode'   => "HTML",
            'reply_markup' => $replyKb,
        ]);
        unlink($urlimage);
    }
}

// Extract panel username from a config link fragment (same logic as admin.php).
function ap_username_from_config_link(string $text): string
{
    if (preg_match('~^(?:vless|vmess|ss|trojan)://[^#]+#(.+)$~i', trim($text), $m)) {
        return explode('-', urldecode($m[1]))[0];
    }
    return trim($text);
}

// ── entry ─────────────────────────────────────────────────────────────────────

$ap_triggers = [$textbotlang['Admin']['textpaneladmin'], "panel", "/panel"];
$is_ap_entry = in_array($text, $ap_triggers) || $datain == "ap_panel";

// ── panel home ────────────────────────────────────────────────────────────────

if ($is_ap_entry) {
    step('home', $from_id);
    if ($datain == "ap_panel") {
        Editmessagetext($from_id, $message_id, "👨‍💼 <b>پنل نماینده</b>\n\nیک گزینه را انتخاب کنید:", ap_keyboard_main());
    } else {
        sendmessage($from_id, "👨‍💼 <b>پنل نماینده</b>\n\nیک گزینه را انتخاب کنید:", ap_keyboard_main(), 'HTML');
    }

// ── search service ─────────────────────────────────────────────────────────────

} elseif ($text == "🔍 جستجو سرویس" || $datain == "ap_searchservice") {
    sendmessage($from_id,
        "🔍 نام کاربری سرویس، شماره سفارش یا لینک کانفیگ را ارسال کنید:",
        ap_back_to_panel(), 'HTML');
    step('ap_get_service', $from_id);

} elseif ($user['step'] == "ap_get_service") {
    step('home', $from_id);
    $q = ap_username_from_config_link($text);

    $stmt = $pdo->prepare(
        "SELECT * FROM invoice WHERE id_user = :uid
         AND (username LIKE :q OR id_invoice LIKE :q2 OR note LIKE :q3)
         ORDER BY time_sell DESC LIMIT 10"
    );
    $stmt->execute([':uid' => $from_id, ':q' => "%{$q}%", ':q2' => "%{$q}%", ':q3' => "%{$q}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        sendmessage($from_id, "❌ سرویسی یافت نشد.", ap_back_to_panel(), 'HTML');
        return;
    }
    if (count($rows) > 1) {
        $kb = ['inline_keyboard' => []];
        foreach ($rows as $r) {
            $label = $r['username'];
            if (!empty($r['note'])) $label .= " ({$r['note']})";
            $label .= " — {$r['Status']}";
            $kb['inline_keyboard'][] = [
                ['text' => "📋 {$label}", 'callback_data' => "ap_refresh_{$r['id_invoice']}"],
            ];
        }
        $kb['inline_keyboard'][] = [['text' => "↩️ بازگشت", 'callback_data' => "ap_panel"]];
        sendmessage($from_id, "⚠️ چند سرویس یافت شد. یکی را انتخاب کنید:", json_encode($kb), 'HTML');
        return;
    }
    $invoice  = $rows[0];
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful") {
        sendmessage($from_id, "❌ اتصال به پنل برقرار نشد.", ap_back_to_panel(), 'HTML');
        return;
    }
    [$infoText, $infoKb] = ap_service_info_keyboard($invoice, $dataUser);
    sendmessage($from_id, $infoText, $infoKb, 'HTML');

// ── refresh / view service ────────────────────────────────────────────────────

} elseif (preg_match('/^ap_refresh_(\w+)$/', $datain, $m)) {
    $invoice = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ اتصال به پنل برقرار نشد.", ap_back_to_panel());
        return;
    }
    [$infoText, $infoKb] = ap_service_info_keyboard($invoice, $dataUser);
    Editmessagetext($from_id, $message_id, $infoText, $infoKb);

// ── show all configs (text + QR) ──────────────────────────────────────────────

} elseif (preg_match('/^ap_allconfigs_(\w+)$/', $datain, $m)) {
    $invoice = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ اتصال به پنل برقرار نشد.", ap_back_to_panel());
        return;
    }
    if (empty($dataUser['links'])) {
        Editmessagetext($from_id, $message_id, "❌ هیچ کانفیگی یافت نشد.", ap_back_to_panel());
        return;
    }
    deletemessage($from_id, $message_id);
    ap_send_all_configs($from_id, $dataUser['links'], "ap_refresh_{$invoice['id_invoice']}");

// ── change link (revoke_sub) ──────────────────────────────────────────────────

} elseif (preg_match('/^ap_resetlink_(\w+)$/', $datain, $m)) {
    $invoice  = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ اتصال به پنل برقرار نشد.", ap_back_to_panel());
        return;
    }
    if (in_array($dataUser['status'], ['disabled', 'on_hold'])) {
        Editmessagetext($from_id, $message_id, "❌ سرویس غیرفعال است. امکان تغییر لینک وجود ندارد.", ap_back_to_panel());
        return;
    }
    $confirmKb = json_encode([
        'inline_keyboard' => [
            [['text' => "✅ تایید تغییر لینک", 'callback_data' => "ap_resetlink_confirm_{$invoice['id_invoice']}"]],
            [['text' => "↩️ انصراف",            'callback_data' => "ap_refresh_{$invoice['id_invoice']}"]],
        ],
    ]);
    Editmessagetext($from_id, $message_id,
        "⚠️ با تایید، لینک اشتراک و تمام کانفیگ‌های سرویس <b>{$invoice['username']}</b> تغییر می‌کنند.\nلینک‌های قدیمی دیگر کار نخواهند کرد.",
        $confirmKb);

} elseif (preg_match('/^ap_resetlink_confirm_(\w+)$/', $datain, $m)) {
    $invoice = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $result = $ManagePanel->Revoke_sub($invoice['Service_location'], $invoice['username']);
    if ($result['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ تغییر لینک با خطا مواجه شد.", ap_back_to_panel());
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    [$infoText, $infoKb] = ap_service_info_keyboard($invoice, $dataUser);
    // Show new configs if panel has config enabled
    if ($marzban_list_get['config'] == "onconfig" && !empty($result['configs'])) {
        Editmessagetext($from_id, $message_id, "✅ لینک با موفقیت تغییر کرد.\n\n" . $infoText, $infoKb);
        ap_send_all_configs($from_id, $result['configs'], "ap_refresh_{$invoice['id_invoice']}");
    } else {
        Editmessagetext($from_id, $message_id, "✅ لینک با موفقیت تغییر کرد.\n\n" . $infoText, $infoKb);
    }

// ── rename service (note field) ───────────────────────────────────────────────

} elseif (preg_match('/^ap_rename_(\w+)$/', $datain, $m)) {
    $invoice = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    update("user", "Processing_value_one", $invoice['id_invoice'], "id", $from_id);
    Editmessagetext($from_id, $message_id,
        "✏️ نام نمایشی جدید برای سرویس <b>{$invoice['username']}</b> را ارسال کنید:\n(حداکثر 100 کاراکتر)",
        ap_back_to_panel());
    step('ap_get_rename', $from_id);

} elseif ($user['step'] == "ap_get_rename") {
    $id_invoice = $user['Processing_value_one'];
    $invoice    = ap_own_invoice($id_invoice);
    if (!$invoice) {
        sendmessage($from_id, "❌ سرویس یافت نشد.", ap_back_to_panel(), 'HTML');
        step('home', $from_id);
        return;
    }
    if (mb_strlen(trim($text)) < 1 || mb_strlen(trim($text)) > 100) {
        sendmessage($from_id, "❌ نام باید بین 1 تا 100 کاراکتر باشد.", ap_back_to_panel(), 'HTML');
        return;
    }
    update("invoice", "note", trim($text), "id_invoice", $id_invoice);
    step('home', $from_id);
    $invoice['note'] = trim($text);
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    [$infoText, $infoKb] = ap_service_info_keyboard($invoice, $dataUser);
    sendmessage($from_id, "✅ نام نمایشی ذخیره شد.\n\n" . $infoText, $infoKb, 'HTML');

// ── toggle enable/disable ─────────────────────────────────────────────────────

} elseif (preg_match('/^ap_toggle_(\w+)$/', $datain, $m)) {
    $invoice  = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ اتصال به پنل برقرار نشد.", ap_back_to_panel());
        return;
    }
    if ($dataUser['status'] == "on_hold") {
        Editmessagetext($from_id, $message_id,
            "❌ کاربر هنوز به کانفیگ متصل نشده است. امکان تغییر وضعیت وجود ندارد.", ap_back_to_panel());
        return;
    }
    if ($dataUser['status'] === 'active') {
        $confirmKb = json_encode(['inline_keyboard' => [
            [['text' => "✅ تایید خاموش کردن", 'callback_data' => "ap_toggle_confirm_{$invoice['id_invoice']}"]],
            [['text' => "↩️ انصراف",            'callback_data' => "ap_refresh_{$invoice['id_invoice']}"]],
        ]]);
        Editmessagetext($from_id, $message_id,
            "⚠️ آیا می‌خواهید اکانت <b>{$invoice['username']}</b> را خاموش کنید؟", $confirmKb);
    } else {
        $confirmKb = json_encode(['inline_keyboard' => [
            [['text' => "✅ تایید روشن کردن", 'callback_data' => "ap_toggle_confirm_{$invoice['id_invoice']}"]],
            [['text' => "↩️ انصراف",           'callback_data' => "ap_refresh_{$invoice['id_invoice']}"]],
        ]]);
        Editmessagetext($from_id, $message_id,
            "⚠️ آیا می‌خواهید اکانت <b>{$invoice['username']}</b> را روشن کنید؟", $confirmKb);
    }

} elseif (preg_match('/^ap_toggle_confirm_(\w+)$/', $datain, $m)) {
    $invoice = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $result = $ManagePanel->Change_status($invoice['username'], $invoice['Service_location']);
    if ($result['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ تغییر وضعیت با خطا مواجه شد.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    $newStatus = ($dataUser['status'] == 'active') ? 'active' : 'disabled';
    update("invoice", "Status", $newStatus, "id_invoice", $invoice['id_invoice']);
    [$infoText, $infoKb] = ap_service_info_keyboard($invoice, $dataUser);
    Editmessagetext($from_id, $message_id, "✅ وضعیت سرویس تغییر کرد.\n\n" . $infoText, $infoKb);

// ── extend service ────────────────────────────────────────────────────────────

} elseif (preg_match('/^ap_extend_(\w+)$/', $datain, $m)) {
    $invoice = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $panel       = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $pricePerGig = intval(json_decode($panel['pricecustomvolume'] ?? '{}', true)[$user['agent']] ?? 0);
    $pricePerDay = intval(json_decode($panel['pricecustomtime']   ?? '{}', true)[$user['agent']] ?? 0);
    update("user", "Processing_value_one", $invoice['id_invoice'], "id", $from_id);
    Editmessagetext($from_id, $message_id,
        "⏳ <b>تمدید سرویس</b>: <code>{$invoice['username']}</code>\n\n" .
        "💰 نرخ: هر گیگ <b>" . number_format($pricePerGig) . "</b> — هر روز <b>" . number_format($pricePerDay) . "</b> تومان\n\n" .
        "📦 حجم تمدید (گیگابایت) را ارسال کنید (0 = بدون تغییر):",
        ap_back_to_panel());
    step('ap_extend_vol', $from_id);

} elseif ($user['step'] == "ap_extend_vol") {
    if (!ctype_digit(trim($text)) || intval($text) < 0) {
        sendmessage($from_id, "❌ عدد معتبر وارد کنید (0 یا بیشتر).", ap_back_to_panel(), 'HTML');
        return;
    }
    update("user", "Processing_value_tow", trim($text), "id", $from_id);
    sendmessage($from_id, "⌛️ زمان تمدید (روز) را ارسال کنید (0 = بدون تغییر):", ap_back_to_panel(), 'HTML');
    step('ap_extend_days', $from_id);

} elseif ($user['step'] == "ap_extend_days") {
    if (!ctype_digit(trim($text)) || intval($text) < 0) {
        sendmessage($from_id, "❌ عدد معتبر وارد کنید (0 یا بیشتر).", ap_back_to_panel(), 'HTML');
        return;
    }
    $days       = intval($text);
    $vol        = intval($user['Processing_value_tow']);
    $id_invoice = $user['Processing_value_one'];

    if ($vol == 0 && $days == 0) {
        sendmessage($from_id, "❌ حداقل یکی از مقادیر حجم یا زمان باید بیشتر از صفر باشد.", ap_back_to_panel(), 'HTML');
        return;
    }
    $invoice = ap_own_invoice($id_invoice);
    if (!$invoice) {
        sendmessage($from_id, "❌ سرویس یافت نشد.", ap_back_to_panel(), 'HTML');
        step('home', $from_id);
        return;
    }
    $panel       = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $pricePerGig = intval(json_decode($panel['pricecustomvolume'] ?? '{}', true)[$user['agent']] ?? 0);
    $pricePerDay = intval(json_decode($panel['pricecustomtime']   ?? '{}', true)[$user['agent']] ?? 0);
    $totalPrice  = ($vol * $pricePerGig) + ($days * $pricePerDay);

    $agentBalance = intval(select("user", "Balance", "id", $from_id, "select")['Balance']);
    if ($agentBalance < $totalPrice) {
        sendmessage($from_id,
            "❌ موجودی کافی نیست.\n💰 هزینه: <b>" . number_format($totalPrice) . "</b> تومان\n💳 موجودی شما: <b>" . number_format($agentBalance) . "</b> تومان",
            ap_back_to_panel(), 'HTML');
        step('home', $from_id);
        return;
    }

    $volLabel  = $vol  > 0 ? "{$vol} گیگابایت" : "بدون تغییر";
    $daysLabel = $days > 0 ? "{$days} روز"      : "بدون تغییر";
    $confirmKb = json_encode([
        'inline_keyboard' => [
            [['text' => "✅ تایید و تمدید", 'callback_data' => "ap_extend_ok_{$id_invoice}_{$vol}_{$days}"]],
            [['text' => "↩️ انصراف",         'callback_data' => "ap_refresh_{$id_invoice}"]],
        ],
    ]);
    sendmessage($from_id,
        "📋 <b>فاکتور تمدید</b>\n\n" .
        "🔑 سرویس: <code>{$invoice['username']}</code>\n" .
        "📦 حجم: {$volLabel}\n" .
        "⌛️ زمان: {$daysLabel}\n" .
        "💰 هزینه: <b>" . number_format($totalPrice) . "</b> تومان\n\n" .
        "با تایید، مبلغ از کیف پول شما کسر می‌شود.",
        $confirmKb, 'HTML');
    step('home', $from_id);

} elseif (preg_match('/^ap_extend_ok_(\w+)_(\d+)_(\d+)$/', $datain, $m)) {
    $id_invoice = $m[1];
    $vol        = intval($m[2]);
    $days       = intval($m[3]);
    $invoice    = ap_own_invoice($id_invoice);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    $panel       = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $pricePerGig = intval(json_decode($panel['pricecustomvolume'] ?? '{}', true)[$user['agent']] ?? 0);
    $pricePerDay = intval(json_decode($panel['pricecustomtime']   ?? '{}', true)[$user['agent']] ?? 0);
    $totalPrice  = ($vol * $pricePerGig) + ($days * $pricePerDay);

    $agentBalance = intval(select("user", "Balance", "id", $from_id, "select")['Balance']);
    if ($agentBalance < $totalPrice) {
        Editmessagetext($from_id, $message_id, "❌ موجودی ناکافی.", ap_back_to_panel());
        return;
    }
    if (!in_array($invoice['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        Editmessagetext($from_id, $message_id, "❌ وضعیت سرویس اجازه تمدید نمی‌دهد.", ap_back_to_panel());
        return;
    }
    $newVol  = $vol  > 0 ? $vol  : intval($invoice['Volume']);
    $newDays = $days > 0 ? $days : intval($invoice['Service_time']);

    $extend = $ManagePanel->extend(
        $panel['Methodextend'], $newVol, $newDays,
        $invoice['username'], 'custom_volume', $panel['code_panel']
    );
    if ($extend['status'] == false) {
        Editmessagetext($from_id, $message_id, "❌ خطا در تمدید سرویس. لطفاً دوباره تلاش کنید.", ap_back_to_panel());
        return;
    }

    update("user", "Balance", $agentBalance - $totalPrice, "id", $from_id);
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO service_other (id_user, username, value, type, time, price)
         VALUES (:uid, :uname, :val, 'extend_user', :time, :price)"
    );
    $stmt->execute([
        ':uid'   => $from_id,
        ':uname' => $invoice['username'],
        ':val'   => "{$newVol}_{$newDays}",
        ':time'  => date('Y/m/d H:i:s'),
        ':price' => $totalPrice,
    ]);
    if ($vol  > 0) update("invoice", "Volume",       $newVol,  "id_invoice", $id_invoice);
    if ($days > 0) update("invoice", "Service_time", $newDays, "id_invoice", $id_invoice);

    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    [$infoText, $infoKb] = ap_service_info_keyboard($invoice, $dataUser);
    Editmessagetext($from_id, $message_id,
        "✅ سرویس با موفقیت تمدید شد.\n💰 " . number_format($totalPrice) . " تومان از کیف پول کسر شد.\n\n" . $infoText,
        $infoKb);

// ── transfer service ──────────────────────────────────────────────────────────

} elseif (preg_match('/^ap_transfer_(\w+)$/', $datain, $m)) {
    $invoice = ap_own_invoice($m[1]);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد یا متعلق به شما نیست.", ap_back_to_panel());
        return;
    }
    update("user", "Processing_value_one", $invoice['id_invoice'], "id", $from_id);
    $keyboardTransfer = json_encode([
        'keyboard' => [
            [['text' => "👤 انتخاب از مخاطبین", 'request_user' => ['request_id' => 2, 'user_is_bot' => false]]],
            [['text' => "🏠 بازگشت به منوی اصلی"]],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ]);
    sendmessage($from_id,
        "🔀 انتقال سرویس <b>{$invoice['username']}</b>\n\nشناسه عددی یا یوزرنیم زیرمجموعه مقصد را ارسال کنید، یا از دکمه زیر انتخاب کنید:",
        $keyboardTransfer, 'HTML');
    step('ap_get_transfer_target', $from_id);

} elseif ($user['step'] == "ap_get_transfer_target") {
    $id_invoice = $user['Processing_value_one'];
    $invoice    = ap_own_invoice($id_invoice);
    if (!$invoice) {
        sendmessage($from_id, "❌ سرویس یافت نشد.", ap_back_to_panel(), 'HTML');
        step('home', $from_id);
        return;
    }

    // Resolve target: contact share, numeric ID, or @username
    if ($contact_id != 0) {
        $target_id = strval($contact_id);
    } elseif (isset($update['message']['user_shared']['user_id'])) {
        $target_id = strval($update['message']['user_shared']['user_id']);
    } elseif (str_starts_with(trim($text), '@')) {
        $uname = ltrim(trim($text), '@');
        $found = $pdo->prepare("SELECT id FROM user WHERE username = :u LIMIT 1");
        $found->execute([':u' => $uname]);
        $target_id = $found->fetchColumn() ?: null;
    } else {
        $target_id = trim($text);
    }

    if (!$target_id) {
        sendmessage($from_id, "❌ کاربر یافت نشد.", ap_back_to_panel(), 'HTML');
        return;
    }

    $subuser = ap_own_subuser($target_id);
    if (!$subuser) {
        sendmessage($from_id, "❌ این کاربر زیرمجموعه شما نیست.", ap_back_to_panel(), 'HTML');
        return;
    }
    if ($target_id == $invoice['id_user']) {
        sendmessage($from_id, "❌ سرویس از قبل متعلق به این کاربر است.", ap_back_to_panel(), 'HTML');
        return;
    }

    $targetUsername = $subuser['username'] ? "@{$subuser['username']}" : $target_id;
    $confirmKb = json_encode([
        'inline_keyboard' => [
            [['text' => "✅ تایید انتقال", 'callback_data' => "ap_transfer_ok_{$id_invoice}_{$target_id}"]],
            [['text' => "↩️ انصراف",       'callback_data' => "ap_refresh_{$id_invoice}"]],
        ],
    ]);
    sendmessage($from_id,
        "📋 انتقال سرویس <b>{$invoice['username']}</b> به کاربر <b>{$targetUsername}</b>\n\nتایید می‌کنید؟",
        $confirmKb, 'HTML');
    step('home', $from_id);

} elseif (preg_match('/^ap_transfer_ok_(\w+)_(\d+)$/', $datain, $m)) {
    $id_invoice = $m[1];
    $target_id  = $m[2];
    $invoice    = ap_own_invoice($id_invoice);
    $subuser    = ap_own_subuser($target_id);
    if (!$invoice || !$subuser) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یا کاربر مقصد معتبر نیست.", ap_back_to_panel());
        return;
    }
    update("invoice", "id_user", $target_id, "id_invoice", $id_invoice);
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO service_other (id_user, username, value, type, time, price)
         VALUES (:uid, :uname, :val, 'transfertouser', :time, '0')"
    );
    $stmt->execute([
        ':uid'   => $from_id,
        ':uname' => $invoice['username'],
        ':val'   => $target_id,
        ':time'  => date('Y/m/d H:i:s'),
    ]);
    $targetUsername = $subuser['username'] ? "@{$subuser['username']}" : $target_id;
    sendmessage($target_id,
        "✅ سرویس <code>{$invoice['username']}</code> از طرف نماینده به حساب شما منتقل شد.",
        null, 'HTML');
    Editmessagetext($from_id, $message_id,
        "✅ سرویس <b>{$invoice['username']}</b> به کاربر <b>{$targetUsername}</b> منتقل شد.",
        ap_back_to_panel());

// ── search user ───────────────────────────────────────────────────────────────

} elseif ($text == "🔍 جستجو کاربر" || $datain == "ap_searchuser") {
    $keyboardSearchUser = json_encode([
        'keyboard' => [
            [['text' => "👤 انتخاب از مخاطبین", 'request_user' => ['request_id' => 3, 'user_is_bot' => false]]],
            [['text' => "🏠 بازگشت به منوی اصلی"]],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ]);
    sendmessage($from_id,
        "🔍 شناسه عددی یا یوزرنیم زیرمجموعه را ارسال کنید:",
        $keyboardSearchUser, 'HTML');
    step('ap_get_user', $from_id);

} elseif ($user['step'] == "ap_get_user") {
    step('home', $from_id);

    if ($contact_id != 0) {
        $q = strval($contact_id);
    } elseif (isset($update['message']['user_shared']['user_id'])) {
        $q = strval($update['message']['user_shared']['user_id']);
    } else {
        $q = ltrim(trim($text), '@');
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM user WHERE affiliates = :agent AND (id = :q OR username = :q2) LIMIT 1"
    );
    $stmt->execute([':agent' => $from_id, ':q' => $q, ':q2' => $q]);
    $subuser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subuser) {
        sendmessage($from_id, "❌ کاربری با این مشخصات در زیرمجموعه‌های شما یافت نشد.", ap_back_to_panel(), 'HTML');
        return;
    }
    // Show user management keyboard
    ap_show_subuser($from_id, $subuser, false);

// ── sub-user management view ──────────────────────────────────────────────────

} elseif (preg_match('/^ap_userview_(\d+)$/', $datain, $m)) {
    $subuser = ap_own_subuser($m[1]);
    if (!$subuser) {
        Editmessagetext($from_id, $message_id, "❌ این کاربر زیرمجموعه شما نیست.", ap_back_to_panel());
        return;
    }
    ap_show_subuser($from_id, $subuser, true, $message_id);

// ── sub-user: set discount ────────────────────────────────────────────────────

} elseif (preg_match('/^ap_discount_(\d+)$/', $datain, $m)) {
    $subuser = ap_own_subuser($m[1]);
    if (!$subuser) {
        Editmessagetext($from_id, $message_id, "❌ این کاربر زیرمجموعه شما نیست.", ap_back_to_panel());
        return;
    }
    update("user", "Processing_value", $subuser['id'], "id", $from_id);
    Editmessagetext($from_id, $message_id,
        "🎁 درصد تخفیف برای کاربر <b>" . ($subuser['username'] ? "@{$subuser['username']}" : $subuser['id']) . "</b> را ارسال کنید (0 تا 100):\n\n⚠️ توجه: قیمت نهایی کاربر نمی‌تواند از قیمت پایه شما کمتر شود.",
        ap_back_to_panel());
    step('ap_get_discount', $from_id);

} elseif ($user['step'] == "ap_get_discount") {
    $sub_id  = $user['Processing_value'];
    $subuser = ap_own_subuser($sub_id);
    if (!$subuser || !ctype_digit(trim($text)) || intval($text) > 100 || intval($text) < 0) {
        sendmessage($from_id, "❌ مقدار نامعتبر است. عدد 0 تا 100 وارد کنید.", ap_back_to_panel(), 'HTML');
        return;
    }
    update("user", "pricediscount", intval($text), "id", $sub_id);
    step('home', $from_id);
    $uname = $subuser['username'] ? "@{$subuser['username']}" : $sub_id;
    sendmessage($from_id, "✅ درصد تخفیف کاربر {$uname} به {$text}٪ تنظیم شد.", ap_keyboard_main(), 'HTML');

// ── sub-user: ban / unban ─────────────────────────────────────────────────────

} elseif (preg_match('/^ap_ban_(\d+)$/', $datain, $m)) {
    $subuser = ap_own_subuser($m[1]);
    if (!$subuser) {
        Editmessagetext($from_id, $message_id, "❌ این کاربر زیرمجموعه شما نیست.", ap_back_to_panel());
        return;
    }
    $newStatus = ($subuser['User_Status'] == 'Banned') ? 'Active' : 'Banned';
    update("user", "User_Status", $newStatus, "id", $subuser['id']);
    $label = ($newStatus == 'Banned') ? '🚫 مسدود شد' : '✅ رفع مسدودیت شد';
    $uname = $subuser['username'] ? "@{$subuser['username']}" : $subuser['id'];
    Editmessagetext($from_id, $message_id, "{$label}: کاربر {$uname}", ap_back_to_panel());

// ── sub-user: view orders ─────────────────────────────────────────────────────

} elseif (preg_match('/^ap_orders_(\d+)$/', $datain, $m)) {
    $subuser = ap_own_subuser($m[1]);
    if (!$subuser) {
        Editmessagetext($from_id, $message_id, "❌ این کاربر زیرمجموعه شما نیست.", ap_back_to_panel());
        return;
    }
    $stmt = $pdo->prepare(
        "SELECT * FROM invoice WHERE id_user = :uid
         AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
         ORDER BY time_sell DESC LIMIT 20"
    );
    $stmt->execute([':uid' => $subuser['id']]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $uname = $subuser['username'] ? "@{$subuser['username']}" : $subuser['id'];
    if (empty($services)) {
        Editmessagetext($from_id, $message_id, "📦 کاربر {$uname} سرویس فعالی ندارد.", ap_back_to_panel());
        return;
    }
    $kb = ['inline_keyboard' => []];
    foreach ($services as $svc) {
        $label = $svc['username'];
        if (!empty($svc['note'])) $label .= " ({$svc['note']})";
        $label .= " — {$svc['Status']}";
        $kb['inline_keyboard'][] = [
            ['text' => "📋 {$label}", 'callback_data' => "ap_subsvc_{$svc['id_invoice']}_{$subuser['id']}"],
        ];
    }
    $kb['inline_keyboard'][] = [['text' => "↩️ بازگشت", 'callback_data' => "ap_userview_{$subuser['id']}"]];
    Editmessagetext($from_id, $message_id, "📦 سرویس‌های کاربر {$uname}:", json_encode($kb));

// ── sub-user service detail (full management same as own service) ──────────────

} elseif (preg_match('/^ap_subsvc_(\w+)_(\d+)$/', $datain, $m)) {
    $id_invoice = $m[1];
    $sub_id     = $m[2];
    $subuser    = ap_own_subuser($sub_id);
    if (!$subuser) {
        Editmessagetext($from_id, $message_id, "❌ این کاربر زیرمجموعه شما نیست.", ap_back_to_panel());
        return;
    }
    $invoice = ap_subuser_invoice($id_invoice, $sub_id);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ اتصال به پنل برقرار نشد.", ap_back_to_panel());
        return;
    }

    $status_map = [
        'active' => '✅ فعال', 'limited' => '⚠️ محدود', 'disabled' => '❌ غیرفعال',
        'expired' => '⏰ منقضی', 'on_hold' => '⏸ در انتظار', 'Unknown' => '❓ نامشخص',
        'deactivev' => '❌ غیرفعال',
    ];
    $status_label = $status_map[$dataUser['status']] ?? $dataUser['status'];
    $expire    = $dataUser['expire'] ? jdate('Y/m/d', $dataUser['expire']) : 'نامحدود';
    $timeDiff  = $dataUser['expire'] ? floor(($dataUser['expire'] - time()) / 86400) : '∞';
    $dayLabel  = is_numeric($timeDiff) ? "{$timeDiff} روز" : $timeDiff;
    $limit     = $dataUser['data_limit']   ? formatBytes($dataUser['data_limit'])   : 'نامحدود';
    $used      = $dataUser['used_traffic'] ? formatBytes($dataUser['used_traffic']) : '0';
    $remaining = $dataUser['data_limit']
        ? formatBytes(max(0, $dataUser['data_limit'] - $dataUser['used_traffic']))
        : 'نامحدود';
    $sub = $dataUser['subscription_url'] ?? '';
    $uname = $subuser['username'] ? "@{$subuser['username']}" : $sub_id;

    $infoText = "📋 <b>سرویس زیرمجموعه</b> ({$uname})\n\n" .
        "🔑 نام کاربری: <code>{$invoice['username']}</code>\n" .
        "📍 موقعیت: {$invoice['Service_location']}\n" .
        "📦 محصول: {$invoice['name_product']}\n" .
        "🔄 وضعیت: {$status_label}\n\n" .
        "🔋 حجم کل: {$limit}\n" .
        "📥 مصرف: {$used}\n" .
        "💢 باقی: {$remaining}\n" .
        "📅 انقضا: {$expire} ({$dayLabel})\n\n" .
        "🔗 لینک اشتراک:\n<code>{$sub}</code>";

    $toggle_text = ($dataUser['status'] === 'active') ? '❌ خاموش کردن اکانت' : '💡 روشن کردن اکانت';
    $id = $invoice['id_invoice'];

    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => "♻️ بروزرسانی",          'callback_data' => "ap_subsvc_{$id}_{$sub_id}"]],
            [['text' => "⚙️ دریافت همه کانفیگ ها", 'callback_data' => "ap_subsvc_configs_{$id}_{$sub_id}"]],
            [['text' => "🔄 تغییر لینک اشتراک",   'callback_data' => "ap_subsvc_resetlink_{$id}_{$sub_id}"]],
            [['text' => $toggle_text,               'callback_data' => "ap_subsvc_toggle_{$id}_{$sub_id}"]],
            [['text' => "↩️ بازگشت به سفارشات",    'callback_data' => "ap_orders_{$sub_id}"]],
        ],
    ]);
    Editmessagetext($from_id, $message_id, $infoText, $kb);

// ── sub-user service: all configs ─────────────────────────────────────────────

} elseif (preg_match('/^ap_subsvc_configs_(\w+)_(\d+)$/', $datain, $m)) {
    $id_invoice = $m[1];
    $sub_id     = $m[2];
    $invoice    = ap_subuser_invoice($id_invoice, $sub_id);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful" || empty($dataUser['links'])) {
        Editmessagetext($from_id, $message_id, "❌ کانفیگی یافت نشد.", ap_back_to_panel());
        return;
    }
    deletemessage($from_id, $message_id);
    ap_send_all_configs($from_id, $dataUser['links'], "ap_subsvc_{$id_invoice}_{$sub_id}");

// ── sub-user service: change link ─────────────────────────────────────────────

} elseif (preg_match('/^ap_subsvc_resetlink_(\w+)_(\d+)$/', $datain, $m)) {
    $id_invoice = $m[1];
    $sub_id     = $m[2];
    $invoice    = ap_subuser_invoice($id_invoice, $sub_id);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد.", ap_back_to_panel());
        return;
    }
    $result = $ManagePanel->Revoke_sub($invoice['Service_location'], $invoice['username']);
    if ($result['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ تغییر لینک با خطا مواجه شد.", ap_back_to_panel());
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    Editmessagetext($from_id, $message_id, "✅ لینک با موفقیت تغییر کرد.", json_encode([
        'inline_keyboard' => [[['text' => "↩️ مشاهده سرویس", 'callback_data' => "ap_subsvc_{$id_invoice}_{$sub_id}"]]],
    ]));
    if ($marzban_list_get['config'] == "onconfig" && !empty($result['configs'])) {
        ap_send_all_configs($from_id, $result['configs'], "ap_subsvc_{$id_invoice}_{$sub_id}");
    }

// ── sub-user service: toggle ──────────────────────────────────────────────────

} elseif (preg_match('/^ap_subsvc_toggle_(\w+)_(\d+)$/', $datain, $m)) {
    $id_invoice = $m[1];
    $sub_id     = $m[2];
    $invoice    = ap_subuser_invoice($id_invoice, $sub_id);
    if (!$invoice) {
        Editmessagetext($from_id, $message_id, "❌ سرویس یافت نشد.", ap_back_to_panel());
        return;
    }
    $dataUser = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    if ($dataUser['status'] == "Unsuccessful" || $dataUser['status'] == "on_hold") {
        Editmessagetext($from_id, $message_id, "❌ امکان تغییر وضعیت وجود ندارد.", ap_back_to_panel());
        return;
    }
    $result = $ManagePanel->Change_status($invoice['username'], $invoice['Service_location']);
    if ($result['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, "❌ تغییر وضعیت با خطا مواجه شد.", ap_back_to_panel());
        return;
    }
    $dataUser  = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    $newStatus = ($dataUser['status'] == 'active') ? 'active' : 'disabled';
    update("invoice", "Status", $newStatus, "id_invoice", $id_invoice);
    Editmessagetext($from_id, $message_id, "✅ وضعیت سرویس تغییر کرد.", json_encode([
        'inline_keyboard' => [[['text' => "↩️ مشاهده سرویس", 'callback_data' => "ap_subsvc_{$id_invoice}_{$sub_id}"]]],
    ]));
}

// ── sub-user display helper (defined after all handlers to avoid forward-ref issues) ──

if (!function_exists('ap_show_subuser')) {
    function ap_show_subuser(int $chat_id, array $subuser, bool $edit = false, int $message_id = 0): void
    {
        $uname = $subuser['username'] ? "@{$subuser['username']}" : $subuser['id'];
        $agentLabel = ['n' => 'نماینده', 'n2' => 'نماینده سطح 2', 'f' => 'کاربر عادی'][$subuser['agent']] ?? $subuser['agent'];
        $statusLabel = ($subuser['User_Status'] == 'Banned') ? '🚫 مسدود' : '✅ فعال';
        $registerDate = $subuser['register'] ? jdate('Y/m/d', strtotime($subuser['register'])) : '—';
        $banBtnText = ($subuser['User_Status'] == 'Banned') ? "✅ رفع مسدودیت" : "🚫 مسدود کردن";

        $infoText = "👤 <b>اطلاعات کاربر</b>\n\n" .
            "🆔 شناسه: <code>{$subuser['id']}</code>\n" .
            "👤 یوزرنیم: {$uname}\n" .
            "🏷 نوع: {$agentLabel}\n" .
            "📊 وضعیت: {$statusLabel}\n" .
            "🎁 درصد تخفیف: {$subuser['pricediscount']}٪\n" .
            "📅 تاریخ ثبت: {$registerDate}";

        $kb = json_encode([
            'inline_keyboard' => [
                [['text' => "📦 مشاهده سفارشات",    'callback_data' => "ap_orders_{$subuser['id']}"]],
                [['text' => "🎁 درصد تخفیف",         'callback_data' => "ap_discount_{$subuser['id']}"]],
                [['text' => $banBtnText,               'callback_data' => "ap_ban_{$subuser['id']}"]],
                [['text' => "↩️ بازگشت به پنل نماینده", 'callback_data' => "ap_panel"]],
            ],
        ]);

        if ($edit && $message_id) {
            Editmessagetext($chat_id, $message_id, $infoText, $kb);
        } else {
            sendmessage($chat_id, $infoText, $kb, 'HTML');
        }
    }
}
