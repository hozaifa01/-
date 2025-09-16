<?php
// invoice.php (نسخة منقحة لتوليد فاتورة مع رمز QR)
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

// تحقق من الجلسة
if (!isset($_SESSION['aid']) || !filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
    header('Location: logout.php');
    exit();
}
$aid = (int) $_SESSION['aid'];

// التحقق من صلاحية المدير
$stmt = $con->prepare("SELECT level FROM tbl_login WHERE id = ?");
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
    <title><?= htmlspecialchars($site["title"] ?? 'الموقع', ENT_QUOTES, 'UTF-8') ?> - فاتورة العميل</title>
    <?php include_once "header.php"; /* تأكد أن header.php لا يحظر تحميل السكربتات */ ?>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #f4f4f4; padding: 40px; }
        .invoice-box { background:#fff; padding:30px; border:1px solid #ccc; max-width:800px; margin:auto; box-shadow:0 0 10px rgba(0,0,0,.1); }
        .invoice-header { text-align:center; margin-bottom:30px; }
        .invoice-details { width:100%; border-collapse:collapse; margin-bottom:20px; }
        .invoice-details th,.invoice-details td { padding:10px; border-bottom:1px solid #ddd; text-align:right; }
        .invoice-footer,.signature { margin-top:40px; text-align:left; }
        #qrcode { margin-top:10px; }
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

                    $stmt = $con->prepare("SELECT * FROM customers WHERE customer_id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();

                        $check_in = new DateTime($row['check_in']);
                        $check_out = new DateTime($row['check_out']);
                        $days = $check_in->diff($check_out)->days;

                        // جلب الغرف المحجوزة
                        $stmtRooms = $con->prepare("
                            SELECT r.room_number, r.room_type, r.price_per_night
                            FROM booking_rooms br
                            JOIN rooms r ON br.room_id = r.room_id
                            WHERE br.customer_id = ?
                        ");

                        $room_rows = '';
                        $total_room_cost = 0;

                        if ($stmtRooms) {
                            $stmtRooms->bind_param("i", $row['customer_id']);
                            $stmtRooms->execute();
                            $rooms_result = $stmtRooms->get_result();

                            while ($room = $rooms_result->fetch_assoc()) {
                                $room_cost = floatval($room['price_per_night']) * $days;
                                $room_rows .= '<tr>
                                    <td>' . htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8') . '</td>
                                    <td>' . htmlspecialchars($room['room_type'], ENT_QUOTES, 'UTF-8') . '</td>
                                </tr>';
                                $total_room_cost += $room_cost;
                            }

                            $stmtRooms->close();
                        } else {
                            $room_rows = '<tr><td colspan="2">تعذر جلب بيانات الغرف</td></tr>';
                        }
                        ?>
                        <button class="btn btn-primary mb-3" onclick="printTable()">طباعة</button>
                        <div class="table-responsive">
                            <table id="invoiceTable" class="invoice-details">
                                <thead>
                                    <tr>
                                        <th colspan="2">
                                            <div class="invoice-header">
                                                <h2><?= htmlspecialchars($site["title"] ?? 'الموقع', ENT_QUOTES, 'UTF-8') ?> - فاتورة العميل</h2>
                                                <hr>
                                                <p>تاريخ الإصدار: <?= date('Y-m-d'); ?></p>
                                                <p>#<?= htmlspecialchars($row['customer_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><th>اسم العميل:</th><td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                                    <tr><th colspan="2">الغرف المحجوزة:</th></tr>
                                    <tr>
                                        <td colspan="2">
                                            <table style="width:100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr><th>رقم الغرفة</th><th>نوع الغرفة</th></tr>
                                                </thead>
                                                <tbody>
                                                    <?= $room_rows; ?>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr><th>عدد الأيام:</th><td><?= $days; ?></td></tr>
                                    <tr><th>تاريخ الوصول:</th><td><?= htmlspecialchars($row['come_date'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
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
                                        <tr><th>المبلغ بعد خصم الضرائب (<?= (int)((1 - $totalRate)*100) ?>%):</th><td><?= number_format($netAmount, 2); ?> جنيه</td></tr>
                                        <tr><th>ضريبة القيمة المضافة (17%):</th><td><?= number_format($taxAmount, 2); ?> جنيه</td></tr>
                                        <tr><th>ضريبة السياحة (5%):</th><td><?= number_format($tourismAmount, 2); ?> جنيه</td></tr>
                                        <tr><th>المبلغ الإجمالي:</th><td><?= number_format($amount, 2); ?> جنيه</td></tr>
                                        <?php
                                    } else {
                                        ?>
                                        <tr><th>المبلغ الإجمالي:</th><td><?= number_format($amount, 2); ?> جنيه</td></tr>
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
                                                <p>رمز QR يحتوي على بيانات العميل:</p>
                                                <div id="qrcode" aria-hidden="false"></div>
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
(function(){
  // مراقبة الأخطاء العامة لالتقاط أي خطأ يمنع تنفيذ السكربت
  window.addEventListener('error', function(e){
    console.error('GlobalError:', e.message, e.filename, e.lineno, e.error);
    try {
      var el = document.getElementById('qrcode');
      if (el) {
        el.dataset.jsError = e.message;
      }
    } catch(e){}
  });

  // لائحة السكربتات المحمّلة (مفيدة للتشخيص)
  function logLoadedScripts(){
    var list = [];
    for (var i=0; i<document.scripts.length; i++){
      list.push(document.scripts[i].src || '(inline)');
    }
    console.log('Loaded scripts:', list);
    var el = document.getElementById('qrcode');
    if(el){
      var note = document.createElement('div');
      note.style.fontSize='12px';
      note.style.color='#666';
      note.style.marginTop='6px';
      note.textContent = ' امسح الكود للتاكد من صحة البيانات.';
      el.parentNode.insertBefore(note, el.nextSibling);
    }
  }
  logLoadedScripts();

  // بيانات العميل مُمررة من PHP بأمان (دعم العربية)
  var customerData = <?= json_encode([
      'name' => isset($row) ? ($row['first_name'] . ' ' . $row['last_name']) : '',
      'id'   => isset($row) ? $row['customer_id'] : '',
      'amount' => isset($amount) ? number_format($amount, 2) . ' جنيه' : '0.00 جنيه',
      'comeDate' => isset($row) ? $row['come_date'] : '',
      'days' => isset($days) ? $days . ' يوم' : '0 يوم'
  ], JSON_UNESCAPED_UNICODE); ?>;

  // نص الفاتورة للـ QR
  var qrText = "فاتورة فندقية\n";
  qrText += "الاسم: " + customerData.name + "\n";
  qrText += "رقم العميل: " + customerData.id + "\n";
  qrText += "المبلغ: " + customerData.amount + "\n";
  qrText += "تاريخ الوصول: " + customerData.comeDate + "\n";
  qrText += "مدة الإقامة: " + customerData.days + "\n";
  qrText += "تاريخ الإصدار: <?= date('Y-m-d'); ?>";

  // عنصر الحاوية
  var qrContainer = document.getElementById('qrcode');
  if (!qrContainer) {
    console.error('عنصر qrcode غير موجود في الـ DOM');
    return;
  }
  qrContainer.innerHTML = ''; // تنظيف

  // fallback: صورة من خدمة خارجية
  function imageFallback(){
    var data = encodeURIComponent(qrText);
    var size = '200x200';
    var src = 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + '&data=' + data;
    var img = document.createElement('img');
    img.alt = 'QR Code';
    img.src = src;
    img.width = 200; img.height = 200;
    qrContainer.innerHTML = '';
    qrContainer.appendChild(img);
    console.warn('تم استخدام صورة QR الاحتياطية من api.qrserver.com');
  }

  // محاولة إنشاء QR باستخدام المكتبة (إذا كانت موجودة)
  function createWithLib(){
    try {
      if (typeof QRCode === 'undefined') {
        console.warn('QRCode غير معرّف');
        return false;
      }
      // إن كانت المكتبة موجودة لكن ليست Constructor تقنيًا
      if (typeof QRCode !== 'function' && typeof QRCode !== 'object') {
        console.warn('QRCode موجودة لكن ليست Function/Constructor:', typeof QRCode);
        return false;
      }
      // إنشاء
      qrContainer.innerHTML = '';
      // بعض نسخ المكتبة قد تختزن التصحيح داخل QRCode.CorrectLevel، لذلك نتحقق
      var correctLevel = (typeof QRCode !== 'undefined' && QRCode.CorrectLevel) ? QRCode.CorrectLevel.H : undefined;
      var opts = {
        text: qrText,
        width: 200,
        height: 200
      };
      if (correctLevel) opts.correctLevel = correctLevel;
      new QRCode(qrContainer, opts);
      console.log('تم إنشاء QR بواسطة مكتبة qrcodejs');
      return true;
    } catch (err){
      console.error('خطأ أثناء إنشاء QR بالمكتبة:', err);
      return false;
    }
  }

  // لو المكتبة غير محمّلة نحاول تحميلها ديناميكيًا ثم نستدعي الإنشاء مرة أخرى
  function ensureLibAndCreate(){
    if (createWithLib()) return;

    var cdn = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    // إذا كانت علامة السكربت موجودة لكن لا تعمل، نتجنّب إعادة إدراجها
    if (document.querySelector('script[src="' + cdn + '"]')) {
      console.warn('موجود script لـ qrcode في الصفحة لكن createWithLib فشل. سنستخدم fallback صورة.');
      imageFallback();
      return;
    }

    var s = document.createElement('script');
    s.src = cdn;
    s.async = true;
    s.onload = function(){
      console.log('qrcode.js تم تحميلها من CDN، أحاول الإنشاء الآن...');
      setTimeout(function(){
        if (!createWithLib()) {
          console.warn('بعد التحميل، لا زالت المكتبة لا تعمل. سيتم استخدام fallback صورة.');
          imageFallback();
        }
      }, 50);
    };
    s.onerror = function(){
      console.error('فشل تحميل qrcode.js من CDN.');
      imageFallback();
    };
    document.head.appendChild(s);
  }

  // نفّذ
  ensureLibAndCreate();

})();
</script>

<?php include "footer.php"; ?>
</body>
</html>