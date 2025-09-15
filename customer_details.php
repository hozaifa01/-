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
if (!isset($user) || (int)$user['level'] <= 0) {
    echo '<script>alert("عفوا، لا تملك صلاحيات كافية")</script>';
    echo "<script>window.location.href='index.php'</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8')?> - فاتورة العميل</title>
    <?php include_once "header.php";?>
    <style>
        body {
            font-family: 'Tahoma', sans-serif;
            background-color: #f4f4f4;
            padding: 40px;
}
.invoice-box {
            background: #fff;
            padding: 30px;
            border: 1px solid #ccc;
            max-width: 800px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
.invoice-header {
            text-align: center;
            margin-bottom: 30px;
}
.invoice-details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
}
.invoice-details th,.invoice-details td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: right;
}
.invoice-footer,.signature {
            margin-top: 40px;
            text-align: left;
}
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3"><?php include "leftbar.php";?></div>
        <div class="col-md-9">
            <div class="invoice-box">
                <?php
                if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
                    $id = (int) $_GET['id'];

                    $stmt = $con->prepare("SELECT * FROM customers WHERE customer_id =?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows> 0) {
                        $row = $result->fetch_assoc();

                        $check_in = new DateTime($row['check_in']);
                        $check_out = new DateTime($row['check_out']);
                        $days = $check_in->diff($check_out)->days;

                        // جلب الغرف المحجوزة
                        $stmtRooms = $con->prepare("
                            SELECT r.room_number, r.room_type, r.price_per_night
                            FROM booking_rooms br
                            JOIN rooms r ON br.room_id = r.room_id
                            WHERE br.customer_id =?
                        ");

                        $room_rows = '';
                        $total_room_cost = 0;

                        if ($stmtRooms) {
                            $stmtRooms->bind_param("i", $row['customer_id']);
                            $stmtRooms->execute();
                            $rooms_result = $stmtRooms->get_result();

                            while ($room = $rooms_result->fetch_assoc()) {
                                $room_cost = $room['price_per_night'] * $days;
                                $room_rows.= '<tr>
                                    <td>'. htmlspecialchars($room['room_number']). '</td>
                                    <td>'. htmlspecialchars($room['room_type']). '</td>
                                </tr>';
                                $total_room_cost += $room_cost;
}

                            $stmtRooms->close();
} else {
                            $room_rows = '<tr><td colspan="5">تعذر جلب بيانات الغرف</td></tr>';
}
?>
                <button class="btn btn-primary mb-3" onclick="printTable()">طباعة</button>
          <div class="table-responsive">
                <table id="invoiceTable" class="invoice-details">
                    <thead>
                        <tr>
                            <th colspan="2">
                                <div class="invoice-header">
                                    <h2><?= htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8')?> - فاتورة العميل</h2>
                                    <hr>
                                    <p>تاريخ الإصدار: <?= date('Y-m-d');?></p>
                                    <p>#<?= htmlspecialchars($row['customer_id'], ENT_QUOTES, 'UTF-8');?></p>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><th>اسم العميل:</th><td><?= htmlspecialchars($row['first_name']. ' '. $row['last_name'], ENT_QUOTES, 'UTF-8');?></td></tr>
                        <tr><th colspan="2">الغرف المحجوزة:</th></tr>
                        <tr>
                            <td colspan="2">
                                <table style="width:100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th>رقم الغرفة</th>
                                            <th>نوع الغرفة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?= $room_rows;?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr><th>عدد الأيام:</th><td><?= $days;?></td></tr>
                        <tr><th>تاريخ الوصول:</th><td><?= htmlspecialchars($row['come_date'], ENT_QUOTES, 'UTF-8');?></td></tr>
                        <?php
$amount = floatval($row['amount']);
$taxRate = 0.17;
$tourismRate = 0.05;
$totalRate = $taxRate + $tourismRate;

if (isset($_SESSION['level']) && $_SESSION['level'] == 99) {
    $netAmount = $amount * (1 - $totalRate);
    $taxAmount = $amount * $taxRate;
    $tourismAmount = $amount * $tourismRate;
?>
    <tr><th>المبلغ بعد خصم الضرائب (78%):</th><td><?= number_format($netAmount, 2);?> جنيه</td></tr>
    <tr><th>ضريبة القيمة المضافة (17%):</th><td><?= number_format($taxAmount, 2);?> جنيه</td></tr>
    <tr><th>ضريبة السياحة (5%):</th><td><?= number_format($tourismAmount, 2);?> جنيه</td></tr>
        <tr><th>المبلغ الإجمالي:</th><td><?= number_format($amount, 2);?> جنيه</td></tr>
<?php
} else {
?>
    <tr><th>المبلغ الإجمالي:</th><td><?= number_format($amount, 2);?> جنيه</td></tr>
<?php
}
?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>
                                <div class="invoice-footer">
                                    <p>شكرًا لاختياركم خدماتنا. نأمل أن تكون إقامتكم ممتعة.</p>
                                </div>
                            </td>
                            <td>
                                <div class="signature">
                                    <p>تم التوقيع من قبل</p>
                                    <?php
                                    $imagePath = 'uploads/توقيع.png';
                                    if (file_exists($imagePath)) {
                                        $imageData = base64_encode(file_get_contents($imagePath));
                                        $imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
                                        echo '<img src="data:image/'. $imageType. ';base64,'. $imageData. '" alt="توقيع" style="width: 120px;">';
} else {
                                        echo '<p>[توقيع غير متوفر]</p>';
}
?>
                                    <p>المدير المالي: حذيفة أحمد</p>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <?php
} else {
                        echo "<p>لا توجد بيانات للعميل.</p>";
}
                    $stmt->close();
} else {
                    echo "<p>لم يتم تحديد العميل.</p>";
}
?>
            </div>
        </div>
    </div>
</div>

<script>
function printTable() {
    const tableContent = document.getElementById("invoiceTable").outerHTML;
    const printWindow = window.open('', '', 'width=800,height=600');
    printWindow.document.write('<html><head><title>فاتورة</title>');
    printWindow.document.write('<style>*{direction:rtl; text-align:right;} table {width: 100%; border-collapse: collapse;} th, td {border:none; padding: 8px; text-align: right;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(tableContent);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>
<?php include "footer.php";?>
</body>
</html>