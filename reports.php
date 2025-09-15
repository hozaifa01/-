<?php
// إعدادات الجلسة الآمنة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start(['cookie_httponly' => true, 'cookie_secure' => isset($_SERVER['HTTPS']), 'use_strict_mode' => true]);

require_once 'dbconnection.php';
header('Content-Type: charset=utf-8');
// التحقق من الجلسة
if (!isset($_SESSION['aid']) || !filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
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
if (!isset($user) || (int)$user['level'] <= 2) {
    echo '<script>alert("عفوا، لا تملك صلاحيات كافية")</script>';
    echo "<script>window.location.href='index.php'</script>";
    exit();
}
// --- منطق الفلترة ---
$where_clause = " WHERE 1=1 ";
$params = [];
$param_types = '';
$filter_title = "التقارير الشاملة (كل الأوقات)";

if (!empty($_GET['report_date'])) {
    $report_date = $_GET['report_date'];
    $where_clause = " WHERE DATE(come_date) = ? ";
    $params = [$report_date];
    $param_types = 's';
    $filter_title = "تقرير يوم: " . htmlspecialchars($report_date);
} elseif (isset($_GET['current_month'])) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $where_clause = " WHERE come_date BETWEEN ? AND ? ";
    $params = [$start_date, $end_date];
    $param_types = 'ss';
    $filter_title = "تقارير الشهر الحالي (" .formatDate(date('F Y')) . ")";
} elseif (isset($_GET['current_year'])) {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
    $where_clause = " WHERE come_date BETWEEN ? AND ? ";
    $params = [$start_date, $end_date];
    $param_types = 'ss';
    $filter_title = "تقارير السنة الحالية (" . date('Y') . ")";
}
function formatDate($date) {
    $timestamp = strtotime($date);
    $day = date("j", $timestamp);
    $month = date("M", $timestamp);
    $year = date("Y", $timestamp);

    $months = [
        'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس', 'Apr' => 'أبريل',
        'May' => 'مايو', 'Jun' => 'يونيو', 'Jul' => 'يوليو', 'Aug' => 'أغسطس',
        'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر'
    ];

    return $day . " " . $months[$month] . " " . $year;
}
// --- استعلام موحد للبيانات المالية حسب الفلتر ---
$financial_summary = [
    'total_bookings' => 0,
    'total_revenue' => 0,
    'total_tax' => 0,
    'total_discount' => 0,
];

$sql_financial = "SELECT 
                    COUNT(*) as total_bookings,
                    IFNULL(SUM(amount), 0) as total_revenue,
                    IFNULL(SUM(amount * tax / 100), 0) as total_tax,
                    IFNULL(SUM(amount * discount / 100), 0) as total_discount
                  FROM customers" . $where_clause;

$stmt = $con->prepare($sql_financial);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $financial_summary = $result->fetch_assoc();
}
$stmt->close();

// --- استعلام لبيانات الرسم البياني اليومي ---
$daily_labels = [];
$daily_revenue_data = [];
$daily_tax_data = [];

$sql_daily = "SELECT 
                DATE(come_date) as day, 
                SUM(amount) as daily_revenue, 
                SUM(amount * tax / 100) as daily_tax
              FROM customers" . $where_clause . " GROUP BY DATE(come_date) ORDER BY day ASC";
              
$stmt_daily = $con->prepare($sql_daily);
if (!empty($params)) {
    $stmt_daily->bind_param($param_types, ...$params);
}
$stmt_daily->execute();
$result_daily = $stmt_daily->get_result();
while ($row = $result_daily->fetch_assoc()) {
    $daily_labels[] = $row['day'];
    $daily_revenue_data[] = $row['daily_revenue'];
    $daily_tax_data[] = $row['daily_tax'];
}
$stmt_daily->close();

$sql_payment = "SELECT payment_method, SUM(amount) AS total_amount FROM customers". $where_clause. " GROUP BY payment_method";
$stmt_payment = $con->prepare($sql_payment);

if (!empty($params)) {
    $stmt_payment->bind_param($param_types,...$params);
}

$stmt_payment->execute();
$result_payment = $stmt_payment->get_result();

$paymentLabels = [];
$paymentValues = [];
$paymentCounts = [];

$sql_payment = "SELECT payment_method, COUNT(*) AS count, SUM(amount) AS total_amount
                FROM customers". $where_clause. "
                GROUP BY payment_method";

$stmt_payment = $con->prepare($sql_payment);
if (!empty($params)) {
    $stmt_payment->bind_param($param_types,...$params);
}
$stmt_payment->execute();
$result_payment = $stmt_payment->get_result();

while ($row = $result_payment->fetch_assoc()) {
    $paymentLabels[] = $row['payment_method'];
    $paymentValues[] = round($row['total_amount'], 2);
    $paymentCounts[] = $row['count'];
}
$stmt_payment->close();
// --- استعلام حالة الغرف (دائماً يعرض الحالة الحالية) ---
$room_counts = ['متاحة' => 0, 'محجوزة' => 0, 'total' => 0];
$sql_rooms = "SELECT current_status, COUNT(*) as count FROM rooms GROUP BY current_status";
$result_rooms = $con->query($sql_rooms);
while ($row = $result_rooms->fetch_assoc()) {
    if (isset($room_counts[$row['current_status']])) {
        $room_counts[$row['current_status']] = $row['count'];
    }
}
$room_counts['total'] = $room_counts['متاحة'] + $room_counts['محجوزة'];
$room_status_labels = ['متاحة', 'محجوزة'];
$room_status_values = [$room_counts['متاحة'], $room_counts['محجوزة']];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8')?> - التقارير</title>
  <?php include "header.php";?>
</head>
<body class="p-4">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3"><?php include "leftbar.php";?></div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">📊 التقارير</h2>
                <button class="btn btn-outline-primary" onclick="generatePDF()"><i class="fa fa-file-pdf-o"></i> تصدير إلى PDF</button>
            </div>

            <!-- قسم الفلترة -->
            <div class="card mb-4">
                <div class="card-header">
                    فلترة التقارير
                </div>
              <div class="table-responsive" ><div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label for="report_date" class="form-label">عرض تقرير لتاريخ محدد:</label>
                            <input type="date" name="report_date" id="report_date" class="form-control" value="<?= htmlspecialchars($_GET['report_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">عرض التقرير</button>
                            <a href="reports.php?current_month=1" class="btn btn-info me-2">الشهر الحالي</a>
                            <a href="reports.php?current_year=1" class="btn btn-warning me-2">السنة الحالية</a>
                            <a href="reports.php" class="btn btn-secondary">عرض الكل</a>
                        </div>
                    </form>
                </div></div>
            </div>

            <!-- هنا يبدأ المحتوى الذي سيتم تصديره -->
            <div id="reportContent">
                <h3 class="mb-4 text-center p-3 bg-custom rounded"><?= htmlspecialchars($filter_title) ?></h3>

                <!-- البطاقات العلوية -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="card text-center p-3 shadow-sm"><h6>إجمالي الحجوزات</h6><h3 class="text-primary"><?= $financial_summary['total_bookings'] ?></h3></div></div>
                    <div class="col-md-3"><div class="card text-center p-3
                    shadow-sm"><h6>إجمالي مبيعات</h6><h3 class="text-success"><?=
                    number_format($financial_summary['total_revenue'], 2) ?>
                    $</h3></div></div>
                    <div class="col-md-3"><div class="card text-center p-3
                    shadow-sm"><h6>اساس الضريبة 78% </h6><h3
                    class="text-danger"><?=
                    number_format($financial_summary['total_revenue']*(1-0.22), 2) ?>
                    $</h3></div></div>
                    <div class="col-md-3"><div class="card text-center p-3 shadow-sm"><h6>إجمالي الخصومات</h6><h3 class="text-warning"><?= number_format($financial_summary['total_discount'], 2) ?> $</h3></div></div>
                </div>

                <!-- الرسم البياني اليومي وتقرير الغرف -->
                <div class="row g-4 mb-4">
                    <div class="col-md-8">
            <div class="card p-3 shadow-sm mb-4">
    <h6><i class="fa fa-bar-chart"></i> إجمالي المبالغ حسب طرق الدفع</h6>
    <canvas id="paymentBarChart"></canvas>
 <div class="card p-3 shadow-sm mb-4">
    <h6><i class="fa fa-credit-card"></i> طرق الدفع المستخدمة</h6>

    <ul class="list-group list-group-flush mt-3">
        <?php for ($i = 0; $i < count($paymentLabels); $i++):?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($paymentLabels[$i], ENT_QUOTES, 'UTF-8')?>
                <span class="badge bg-custom rounded-pill">
                    <?= number_format($paymentCounts[$i])?> عملية / <?= number_format($paymentValues[$i], 2)?> ج
                </span>
            </li>
        <?php endfor;?>
    </ul>
</div>                       </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 shadow-sm h-100">
                             <h6><i class="fa fa-pie-chart"></i> تقرير حالة الغرف (الحالية)</h6>
                            <canvas id="roomStatusChart"></canvas>
                            <ul class="list-group list-group-flush mt-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">متاحة <span class="badge bg-success rounded-pill"><?= $room_counts['متاحة'] ?></span></li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">محجوزة <span class="badge bg-danger rounded-pill"><?= $room_counts['محجوزة'] ?></span></li>
                                <li class="list-group-item d-flex
                                justify-content-between align-items-center
                                fw-bold">الإجمالي <span class="badge bg-custom rounded-pill"><?= $room_counts['total']
                                ?></span></li>
                            </ul>
                        
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>

<?php include "footer.php";?>
<script>
// الرسم البياني اليومي للإيرادات والضرائب
const ctxDaily = document.getElementById('dailyFinancialChart');
new Chart(ctxDaily, {
  type: 'line',
  data: {
    labels: <?= json_encode($daily_labels) ?>,
    datasets: [{
      label: 'الإيرادات اليومية',
      data: <?= json_encode($daily_revenue_data) ?>,
      borderColor: 'rgba(75, 192, 192, 1)',
      backgroundColor: 'rgba(75, 192, 192, 0.2)',
      fill: true,
      tension: 0.1
    },
    {
      label: 'الضرائب اليومية',
      data: <?= json_encode($daily_tax_data) ?>,
      borderColor: 'rgba(255, 99, 132, 1)',
      backgroundColor: 'rgba(255, 99, 132, 0.2)',
      fill: true,
      tension: 0.1
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top' },
      title: { display: false }
    }
  }
});

// الرسم البياني لحالة الغرف
const ctxRooms = document.getElementById('roomStatusChart');
new Chart(ctxRooms, {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($room_status_labels) ?>,
    datasets: [{
      label: 'حالة الغرف',
      data: <?= json_encode($room_status_values) ?>,
      backgroundColor: ['#28a745', '#dc3545'],
      hoverOffset: 4
    }]
  }
});

// وظيفة تصدير PDF
function generatePDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF('p', 'pt', 'a4');
  
  // لضمان دعم اللغة العربية، يفضل استخدام خط يدعمها
  // هذه الخطوة تتطلب تضمين الخط في المشروع
  // doc.addFont('Amiri-Regular.ttf', 'Amiri', 'normal');
  // doc.setFont('Amiri');

  const element = document.getElementById("reportContent");

  html2canvas(element, {
    scale: 2, // جودة أعلى
    useCORS: true,
    // السماح بتقسيم المحتوى الطويل على عدة صفحات
    windowHeight: element.scrollHeight 
  }).then(canvas => {
    const imgData = canvas.toDataURL("image/png");
    const pageWidth = doc.internal.pageSize.getWidth();
    // const pageHeight = doc.internal.pageSize.getHeight(); // غير مستخدم مباشرة هنا

    // حساب الأبعاد للحفاظ على نسبة العرض إلى الارتفاع
    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;
    const ratio = canvasWidth / canvasHeight;
    const imgWidth = pageWidth - 40; // مع هوامش
    const imgHeight = imgWidth / ratio;
    
    let heightLeft = imgHeight;
    let position = 20; // الهامش العلوي

    doc.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight);
    heightLeft -= doc.internal.pageSize.getHeight();

    // إضافة صفحات جديدة إذا كان المحتوى أطول من صفحة واحدة
    while (heightLeft > 0) {
      position = -imgHeight + heightLeft;
      doc.addPage();
      doc.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight);
      heightLeft -= doc.internal.pageSize.getHeight();
    }
    
    doc.save("تقرير-الفترة-المحددة.pdf");
  });
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('paymentBarChart').getContext('2d');

    const labels = <?= json_encode($paymentLabels, JSON_UNESCAPED_UNICODE);?>;
    const data = <?= json_encode($paymentValues);?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'إجمالي المبلغ (جنيه)',
                data: data,
                backgroundColor:['#34aaf7', '#065589'],
                borderColor: '#0056b3',
                borderWidth: 1
}]
},
        options: {
            responsive: true,
            plugins: {
                legend: { display: false},
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return `${context.label}: ${context.parsed.toLocaleString()} جنيه`;
}
}
}
},
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString() + ' ج';
}
}
}
}
}
});
});
</script>
</body>
</html>