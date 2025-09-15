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
  $day = date("j", $timestamp);
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
?>

<?php include_once "header.php";?>
<title><?php echo htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8');?> - الحجوزات والعملاء</title>
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
                        <th>تفاصيل الغرفة</th>
                        <th>طريقة الدفع</th>
                        <th>رقم الحساب</th>
                        <th>المبلغ</th>
                        <th>تاريخ الوصول</th>
                        <th>تاريخ الانصراف</th>
                        <th>عدد أيام الحجز</th>
                        <th>المتبقي لانتهاء الحجز</th>
                        <th>تخفيض ٪</th>
                        <th>ضريبة ٪</th>
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

if ($customers_query && $customers_query->num_rows> 0) {
    // عرض الصفوف
} else {
    echo '<tr><td colspan="15" class="text-center">لا توجد سجلات لعرضها.</td></tr>';
}

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
        <td><?php echo $sn++;?></td>
        <td><?php echo htmlspecialchars($post['first_name']). ' '. htmlspecialchars($post['last_name']);?></td>
        <td><?php echo $rooms;?></td>
        <td><?php echo htmlspecialchars($post['payment_method']);?></td>
        <td><?php echo htmlspecialchars($post['bank_account']);?></td>
        <td><?php 

if (!isset($_SESSION['divide'])) {
    $_SESSION['divide'] = false;
}

if (isset($_GET['divide'])) {
    $_SESSION['divide'] = !$_SESSION['divide'];
}

$amount_last = htmlspecialchars(number_format($post['amount'], 2));
if ($_SESSION['divide']) {
    $amount_last = htmlspecialchars(number_format($post['amount'] / 1.22, 2));
}

        $amount_last = htmlspecialchars($post['amount']);
        echo htmlspecialchars(number_format($amount_last, 2));?></td>
        <td><?php echo htmlspecialchars(formatDate($post['check_in']));?></td>
        <td><?php echo htmlspecialchars(formatDate($post['check_out']));?></td>
        <td><?php echo $days. ' يوم';?></td>
        <td><?php echo ($remaining_days > 0)? $remaining_days. ' يوم': 'انتهى';?></td>
        <td><?php echo htmlspecialchars($post['discount']);?></td>
        <td><?php echo htmlspecialchars($post['tax']);?></td>
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
</tbody> </table>
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
        stateSave: true, // يحفظ حالة الجدول (ترتيب، بحث، صفحة) عند إعادة التحميل
        pageLength: 10,
        dom: 'Bfrtip', // تحكم في ترتيب عناصر DataTables (B=Buttons, f=filter, r=processing, t=table, i=information, p=pagination)
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
                // Custom PDF styling for RTL and Arabic font
                customize: function (doc) {
                    // For proper Arabic font support, ensure 'Amiri-Regular' (or another Arabic font)
                    // is properly defined and loaded within pdfmake (usually via vfs_fonts.js)
                    // This is a placeholder; actual implementation requires font embedding.
                    doc.defaultStyle.font = 'Amiri-Regular'; 
                    doc.defaultStyle.alignment = 'right';
                    doc.styles.tableHeader.alignment = 'right';
                    
                    // Reverse column order for RTL in PDF, if header and body are handled together
                    if (doc.content[1] && doc.content[1].table) {
                        // Reverse the headers
                        if (doc.content[1].table.body.length > 0) {
                            doc.content[1].table.body[0].reverse();
                        }
                        // Reverse all data rows
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
        }
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