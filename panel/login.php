<?php
ini_set('session.cookie_httponly', true);
session_start();
session_regenerate_id(true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
$texterrr = "";
$_SESSION["user"] = null;
if (isset($_POST['login'])) {
    $username = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
    $password = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8');
    $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $username, PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    if ( !$result ) {
        $texterrr = 'نام کاربری یا رمزعبور وارد شده اشتباه است!';
    } else {
                       
        if ( $password == $result["password"]) {
            foreach ($admin_ids as $admin) {
                $texts = "کاربر با نام کاربری $username وارد پنل تحت وب شد";
                sendmessage($admin, $texts, null, 'html');
            }
            $_SESSION["user"] = $result["username"];
            $_SESSION["admin_rule"] = $result["rule"];
            header('Location: index.php');
            exit;
        } else {
            $texterrr =  'رمز صحیح نمی باشد';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="Mosaddek">
    <meta name="keyword" content="FlatLab, Dashboard, Bootstrap, Admin, Template, Theme, Responsive, Fluid, Retina">
    <link rel="shortcut icon" href="img/favicon.html">

    <title>ورود به پنل مدیریت</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-reset.css" rel="stylesheet">
    <!--external css-->
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <!-- Custom styles for this template -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 tooltipss and media queries -->
    <!--[if lt IE 9]>
    <script src="js/html5shiv.js"></script>
    <script src="js/respond.min.js"></script>
    <![endif]-->
</head>

  <body class="login-body">
    <div class="container">
      <form method="post" class="form-signin" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <h2 class="form-signin-heading">پنل مدیریت ربات میرزا</h2>
        <div class="login-wrap">
            <p><?php echo $texterrr; ?></p>
            <input type="text" name="username" class="form-control" placeholder="نام کاربری" autofocus>
            <input type="password" name="password" class="form-control" placeholder="کلمه عبور">
            <button class="btn btn-lg btn-login btn-block" name="login" type="submit">ورود</button>
        </div>

      </form>
    </div>


  </body>
</html>
