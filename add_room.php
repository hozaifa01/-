<?php
// إعدادات الجلسة الآمنة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true
]);

require_once 'dbconnection.php';

// التحقق من الجلسة
if (!isset($_SESSION['aid']) ||!filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
    header('Location: logout.php');
    exit();
}
$aid = (int) $_SESSION['aid'];

// التحقق من صلاحية المدير
$stmt = $con->prepare("SELECT level FROM tbl_login WHERE id =?");
$stmt->bind_param("i", $aid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
if (!isset($user) || (int)$user['level'] <= 1) {
    echo '<script>alert("عفوا، لا تملك صلاحيات كافية")</script>';
    echo "<script>window.location.href='index.php'</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8')?> - إضافة غرفة</title>
  <?php include "header.php";?>
</head>
<body class="p-4">
<div class="container-fluid">
  <div class="row">
    <div class="col-md-3">
      <?php include "leftbar.php";?>
    </div>
    <div class="col-md-9">
      <h2 class="mb-4">إضافة غرفة جديدة</h2>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">رقم الغرفة</label>
          <input type="text" name="room_number" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">نوع الغرفة</label>
          <select name="room_type" class="form-control" required>
            <option value="فردية">فردية</option>
            <option value="مزدوجة">مزدوجة</option>
            <option value="3 سراير">3 سراير</option>
            <option value="4 سراير"> 4 سراير</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">حالة الغرفة</label>
          <select name="current_status" class="form-control" required>
            <option value="متاحة">متاحة</option>
            <option value="محجوز">محجوز</option>
            <option value="تحت التنظيف">تحت التنظيف</option>
            <option value="تحت الصيانة">تحت الصيانة</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">السعر لليلة ($)</label>
                    <select name="price_per_night" class="form-control" required>
            <option value="150000"> جنيه 150000</option>
            <option value="170000">جنيه 170000</option>
            <option value="190000"> جنيه 190000</option>
            <option value="60000">جنيه 60000</option>
          </select>
        </div>
        <button type="submit" name="save" class="btn btn-success">حفظ</button>
        <a href="rooms.php" class="btn btn-secondary">رجوع</a>
      </form>
    </div>
  </div>
</div>
<?php include "footer.php";?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // تعقيم المدخلات
    $number = trim($_POST['room_number']);
    $type = $_POST['room_type'];
    $state = $_POST['current_status'];
    $price = filter_var($_POST['price_per_night'], FILTER_VALIDATE_FLOAT);

    if ($price === false || $price < 0) {
        echo "<script>alert('السعر غير صالح');</script>";
} else {
        // استخدام Prepared Statement
        $stmt = $con->prepare("INSERT INTO rooms (room_number, room_type, price_per_night, current_status) VALUES (?,?,?,?)");
        if ($stmt) {
            $stmt->bind_param("ssds", $number, $type, $price, $state);
            if ($stmt->execute()) {
                echo "<script>alert('تمت إضافة الغرفة بنجاح');window.location='rooms.php';</script>";
} else {
                error_log("خطأ في التنفيذ: ". $stmt->error);
                echo "<script>alert('حدث خطأ أثناء حفظ البيانات');</script>";
}
            $stmt->close();
} else {
            error_log("فشل إعداد الاستعلام: ". $con->error);
            echo "<script>alert('تعذر إعداد عملية الحفظ');</script>";
}
}
}
?>
</body>
</html>