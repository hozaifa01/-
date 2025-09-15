<?php
require_once 'dbconnection.php';

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
// التحقق من وجود المعرف وصحته
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $room_id = (int) $_GET['id'];

    // استخدام Prepared Statement للحذف الآمن
    $stmt = $con->prepare("DELETE FROM rooms WHERE room_id =?");
    if ($stmt) {
        $stmt->bind_param("i", $room_id);
        if ($stmt->execute()) {
            echo "<script>alert('تم حذف الغرفة بنجاح');window.location='rooms.php';</script>";
} else {
            error_log("فشل تنفيذ الحذف: ". $stmt->error);
            echo "<script>alert('حدث خطأ أثناء حذف الغرفة');window.location='rooms.php';</script>";
}
        $stmt->close();
} else {
        error_log("فشل إعداد الاستعلام: ". $con->error);
        echo "<script>alert('تعذر حذف الغرفة');window.location='rooms.php';</script>";
}
} else {
    echo "<script>alert('معرف الغرفة غير صالح');window.location='rooms.php';</script>";
}
?>