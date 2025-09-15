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

require_once "dbconnection.php";

// التحقق من الجلسة
if (!isset($_SESSION['aid']) || !filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
    header("Location: logout.php");
    exit();
}
$aid = (int) $_SESSION['aid'];

// جلب مستوى المستخدم باستخدام Prepared Statement
$stmt = $con->prepare("SELECT level, FullName FROM tbl_login WHERE id = ?");
$stmt->bind_param("i", $aid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_level = $user['level'];
$stmt->close();

// حذف الحجز بأمان مع التحقق من الصلاحيات
if (($user_level === 99 || $user_level === 2) && isset($_GET['del'])) {
    $userid = filter_var($_GET['del'], FILTER_VALIDATE_INT);
    if ($userid) {
        $stmt = $con->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $userid);
        if ($stmt->execute()) {
            echo '<script>alert("تم حذف الحجز بنجاح")</script>';
            echo '<script>window.location.href="bookings.php"</script>';
        }
        $stmt->close();
    }
}

// --- منطق سجل الإيرادات والضريبة اليومية المخفي (تم تصحيحه) ---
$today_revenue = 0.00;
$tax_base_40_percent = 0.00;
$tax_deduction_17_percent = 0.00;
$final_tax_amount = 0.00;
$today_date = date('Y-m-d');

if ($user_level >=2) {
    // الخطوة 1: حساب إجمالي الإيرادات لليوم (من حقل come_date - تاريخ الدفع)
    $stmt_revenue = $con->prepare("SELECT SUM(amount) AS total_revenue FROM customers WHERE DATE(come_date) = ?");
    $stmt_revenue->bind_param("s", $today_date);
    $stmt_revenue->execute();
    $revenue_result = $stmt_revenue->get_result();
    
    if ($revenue_result) {
        $revenue_row = $revenue_result->fetch_assoc();
        $today_revenue = (float)($revenue_row['total_revenue'] ?? 0.00);
        
        // تسجيل في سجل الأخطاء للتحقق
        error_log("Today revenue for $today_date: $today_revenue");
    } else {
        error_log("Error in revenue query: " . $con->error);
        $today_revenue = 0.00;
    }
    
    $stmt_revenue->close();

    // --- المنطق الحسابي الجديد للضريبة ---
    // الخطوة 2: حساب أساس الضريبة (40% من الإيرادات)
    $tax_base_40_percent = $today_revenue  /1.22;
    // الخطوة 3: حساب الخصم (17% من أساس الضريبة)
    $tax_deduction_17_percent = $tax_base_40_percent * 0.22;
    // الخطوة 4: حساب صافي الضريبة المستحقة
    $final_tax_amount = $tax_base_40_percent - $tax_deduction_17_percent;
    // --- نهاية المنطق الحسابي الجديد ---

    // معالجة تسجيل/تحديث بيانات الضريبة لليوم في قاعدة البيانات
    if (isset($_POST['log_today_tax'])) {
        $stmt_check = $con->prepare("SELECT id FROM daily_revenue_tax_log WHERE log_date = ?");
        $stmt_check->bind_param("s", $today_date);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        
        if ($check_result->num_rows > 0) {
            $stmt_update = $con->prepare("UPDATE daily_revenue_tax_log SET daily_revenue = ?, daily_tax = ?, logged_at = NOW() WHERE log_date = ?");
            $stmt_update->bind_param("dds", $today_revenue, $final_tax_amount, $today_date);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $stmt_insert = $con->prepare("INSERT INTO daily_revenue_tax_log (log_date, daily_revenue, daily_tax) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sdd", $today_date, $today_revenue, $final_tax_amount);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();
        echo '<script>alert("تم تسجيل/تحديث ضريبة اليوم بنجاح."); window.location.href="bookings.php";</script>';
        exit();
    }

    // معالجة حذف سجل ضريبي فردي
    if (isset($_GET['del_log_id'])) {
        $log_id = filter_var($_GET['del_log_id'], FILTER_VALIDATE_INT);
        if ($log_id) {
            $stmt_delete_log = $con->prepare("DELETE FROM daily_revenue_tax_log WHERE id = ?");
            $stmt_delete_log->bind_param("i", $log_id);
            $stmt_delete_log->execute();
            $stmt_delete_log->close();
            echo '<script>alert("تم حذف السجل الضريبي بنجاح."); window.location.href="bookings.php";</script>';
            exit();
        }
    }
}
// --- نهاية منطق سجل الإيرادات والضريبة ---

// استعلامات عرض السجلات حسب الفلاتر (بدون تغيير)
$customers_query = null;

if (isset($_GET['month_data'])) {
    $current_month = date('m');
    $current_year = date('Y');
    $stmt = $con->prepare("SELECT * FROM customers WHERE MONTH(check_in) = ? AND YEAR(check_in) = ? ORDER BY customer_id DESC");
    $stmt->bind_param("ii", $current_month, $current_year);
    $stmt->execute();
    $customers_query = $stmt->get_result();
    $stmt->close();
} elseif (isset($_GET['all_data'])) {
    $customers_query = $con->query("SELECT * FROM customers ORDER BY customer_id DESC");
} elseif (isset($_GET['search_date'])) {
    $search_date = $_GET['search_date'];
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $search_date)) {
        $year = date('Y', strtotime($search_date));
        $month = date('m', strtotime($search_date));
        $stmt = $con->prepare("SELECT * FROM customers WHERE YEAR(check_in) = ? AND MONTH(check_in) = ? ORDER BY customer_id DESC");
        $stmt->bind_param("ii", $year, $month);
        $stmt->execute();
        $customers_query = $stmt->get_result();
        $stmt->close();
    }
} elseif (isset($_GET['search_date_range'])) {
    $date_from = $_GET['date_from'];
    $date_to = $_GET['date_to'];
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_from) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_to)) {
        $stmt = $con->prepare("SELECT * FROM customers WHERE check_in BETWEEN ? AND ? ORDER BY customer_id DESC");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $customers_query = $stmt->get_result();
        $stmt->close();
    }
} else {
    $customers_query = $con->query("SELECT * FROM customers ORDER BY customer_id DESC LIMIT 25");
}
if (isset($_GET['day_data'])) {
    $current_day = date('d');
    $current_month = date('m');
    $current_year = date('Y');
    $stmt = $con->prepare("SELECT * FROM customers WHERE MONTH(check_in) = ? AND
    YEAR(check_in) = ? AND DAY(check_in) = ? ORDER BY customer_id DESC");
    $stmt->bind_param("iii", $current_month, $current_year,$current_day);
    $stmt->execute();
    $customers_query = $stmt->get_result();
    $stmt->close();
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
// التحقق من صلاحية المدير
$stmt = $con->prepare("SELECT level FROM tbl_login WHERE id =?");
$stmt->bind_param("i", $aid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!isset($user) || (int)$user['level']!== 99) {
    echo '<script>alert("عفوا، لا تملك صلاحيات كافية")</script>';
    echo "<script>window.location.href='index.php'</script>";
    exit();
}
?>

<?php include_once "header.php";?>
<title><?php echo htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8');?> -
مخفي الحجوزات والعملاء</title>
</head>
<body class="p-4">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include "leftbar.php";?>
        </div>
        <div class="col-md-9">
            <h2 class="mb-4">الحجوزات والعملاء</h2>
            <hr />

            <?php if ($user_level === 99 || $user_level === 2):?>
            <!-- قسم سجل الإيرادات والضريبة اليومية المخفي - تم تحديث العرض -->
            <div class="card mb-4 border-info shadow">
                <div class="card-header bg-info ">
                    <h5 class="mb-0"><i class="fa fa-calculator"></i> سجل
                    المبيعات اليومي </h5>
                </div>
                <div class="card-body">
                    <p class="lead"><strong>التاريخ الحالي:</strong> <?php echo htmlspecialchars(formatDate($today_date));?></p>
                    <hr>
                    <h6 class="card-title">تفاصيل الحسابات لليوم:</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                   اجمالي المبيعات + اساس الضريبة = الاجمالي *120% 
                            <span class="text-white badge bg-primary rounded-pill fs-6"><?php echo number_format($tax_base_40_percent, 2);?> جنيه</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            الخصم من الضريبة (17% من الأساس +5% سياحة)
                            <span class="text-white badge bg-info text-dark rounded-pill fs-6">- <?php echo number_format($tax_deduction_17_percent, 2);?> جنيه</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">
                   الإجمالي
                            <span class="text-white badge bg-danger rounded-pill fs-5"><?php echo number_format($today_revenue, 2);?> جنيه</span>
                        </li>
                    </ul>

                    <form method="POST" class="d-inline-block mt-4">
                        <button type="submit" name="log_today_tax" class="btn
                        btn-outline-primary badge" onclick="return confirm('هل أنت متأكد من
                        تسجيل/تحديث ضريبة اليوم بالقيمة النهائية المحسوبة؟');">
                            <i class="fa fa-save"></i> تسجيل/تحديث ضريبة اليوم
                        </button>
                    </form>
                    <button class="btn btn-outline-info badge mt-4" type="button" data-bs-toggle="collapse" data-bs-target="#pastLogsCollapse" aria-expanded="false" aria-controls="pastLogsCollapse">
                        <i class="fa fa-history"></i> عرض السجلات السابقة
                    </button>

                    <div class="collapse mt-4" id="pastLogsCollapse">
                        <h5>السجلات الضريبية السابقة:</h5>
                        <table class="table table-bordered table-sm table-hover" id="dailyTaxLogsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>التاريخ</th>
                                    <th>الإيرادات المسجلة</th>
                                    <th> اجمالي المبيعات </th>
                                    <th>وقت التسجيل</th>
                                    <th>أدوات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt_logs = $con->query("SELECT id, log_date, daily_revenue, daily_tax, logged_at FROM daily_revenue_tax_log ORDER BY log_date DESC LIMIT 20");
                                if ($stmt_logs && $stmt_logs->num_rows > 0) {
                                    while ($log = $stmt_logs->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars(formatDate($log['log_date'])) . '</td>';
                                        echo '<td>' . number_format($log['daily_revenue'], 2) . '</td>';
                                        echo '<td>' . number_format($log['daily_tax'], 2) . '</td>';
                                        echo '<td>' . htmlspecialchars($log['logged_at']) . '</td>';
                                        echo '<td>';
                                        echo '<a href="bookings.php?del_log_id='
                                        . htmlspecialchars($log['id']) . '"
                                        class="btn btn-danger btn-sm me-1"
                                        onclick="return confirm(\'هل أنت متأكد
                                        من حذف هذا السجل؟\');"><i class="fa
                                        fa-trash"></i></a>';
                                        echo '<button type="button" class="btn btn-outline-info btn-sm print-log-btn" data-log-id="' . htmlspecialchars($log['id']) . '" data-log-date="' . htmlspecialchars(formatDate($log['log_date'])) . '" data-log-revenue="' . number_format($log['daily_revenue'], 2) . '" data-log-tax="' . number_format($log['daily_tax'], 2) . '" data-log-timestamp="' . htmlspecialchars($log['logged_at']) . '"><i class="fa fa-print"></i></button>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">لا توجد سجلات سابقة.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!-- نهاية قسم سجل الإيرادات والضريبة اليومية المخفي -->

            <div class="ui-widget-header text-center p-3 mb-3 border rounded shadow-sm">
                <h2>السجلات المراد عرضها</h2>
                <form method="GET" class="mt-3">
                    <div class="row align-items-center mb-2">
                        <label for="search_date" class="col-md-2 col-form-label text-md-end">حسب السنة والشهر:</label>
                        <div class="col-md-4">
                            <input type="date" name="search_date" class="form-control ui-corner-all" placeholder="اختر الشهر والسنة">
                        </div>
                        <div class="col-md-6 text-md-start">
                            <button type="submit" class="btn btn-outline-primary me-2">عرض</button>
                            <button name="all_data" type="submit" class="btn btn-outline-success me-2">كل السجلات</button>
                            <button name="month_data" type="submit" class="btn btn-outline-danger">الشهر الحالي</button>
                            <button name="day_data" type="submit" class="btn
                            btn-outline-success">اليوم الحالي</button>
                        </div>
                    </div>
                    <div class="row align-items-center">
                        <label class="col-md-2 col-form-label text-md-end">بحث حسب الفترة الزمنية:</label>
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control ui-corner-all" placeholder="من">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control ui-corner-all" placeholder="إلى">
                        </div>
                        <div class="col-md-6 text-md-start">
                            <button name="search_date_range" type="submit" class="btn btn-outline-primary">عرض</button>
                        </div>
                    </div>
                </form>
            </div>

            <a href="add_booking.php" class="btn btn-outline-success mb-3 mt-3">+ إضافة حجز</a>
<div class="table-responsive">
            <table id="customersTable" class="table table-striped table-hover w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم العميل</th>
                       <th> المبلغ الإجمالي 

                        <th>المبلغ ٧٨٪</th>
                        
                        <th> سياحة ٥٪ </th>
                        <th>ضريبة ١٧٪ </th>
                             <th>تفاصيل الغرفة</th>
                        <th>طريقة الدفع</th>
                        <th>رقم الحساب</th>
                                       
                        <th>تاريخ الوصول</th>
                        <th>تاريخ الانصراف</th>
                        <th>عدد أيام الحجز</th>
                        <th>المتبقي لانتهاء الحجز</th>
                        <th>هاتف</th>
                        <th>No</th>
                        <th>أدوات</th>
                    </tr>
                </thead>
<tbody>
<?php
$sn = 1;
if ($customers_query && $customers_query->num_rows> 0) {
    while ($post = $customers_query->fetch_assoc()) {
        $check_in = strtotime($post['check_in']);
        $check_out = strtotime($post['check_out']. ' 12:00:00');
$now = new DateTime();
$now->setTime(12, 0);
$current_time = $now->getTimestamp();

$remaining_days = floor(($check_out - $current_time) / (60 * 60 * 24));

        // جلب بيانات الغرف المرتبطة بالعميل
        $stmt_get_rooms = $con->prepare("
            SELECT r.room_number, r.room_type
            FROM booking_rooms br
            JOIN rooms r ON br.room_id = r.room_id
            WHERE br.customer_id =?
        ");

        $rooms = '';
        if ($stmt_get_rooms) {
            $stmt_get_rooms->bind_param("i", $post['customer_id']);
            $stmt_get_rooms->execute();
            $rooms_result = $stmt_get_rooms->get_result();
            while ($room_row = $rooms_result->fetch_assoc()) {
                $rooms.= '<span class="text-dark badge bg-custom me-1">'
. htmlspecialchars($room_row['room_number']). ' - '
. htmlspecialchars($room_row['room_type']). '</span><br>';
}
            $stmt_get_rooms->close();
} else {
            $rooms = '<span class="text-danger">تعذر جلب الغرف</span>';
}
$check_out = strtotime($post['check_out']. ' 12:00:00');
$now = new DateTime();
$now->setTime(12, 0);
$current_time = $now->getTimestamp();

$remaining_days = floor(($check_out - $current_time) / (60 * 60 * 24));
?>
    <tr>
        <td><?= htmlspecialchars($sn++);?></td>
        <td><?php echo htmlspecialchars($post['first_name']). ' '. htmlspecialchars($post['last_name']);?></td>
<?php if (isset($post['amount']) && is_numeric($post['amount'])):?>
    <?php
    $amount_last = floatval($post['amount']); // تحويل آمن إلى رقم عشري
?>
    <td><?php echo htmlspecialchars(number_format($amount_last, 2));?></td>

    <?php
    $taxRate = 0.17;
    $tourismRate = 0.05;
    $totalRate = $taxRate + $tourismRate;

    $net_amount = $amount_last * (1 - $totalRate);
    $tax_value = $amount_last * $taxRate;
    $tourism_value = $amount_last * $tourismRate;
?>

    <td><?php echo htmlspecialchars(number_format($net_amount, 2));?></td>
    <td><?php echo htmlspecialchars(number_format($tourism_value, 2));?></td>
    <td><?php echo htmlspecialchars(number_format($tax_value, 2));?></td>
<?php else:?>
    <td colspan="4">المبلغ غير صالح أو غير موجود</td>
<?php endif;?>
        <td><?php echo $rooms;?></td>
        <td><?php echo htmlspecialchars($post['payment_method']);?></td>
        <td><?php echo htmlspecialchars($post['bank_account']);?></td>
        <td><?php echo htmlspecialchars(formatDate($post['check_in']));?></td>
        <td><?php echo htmlspecialchars(formatDate($post['check_out']));?></td>
    <td>
  <?php
    $check_in = new DateTime($post['check_in']);
    $check_out = new DateTime($post['check_out']);
    $interval = $check_in->diff($check_out);
    echo $interval->days. ' يوم';
?>
</td>
        <td><?php echo ($remaining_days > 0)? $remaining_days. ' يوم': 'انتهى';?></td>
    
        <td><?php echo htmlspecialchars($post['phone_number']);?></td>
        <td>#<?php echo htmlspecialchars($post['customer_id']);?></td>
        <td>
            <a href="edit_booking.php?id=<?php echo htmlspecialchars($post['customer_id']);?>" class="btn btn-outline-primary btn-sm mb-1"><i class="fa fa-edit"></i> تعديل</a>
            <a href="customer_details.php?id=<?php echo htmlspecialchars($post['customer_id']);?>" class="btn btn-info btn-sm mb-1"><i class="fa fa-print"></i> فاتورة</a>
            <?php if ($user_level === 99 || $user_level === 2):?>
            <a href="bookings.php?del=<?php echo htmlspecialchars($post['customer_id']);?>" class="btn btn-outline-danger btn-sm mb-1" onclick="return confirm('هل أنت متأكد من رغبتك بالحذف؟');"><i class="fa fa-recycle"></i> حذف</a>
            <?php endif;?>
        </td>
    </tr>
<?php
}
} else {
    echo '<tr><td colspan="15" class="text-center">لا توجد سجلات لعرضها.</td></tr>';
}
?>
</tbody> 
<tfoot>
  <th></th>
  <th>الإجمالي :</th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
  <th></th>
</tfoot>
</table>
        </div>
        </div>
    </div>
</div>
<?php include_once "footer.php";?>

<script>
  $.fn.dataTable.ext.errMode = 'none';
document.addEventListener('DOMContentLoaded', function () {
    // Initialize DataTables for the main customers table
    $('#customersTable').DataTable({
        responsive: true,
        serverSide: false,
        processing:true,
        lengthChange: true,
        select: {
            style: 'multi'
        },
        keys: true,
        colReorder: false,
        searching: true,
        paging: true,
        info: true,
        fixedHeader: true,
        stateSave: true,
        pageLength: 10,
  lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"] ],
        dom: 'Bfrtip,<"buttom"l>rt<"bottom"ip><"clear">',
        buttons: [
            'copy',
            {
                extend: 'excelHtml5',
                text: 'Excel',
                filename: 'Customer_Bookings_Excel',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdfHtml5',
                text: 'PDF',
                filename: 'Customer_Bookings_PDF',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function (doc) {
                    doc.defaultStyle.font = 'Amiri-Regular';
                    doc.defaultStyle.alignment = 'right';
                    doc.styles.tableHeader.alignment = 'right';
                    
                    if (doc.content[1] && doc.content[1].table) {
                        if (doc.content[1].table.body.length > 0) {
                            doc.content[1].table.body[0].reverse();
                        }
                        for (let i = 1; i < doc.content[1].table.body.length; i++) {
                            doc.content[1].table.body[i].reverse();
                        }
                    }
                }
            },
            'print',
            'colvis'
        ],
        language: {
            select: {
    rows: {
      _: "تم تحديد %d صف",
      0: "لم يتم تحديد أي صف",
      1: "تم تحديد صف واحد"
}
},
            search: 'بحث:',
            info: 'عرض _START_ إلى _END_ من _TOTAL_ صفحة',
            infoEmpty: 'لا توجد سجلات',
            infoFiltered: '(مفلترة من إجمالي _MAX_ السجلات)',
            lengthMenu: 'عرض _MENU_ سجلات',
            loadingRecords: 'تحميل...',
            processing: 'معالجة...',
            zeroRecords: 'لا توجد سجلات مطابقة',
            paginate: {
                first: 'الأول',
                last: 'الأخير',
                next: 'التالي',
                previous: 'السابق'
            },
            aria: {
                sortAscending: ': ترتيب تصاعدي',
                sortDescending: ': ترتيب تنازلي'
            },
            buttons: {
                copy: 'نسخ',
                csv: 'CSV',
                excel: 'Excel',
                pdf: 'PDF',
                print: 'طباعة',
                colvis: 'عرض/إخفاء الأعمدة'
            }
        },
        // إضافة دالة الجمع هنا
        footerCallback: function (row, data, start, end, display) {
    var api = this.api();

    // تحقق من أن الجدول يحتوي على بيانات
    if (api.data().count() === 0) {
        // امسح محتوى الفوتر أو ضع رسالة مخصصة
        api.columns().every(function () {
            $(api.column(this.index()).footer()).html('<strong>—</strong>');
});
        return; // أوقف التنفيذ
}

    var intVal = function (i) {
        if (typeof i === 'string') {
            return parseFloat(i.replace(/[^\d.-]/g, '')) || 0;
} else if (typeof i === 'number') {
            return i;
}
        return 0;
};

    var columnsToSum = [2, 3, 4, 5];

    columnsToSum.forEach(function (colIndex) {
        var selectedRows = api.rows({ selected: true}).data();
        var pageData = api.column(colIndex, { page: 'current'}).data();

        var selectedTotal = 0;
        if (selectedRows.length> 0) {
            selectedRows.each(function (rowData) {
                if (Array.isArray(rowData) && rowData.length> colIndex) {
                    selectedTotal += intVal(rowData[colIndex]);
}
});
}

        var pageTotal = 0;
        if (pageData.length> 0) {
            pageData.each(function (val) {
                pageTotal += intVal(val);
});
}

        var formattedSelected = selectedTotal.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
});

        var formattedPage = pageTotal.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
});

        $(api.column(colIndex).footer()).html(
            `<strong>المحدد: ${formattedSelected}   ج <br>الصفحة: ${formattedPage}  ج </strong>`
);
});
}
    });
var table = $('#customersTable').DataTable();

table.on('select deselect', function () {
    table.draw(); // يعيد تنفيذ footerCallback تلقائيًا
});
    // Initialize DataTables for daily tax logs table
    // It's important to do this *after* the collapse element might be expanded
    // to ensure DataTables calculates dimensions correctly.
    // We can re-initialize or redraw if the collapse is dynamically loaded/shown.
    const dailyTaxLogsTable = $('#dailyTaxLogsTable').DataTable({
        responsive: true,
        paging: false, // No pagination for a small log table
        searching: false, // No search for a small log table
        info: false, // No info for a small log table
        ordering: false, // No ordering for a small log table, data is ordered by PHP
        language: {
            zeroRecords: 'لا توجد سجلات سابقة.',
            emptyTable: 'لا توجد سجلات سابقة.'
        }
    });

    // If the collapse content is loaded dynamically or takes time to render,
    // you might need to redraw the DataTable when it becomes visible.
    $('#pastLogsCollapse').on('shown.bs.collapse', function () {
        dailyTaxLogsTable.columns.adjust().draw();
    });
});

// Function to print a single log entry using html2canvas and jsPDF
$(document).on('click', '.print-log-btn', function() {
    const logId = $(this).data('log-id');
    const logDate = $(this).data('log-date');
    const logRevenue = $(this).data('log-revenue');
    const logTax = $(this).data('log-tax');
    const logTimestamp = $(this).data('log-timestamp');

    // Make sure jspdf is loaded
    if (typeof jspdf === 'undefined' || !jspdf.jsPDF) {
        alert('مكتبة jspdf غير محملة. الرجاء التأكد من تضمينها بشكل صحيح في ملف header.php أو قبل هذا السكريبت.');
        return;
    }
    // Make sure html2canvas is loaded
    if (typeof html2canvas === 'undefined') {
        alert('مكتبة html2canvas غير محملة. الرجاء التأكد من تضمينها بشكل صحيح في ملف header.php أو قبل هذا السكريبت.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'p', // Portrait
        unit: 'mm',
        format: 'a4',
    });

    // Create a temporary hidden element to render with html2canvas (more robust for complex scripts like Arabic)
    const printContent = `
        <div style="font-family: 'Amiri', 'Arial', sans-serif; text-align: right; direction: rtl; padding: 20px;">
            <h2 style="text-align: center; margin-bottom: 20px;">سجل ضريبي يومي</h2>
            <p style="margin-bottom: 10px;"><strong>معرف السجل:</strong> ${logId}</p>
            <p style="margin-bottom: 10px;"><strong>التاريخ:</strong> ${logDate}</p>
            <p style="margin-bottom: 10px;"><strong>إجمالي الإيرادات:</strong> ${logRevenue} جنيه</p>
            <p style="margin-bottom: 10px;"><strong>الضريبة المحسوبة (40%):</strong> ${logTax} جنيه</p>
            <p style="margin-bottom: 10px;"><strong>وقت التسجيل:</strong> ${logTimestamp}</p>
            <br>
            <p style="text-align: center; margin-top: 30px;">--- نهاية السجل ---</p>
        </div>
    `;

    // Create a temporary div, append content, use html2canvas, then add to jsPDF
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = printContent;
    tempDiv.style.position = 'absolute';
    tempDiv.style.left = '-9999px'; // Hide it off-screen
    tempDiv.style.width = '210mm'; // Set width for A4, important for html2canvas to render correctly for PDF page
    document.body.appendChild(tempDiv);

    html2canvas(tempDiv, {
        scale: 2, // Increase scale for better quality
        useCORS: true, // if there are external images
        scrollY: -window.scrollY // Capture content from top of the document
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 190; // A4 width minus margins (210 - 2*10)
        const pageHeight = doc.internal.pageSize.height;
        let imgHeight = canvas.height * imgWidth / canvas.width;

        let heightLeft = imgHeight;
        let position = 10; // Initial Y position for content

        doc.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
        heightLeft -= (pageHeight - position); // Account for initial position used

        while (heightLeft > 0) {
            position = 10; // Reset position for new page
            doc.addPage();
            doc.addImage(imgData, 'PNG', 10, position - imgHeight + heightLeft, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        doc.save(`سجل_ضريبي_${logId}.pdf`);
        document.body.removeChild(tempDiv); // Clean up
    }).catch(error => {
        console.error("Error generating PDF:", error);
        alert('حدث خطأ أثناء إنشاء ملف PDF: ' + error.message);
        document.body.removeChild(tempDiv); // Clean up even on error
    });
});

</script>
</body>
</html>