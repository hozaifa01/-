<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
require_once 'dbconnection.php';
require_once 'update_room_status.php';
if (!isset($_SESSION['aid']) ||!filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
  header('Location: logout.php');
  exit();
}
// إنشاء رمز CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$aid = (int) $_SESSION['aid'];
$stmt = $con->prepare("SELECT level, photo,FullName FROM tbl_login WHERE id =?");
$stmt->bind_param("i", $aid);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$user_level = (int) $row['level'];
$photo =!empty($row['photo'])? htmlspecialchars($row['photo'], ENT_QUOTES, 'UTF-8'): 'default.png';
?>
    <!-- زر إظهار/إخفاء القائمة -->
    <button class="btn btn-outline-custom ms-auto" id="toggleSidebarBtn" type="button">
      <i class="fa fa-hand-o-right"></i>
    </button>
 <button class="ui-button ui-widget ui-corner-all" id="theme-toggler" title="تبديل شكل التصميم">
      <i class="fa fa-adjust"></i>
    </button>


<div id="sidebarMenu" class="bg-custom text-right border rounded p-3 mb-4">
  <h5 class="mb-3">
    <!-- Sidebar Navigation -->
<nav class="navbar navbar-expand-lg navbar-custom text-right bg-custom mb-3">
  <div class="container-fluid text-right">
    <a class="navbar-brand text-right" href="index.php" title="الذهاب للرئيسية">
<?= htmlspecialchars($site['title'], ENT_QUOTES, 'UTF-8') ?>
    </a>

  </div>
</nav>
    <?= "أهلاً وسهلاً <span style='color:red;'> ". htmlspecialchars($row['FullName'], ENT_QUOTES,
    'UTF-8') . " </span> طاب يومك يا"
    ?>
    <?php
    if ($user_level === 1) echo "موظف إستقبالنا ";
    elseif ($user_level === 2) echo "محاسبنا";
    elseif ($user_level === 99) echo "مديرنا";
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
    ?>
  </h5>

  <ul class="nav flex-column fs-5">
    <li class="nav-item mb-2">
      <a href="index.php" class="nav-link"><i class="fa fa-home me-2"></i> الشاشة الرئيسية</a></li>
    <li class="nav-item mb-2"><?php if (isset($user) && (int)$user['level'] === 99):?>
  <a class="nav-link" href="bookings_admin.php">
        <i class="fa fa-user-secret"></i> لوحة الحجز (مدير)
    </a>
<?php else:?>
    <a class="nav-link" href="bookings.php">
        <i class="fa fa-user"></i> لوحة الحجز (عضو)
    </a>
<?php endif;?></a></li>
    <li class="nav-item mb-2"><a href="rooms.php" class="nav-link"><i class="fa
      fa-bed me-2"></i> إدارة الغرف</a></li>
    <li class="nav-item mb-2"><a href="reports.php" class="nav-link"><i class="fa fa-bar-chart me-2"></i> التقارير</a></li>
    <li class="nav-item mb-2"><a href="manage_users.php" class="nav-link"><i class="fa fa-users me-2"></i> إدارة المستخدمين</a></li>
    <li class="nav-item mb-2"><a href="site.php" class="nav-link"><i class="fa fa-cogs me-2"></i> الإعدادات</a></li>
  </ul>

  <div class="dropdown mt-3">
    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
      <img src="uploads/<?= $photo ?>" alt="صورة المستخدم" width="32" height="32" class="rounded-circle me-2">
      <strong><?= htmlspecialchars($_SESSION['login'], ENT_QUOTES, 'UTF-8') ?></strong>
    </a>
    <ul class="dropdown-menu text-small shadow">
      <li><a class="dropdown-item" href="profile.php">الملف الشخصي</a></li>
      <li><a class="dropdown-item" href="#">الإعدادات</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="logout.php">تسجيل خروج</a></li>
    </ul>
  </div>

</div>

<!-- زر إظهار/إخفاء القائمة -->
<script>
$(document).ready(function () {
  // استعادة حالة القائمة الجانبية من localStorage
  const sidebarHidden = localStorage.getItem("sidebarHidden") === "true";
  if (sidebarHidden) {
    $("body").addClass("sidebar-hidden");
    $("#toggleSidebarBtn i").removeClass("fa-hand-o-right").addClass("fa-hand-o-left");
}

  // استعادة السمة من التخزين المحلي
  if (localStorage.getItem("theme") === "dark") {
    $("body").addClass("dark-theme");
    $("#theme-toggler i").removeClass("fa-adjust").addClass("fa-sun");
}

  // تبديل السمة
  $("#theme-toggler").click(function () {
    $("body").toggleClass("dark-theme");
    const isDark = $("body").hasClass("dark-theme");
    localStorage.setItem("theme", isDark? "dark": "light");

    const icon = $(this).find("i");
    if (isDark) {
      icon.removeClass("fa-adjust").addClass("fa-sun");
} else {
      icon.removeClass("fa-sun").addClass("fa-adjust");
}
});

  // تبديل حالة القائمة الجانبية
  $("#toggleSidebarBtn").click(function () {
    $("body").toggleClass("sidebar-hidden");
    const isHidden = $("body").hasClass("sidebar-hidden");

    localStorage.setItem("sidebarHidden", isHidden);

    $.post("update_sidebar_state.php", {
      hidden: isHidden? 1: 0
});

    const icon = $(this).find("i");
    if (isHidden) {
      icon.removeClass("fa-hand-o-right").addClass("fa-hand-o-left");
} else {
      icon.removeClass("fa-hand-o-left").addClass("fa-hand-o-right");
}
});

  // إغلاق القائمة عند النقر خارجها على الشاشات الصغيرة
  $(document).on("click", function (e) {
    if ($(window).width() < 992) {
      if (!$(e.target).closest("#sidebarMenu, #toggleSidebarBtn").length &&
!$("body").hasClass("sidebar-hidden")) {
        $("body").addClass("sidebar-hidden");
        $("#toggleSidebarBtn i").removeClass("fa-hand-o-right").addClass("fa-hand-o-left");
        localStorage.setItem("sidebarHidden", true);
}
}
});

  // منع إغلاق القائمة عند النقر داخلها
  $("#sidebarMenu").on("click", function (e) {
    e.stopPropagation();
});
});
</script>
<style>
#theme-toggler{
  position: fixed;
    top: 100px;
    right: 20px;
    z-index: 1010;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    transition: all var(--transition-speed);
}
    #toggleSidebarBtn {
    position: fixed;
    top: 190px;
    right: 20px;
    z-index: 1010;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    transition: all var(--transition-speed);
  }

</style>
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