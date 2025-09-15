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
// استعلام عدد الغرف
$totalRooms = 0;
$stmtRooms = $con->prepare("SELECT COUNT(*) FROM rooms");
if ($stmtRooms && $stmtRooms->execute()) {
    $stmtRooms->bind_result($totalRooms);
    $stmtRooms->fetch();
    $stmtRooms->close();
}

// استعلام عدد المستخدمين
$totalUsers = 0;
$stmtUsers = $con->prepare("SELECT COUNT(*) FROM tbl_login");
if ($stmtUsers && $stmtUsers->execute()) {
    $stmtUsers->bind_result($totalUsers);
    $stmtUsers->fetch();
    $stmtUsers->close();
}

// استعلام المستخدمين المتصلين
$onlineUsers = 0;
$onlineUserNames = [];
$stmtOnline = $con->prepare("SELECT loginid FROM tbl_login WHERE is_online = 1");
if ($stmtOnline && $stmtOnline->execute()) {
    $result = $stmtOnline->get_result();
    $onlineUsers = $result->num_rows;
    while ($row = $result->fetch_assoc()) {
        $onlineUserNames[] = htmlspecialchars($row['loginid'], ENT_QUOTES, 'UTF-8');
}
    $stmtOnline->close();
}
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fa fa-home fa-2x"></i>
                    <h4>عدد الغرف</h4>
                    <p><?= htmlspecialchars($totalRooms, ENT_QUOTES, 'UTF-8')?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fa fa-users fa-2x"></i>
                    <h4>مستخدمي النظام</h4>
                    <p><?= htmlspecialchars($totalUsers, ENT_QUOTES, 'UTF-8')?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fa fa-dashboard fa-2x"></i>
                    <h4>المستخدمين المتصلين حاليا</h4>
                    <p><?= htmlspecialchars($onlineUsers, ENT_QUOTES, 'UTF-8')?></p>
                    <p><?= implode(", ", $onlineUserNames)?></p>
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
</style>