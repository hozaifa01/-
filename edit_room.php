<?php
// إعدادات الجلسة الآمنة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true
    ]);
}

require_once 'dbconnection.php';

// التحقق من الجلسة
if (!isset($_SESSION['aid']) ||!filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
    header('Location: logout.php');
    exit();
}

// التحقق من معرف الغرفة
if (!isset($_GET['id']) ||!filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo "<script>alert('معرف الغرفة غير صالح');window.location='rooms.php';</script>";
    exit();
}
$room_id = (int) $_GET['id'];

// جلب بيانات الغرفة باستخدام Prepared Statement
$stmt = $con->prepare("SELECT room_number, room_type, price_per_night, current_status FROM rooms WHERE room_id =?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    echo "<script>alert('الغرفة غير موجودة');window.location='rooms.php';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8')?> - تعديل غرفة</title>
    <?php include "header.php";?>
</head>
<body class="p-4">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include "leftbar.php";?>
        </div>
        <div class="col-md-9">
            <h2 class="mb-4">الحجوزات والعملاء</h2>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">رقم الغرفة</label>
            <input type="text" name="room_number" class="form-control" value="<?= htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8')?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">نوع الغرفة</label>
            <select name="room_type" class="form-control" required>
                <option value="سرير واحد" <?= $room['room_type'] === 'سرير واحد'? 'selected': ''?>>فردية</option>
                <option value="مزدوجة" <?= $room['room_type'] === 'مزدوجة'? 'selected': ''?>>مزدوجة</option>
                <option value="3 سراير " <?= $room['room_type'] === '3 سراير '?               'selected': ''?>>3سراير</option>
                <option value="4 سراير " <?= $room['room_type'] === '4 سراير '?               'selected': ''?>>3سراير</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">السعر لليلة ($)</label>
            <input type="number" step="0.01" name="price_per_night" class="form-control" value="<?= htmlspecialchars($room['price_per_night'], ENT_QUOTES, 'UTF-8')?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">الحالة</label>
            <select name="current_status" class="form-control" required>
                <option value="متاحة" <?= $room['current_status'] === 'متاحة'? 'selected': ''?>>متاحة</option>
                <option value="محجوزة" <?= $room['current_status'] === 'محجوزة'? 'selected': ''?>>محجوزة</option>
                <option value="تحت الصيانة" <?= $room['current_status'] === 'تحت الصيانة'? 'selected': ''?>>صيانة</option>
            </select>
        </div>
        <button type="submit" name="update" class="btn btn-primary">تحديث</button>
        <a href="rooms.php" class="btn btn-secondary">رجوع</a>
    </form>
</div>
</div>
</div>
<?php
// معالجة التحديث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $room_number = trim($_POST['room_number']);
    $room_type = $_POST['room_type'];
    $price = filter_var($_POST['price_per_night'], FILTER_VALIDATE_FLOAT);
    $status = $_POST['current_status'];

    if ($price === false || $price < 0) {
        echo "<script>alert('السعر غير صالح');</script>";
} else {
  $stmt = $con->prepare("UPDATE rooms SET room_number =?, room_type =?, price_per_night =?, current_status =? WHERE room_id =?");
        $stmt->bind_param("ssdsi", $room_number, $room_type, $price, $status, $room_id);
        if ($stmt->execute()) {
            echo "<script>alert('تم تحديث بيانات الغرفة بنجاح');window.location='rooms.php';</script>";
} else {
            error_log("خطأ في التحديث: ". $stmt->error);
            echo "<script>alert('حدث خطأ أثناء التحديث');</script>";
}
        $stmt->close();
}
}

// إغلاق الاتصال بقاعدة البيانات
$con->close();
?>
<?php include_once("footer.php");?>
</body>
</html>
  
