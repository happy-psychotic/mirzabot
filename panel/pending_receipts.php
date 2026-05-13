<?php
session_start();
require_once __DIR__ . '/../config.php';
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}
if (!in_array($result['rule'], ['administrator', 'Seller'], true)) {
    header('Location: index.php');
    return;
}
$_SESSION["admin_rule"] = $result['rule'];
$query = $pdo->prepare("SELECT * FROM Payment_report WHERE payment_Status = 'waiting' ORDER BY time ASC");
$query->execute();
$listpayment = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="img/favicon.html">
    <title>پنل مدیریت ربات میرزا</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-reset.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/jquery-easy-pie-chart/jquery.easy-pie-chart.css" rel="stylesheet" type="text/css" media="screen"/>
    <link rel="stylesheet" href="css/owl.carousel.css" type="text/css">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />
  </head>
<body>
    <section id="container" class="">
<?php include("header.php"); ?>
        <section id="main-content">
            <section class="wrapper">
                <div class="row">
                    <div class="col-lg-12">
                        <section class="panel">
                            <header class="panel-heading">
                                رسیدهای تایید نشده
                                <span class="badge badge-warning" style="margin-right:8px;"><?php echo count($listpayment); ?></span>
                            </header>
                            <?php if (count($listpayment) == 0): ?>
                                <div style="padding:20px; text-align:center; color:#888;">هیچ رسید در انتظار تایید وجود ندارد.</div>
                            <?php else: ?>
                            <table class="table table-striped border-top" id="sample_1">
                                <thead>
                                    <tr>
                                        <th class="hidden-phone">آیدی کاربر</th>
                                        <th>کد پیگیری</th>
                                        <th class="hidden-phone">مبلغ (تومان)</th>
                                        <th class="hidden-phone">روش پرداخت</th>
                                        <th class="hidden-phone">زمان ارسال</th>
                                        <th class="hidden-phone">توضیحات رسید</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $method_map = [
                                    'cart to cart'           => 'کارت به کارت',
                                    'low balance by admin'   => 'کسر موجودی توسط ادمین',
                                    'add balance by admin'   => 'افزایش موجودی توسط ادمین',
                                    'Currency Rial 1'        => 'درگاه ارزی ریالی اول',
                                    'Currency Rial tow'      => 'درگاه ارزی ریالی دوم',
                                    'Currency Rial 3'        => 'درگاه ارزی ریالی سوم',
                                    'aqayepardakht'          => 'درگاه اقای پرداخت',
                                    'zarinpal'               => 'زرین پال',
                                    'plisio'                 => 'درگاه ارزی plisio',
                                    'arze digital offline'   => 'درگاه ارزی آفلاین',
                                    'Star Telegram'          => 'استار تلگرام',
                                    'nowpayment'             => 'NowPayment',
                                ];
                                foreach ($listpayment as $list):
                                    $price    = number_format($list['price']);
                                    $method   = $method_map[$list['Payment_Method']] ?? $list['Payment_Method'];
                                    $desc     = htmlspecialchars($list['dec_not_confirmed'] ?? '—');
                                    $time     = htmlspecialchars($list['at_updated'] ?? $list['time'] ?? '—');
                                    $user_id  = htmlspecialchars($list['id_user']);
                                    $order_id = htmlspecialchars($list['id_order']);
                                ?>
                                <tr class="odd gradeX">
                                    <td class="hidden-phone"><?php echo $user_id; ?></td>
                                    <td><?php echo $order_id; ?></td>
                                    <td class="hidden-phone"><?php echo $price; ?></td>
                                    <td class="hidden-phone"><?php echo htmlspecialchars($method); ?></td>
                                    <td class="hidden-phone"><?php echo $time; ?></td>
                                    <td class="hidden-phone" style="max-width:260px;word-break:break-all;font-size:12px;"><?php echo $desc; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>
            </section>
        </section>
    </section>
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.scrollTo.min.js"></script>
    <script src="js/jquery.nicescroll.js" type="text/javascript"></script>
    <script type="text/javascript" src="assets/data-tables/jquery.dataTables.js"></script>
    <script type="text/javascript" src="assets/data-tables/DT_bootstrap.js"></script>
    <script src="js/common-scripts.js"></script>
    <script src="js/dynamic-table.js"></script>
</body>
</html>
