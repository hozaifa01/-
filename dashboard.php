<?php
// إعدادات الجلسة
ini_set('session.gc_maxlifetime', 1800); // مدة الجلسة على السيرفر: 30 دقيقة
session_set_cookie_params(1800);         // مدة الكوكيز في المتصفح
ini_set('session.cookie_httponly', 1); // منع الوصول إلى الكوكيز عبر JavaScript
ini_set('session.use_strict_mode', 1); // منع اختطاف الجلسات
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1); // تفعيل Secure flag عند استخدام HTTPS
}

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true
]);

// تضمين ملف الاتصال بقاعدة البيانات
require_once('dbconnection.php');
// التحقق إذا كان المستخدم مسجل الدخول
if (empty($_SESSION["aid"]) || !is_numeric($_SESSION["aid"])) {
    header("location:logout.php");
    exit();
}

// تنظيف وتعقيم معامل الجلسة
$aid = filter_var($_SESSION['aid'], FILTER_VALIDATE_INT);
if ($aid === false) {
    header("location:logout.php");
    exit();
}


$aid = $_SESSION['aid'];
// التحقق من صلاحية المدير
$stmt = $con->prepare("SELECT level FROM tbl_login WHERE id =?");
$stmt->bind_param("i", $aid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
if (!isset($user) || (int)$user['level'] <= 0) {
    echo '<script>alert("عفوا، لا تملك صلاحيات كافية")</script>';
    echo "<script>window.location.href='index.php'</script>";
    exit();
}
// التحقق من الاتصال بقاعدة البيانات
if (!$con) {
    die("فشل الاتصال بقاعدة البيانات: ". mysqli_connect_error());
}

// دوال مساعدة للاستعلام الآمن
function getRowCount($con, $query) {
    $stmt = mysqli_prepare($con, $query);
    if ($stmt) {
        mysqli_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_num_rows($result);
}
    return 0;
}

// تنفيذ الاستعلامات
$totalrooms     = getRowCount($con, "SELECT room_id FROM rooms");
$avalible_room  = getRowCount($con, "SELECT room_id FROM rooms WHERE current_status = 'متاحة'");
$totalcustomers = getRowCount($con, "SELECT customer_id FROM customers");
$totalusers     = getRowCount($con, "SELECT id FROM tbl_login");
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="<?php echo htmlspecialchars($site['description']);?>">
    <title><?php echo htmlspecialchars($site['title']);?></title>    <?php
    include_once "header.php";?>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <?php include_once"leftbar.php";?>
            </div>
            <div class="col-md-9">
                <h2 class="mb-4">الواجهة الرئيسية</h2>
                <hr />
                <?php include_once("states.php");?>
            </div>
        </div>
    </div>
    <?php include_once("footer.php");?>
     <style>
        body {
            padding-top: 70px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .time-alert-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            border-bottom: 2px solid #ffc107;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: slideInDown 0.5s ease;
        }
        
        .alert-content {
            padding: 12px 0;
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
        }
        
        .alert-icon {
            font-size: 24px;
            color: #ff9800;
            margin-left: 15px;
        }
        
        .alert-title {
            font-weight: bold;
            color: #7d6608;
            margin-bottom: 5px;
        }
        
        .alert-message {
            color: #7d6608;
            margin-bottom: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: #7d6608;
            font-size: 18px;
            cursor: pointer;
            transition: color 0.3s;
            margin-right: 15px;
        }
        
        .close-btn:hover {
            color: #ff5722;
        }
        
        .show-alert-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .example-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #ffc107;
            left: 50%;
            margin-left: -2px;
        }
        
        .timeline-item {
            margin-bottom: 40px;
            position: relative;
            width: 50%;
            padding: 0 40px;
            box-sizing: border-box;
        }
        
        .timeline-item:nth-child(odd) {
            left: 0;
            text-align: left;
        }
        
        .timeline-item:nth-child(even) {
            left: 50%;
            text-align: right;
        }
        
        .timeline-content {
            padding: 20px;
            background: #fff8e1;
            border-radius: 10px;
            border: 1px solid #ffd54f;
            position: relative;
        }
        
        .timeline-content::after {
            content: '';
            position: absolute;
            top: 20px;
            width: 0;
            height: 0;
            border-style: solid;
        }
        
        .timeline-item:nth-child(odd) .timeline-content::after {
            right: -10px;
            border-width: 10px 0 10px 10px;
            border-color: transparent transparent transparent #ffd54f;
        }
        
        .timeline-item:nth-child(even) .timeline-content::after {
            left: -10px;
            border-width: 10px 10px 10px 0;
            border-color: transparent #ffd54f transparent transparent;
        }
        
        .timeline-time {
            font-weight: bold;
            color: #ff9800;
            margin-bottom: 5px;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
            }
            to {
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
    </style>

<!-- شريط التنبيه -->
<div class="time-alert-bar" id="timeAlertBar">
    <div class="alert-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="alert-icon">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div>
                        <div class="alert-title">تنبيه هام: توقيت الفندق يبدأ وينتهي عند الساعة 12 ظهرًا</div>
                        <p class="alert-message mb-0">ادخل البيانات بتاريخ اليوم
                        المحدد وسيقوم النظام بحساب التاريخ تلقائي .</p>
                    </div>
                </div>
                <button class="close-btn" id="closeAlert">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        // إخفاء الشريط عند النقر على زر الإغلاق
        $("#closeAlert").click(function() {
            $("#timeAlertBar").slideUp(300);
        });
        
        // إظهار الشريط عند النقر على زر الإظهار
        $("#showAlertBtn").click(function() {
            $("#timeAlertBar").slideDown(300);
            $(this).removeClass("pulse");
        });
        
        // تأثيرات jQuery UI على البطاقات
        $(".card").hover(
            function() {
                $(this).effect("highlight", { color: "#fff8e1" }, 1000);
            }
        );
        
        // تأثيرات على الأمثلة في الجدول الزمني
        $(".timeline-content").click(function() {
            $(this).effect("bounce", { times: 3, distance: 10 }, 300);
        });
    });
</script>
</div>
</body>
</html>