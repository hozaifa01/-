<?php
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
// استعلامات العد باستخدام Prepared Statements
function getCount($con, $sql) {
    $stmt = $con->prepare($sql);
    if (!$stmt) return 0;
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->num_rows;
    $stmt->close();
    return $count;
}

$totalrooms     = getCount($con, "SELECT room_id FROM rooms");
$avalible_room  = getCount($con, "SELECT room_id FROM rooms WHERE current_status = 'متاحة'");
$totalcustomers = getCount($con, "SELECT customer_id FROM customers");
$totalusers     = getCount($con, "SELECT id FROM tbl_login");

// تحديث حالة الغرف حسب تاريخ الحجز
$stmt = $con->prepare("SELECT customer_id, room_id, check_in, check_out FROM customers ORDER BY customer_id DESC");
$stmt->execute();
$result = $stmt->get_result();
$current_time = time();

while ($row = $result->fetch_assoc()) {
    $check_in = strtotime($row['check_in']);
    $check_out = strtotime($row['check_out']);
    $remaining_days = floor(($check_out - $current_time) / (60 * 60 * 24));
    $room_id = (int) $row['room_id'];
    $status = ($remaining_days <= 0)? 'متاحة': 'محجوزة';

    $update = $con->prepare("UPDATE rooms SET current_status =? WHERE room_id =?");
    $update->bind_param("si", $status, $room_id);
    $update->execute();
    $update->close();
}
$stmt->close();

// إيرادات الشهر الحالي
$current_month = date('m');
$current_year  = date('Y');
$payments_total = 0;
$stmt = $con->prepare("SELECT IFNULL(SUM(amount),0) as total FROM customers WHERE MONTH(check_in) =? AND YEAR(check_in) =?");
$stmt->bind_param("ii", $current_month, $current_year);
$stmt->execute();
$stmt->bind_result($payments_total);
$stmt->fetch();
$stmt->close();

?>

<?php
if (isset($_SESSION['aid']) && filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
    $user_id = (int) $_SESSION['aid'];

    $stmt = $con->prepare("SELECT photo FROM tbl_login WHERE id =?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $photo =!empty($user['photo'])? htmlspecialchars($user['photo'], ENT_QUOTES, 'UTF-8'): null;
}
?>
<div class="container-fluid">
    <div class="row">
        <!-- شاشة الغرف -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fa fa-bed fa-5x"></i>
                    <a href="rooms.php"><h4>شاشة الغرف</h4></a>
                    <p>عدد الغرف: <span class="badge"><?= htmlspecialchars($totalrooms)?></span></p>
                    <p>الغرف الشاغرة: <span class="badge"><?= htmlspecialchars($avalible_room)?></span></p>
                </div>
            </div>
        </div>

        <!-- شاشة الحجوزات -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">

<?php
$aid = $_SESSION['aid'];

// التحقق من صلاحية المدير
$stmt = $con->prepare("SELECT level FROM tbl_login WHERE id =?");
$stmt->bind_param("i", $aid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// عرض الرابط المناسب حسب مستوى العضو
if ($user && intval($user['level']) === 99) {
    echo ' <i class="fa  fa-user-secret fa-5x"></i><h4><a
    href="bookings_admin.php"><i class="fa fa-user-secret"></i> لوحة الحجز
    (مدير)</a><h4>';
} else {
    echo '<i class="fa  fa-user-secret fa-5x"></i><h4><a
    href="bookings.php"><i class="fa fa-user"></i> لوحة الحجز
    (إستقبال)</a><h4>';
}
?>
                    <p><span class="badge"><?= htmlspecialchars($totalcustomers)?></span></p>
                </div>
            </div>
        </div>

        <!-- شاشة المستخدمين -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fa fa-dashboard fa-5x"></i>
                    <a href="manage_users.php"><h4>شاشة المستخدمين</h4></a>
                    <p><span class="badge"><?= htmlspecialchars($totalusers)?></span></p>
                </div>
            </div>
        </div>

        <!-- شاشة التقارير -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fa fa-bar-chart fa-5x"></i>
                    <a href="reports.php"><h4>شاشة التقارير</h4></a>
                    <p>إيرادات الشهر: <span class="badge"><?= number_format($payments_total)?> $</span></p>
                </div>
            </div>
        </div>

        <!-- الملف الشخصي -->
        <div class="col-md-4">
            <div class="card text-center">
 <div class="card-body">
<?php if (!empty($photo)):?>
    <img src="uploads/<?= $photo?>" alt="صورة المستخدم" width="100" height="100" class="rounded-circle me-2">
<?php else:?>
    <img src="uploads/default.png" alt="صورة افتراضية" width="100" height="100" class="rounded-circle me-2">
<?php endif;?>
                    <a href="profile.php"><h4>الملف الشخصي</h4></a>
                </div>
            </div>
        </div>

        <!-- إعدادات النظام -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fa fa-cog fa-5x"></i>
                    <a href="site.php"><h4>إعدادات النظام</h4></a>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.card {
    margin: 10px;
}
.card-body {
    padding: 20px;
}
.card-body i {
    margin-bottom: 10px;
}
.card-body h4 {
    margin-bottom: 10px;
}
.card-body p {
    margin-bottom: 0;
}
.badge {
    font-size: 16px;
    background-color: #f1f1f1;
    padding: 5px 10px;
    border-radius: 10px;
}
</style>