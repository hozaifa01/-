<?php
// get_total_room_price.php
require_once 'dbconnection.php'; // تأكد من أن مسار الاتصال بقاعدة البيانات صحيح

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
header('Content-Type: application/json'); // إرجاع البيانات بصيغة JSON

if (isset($_POST['room_ids']) && is_array($_POST['room_ids'])) {
    $selected_room_ids = array_map('intval', $_POST['room_ids']); // تحويل معرفات الغرف إلى أعداد صحيحة

    if (empty($selected_room_ids)) {
        echo json_encode(['total_price' => 0]);
        exit();
    }

    // إنشاء Placeholder لكل معرف غرفة في الاستعلام الآمن
    $placeholders = implode(',', array_fill(0, count($selected_room_ids), '?'));
    $sql = "SELECT SUM(price_per_night) AS total_price FROM rooms WHERE room_id IN ($placeholders)";

    $stmt = $con->prepare($sql);
    if ($stmt) {
        // ربط المعرفات بشكل آمن
        $types = str_repeat('i', count($selected_room_ids)); // 'i' لكل عدد صحيح
        $stmt->bind_param($types, ...$selected_room_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_price = (float) $row['total_price'];
        $stmt->close();
        echo json_encode(['total_price' => $total_price]);
    } else {
        error_log("Failed to prepare statement in get_total_room_price.php: " . $con->error);
        echo json_encode(['total_price' => 0, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['total_price' => 0, 'error' => 'No room IDs provided or invalid format']);
}
?>