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
if (!isset($user) || (int)$user['level'] <= 0) {
    echo '<script>alert("عفوا، لا تملك صلاحيات كافية")</script>';
    echo "<script>window.location.href='index.php'</script>";
    exit();
}
// إنشاء رمز CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// معالجة إضافة الحجز
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // التحقق من CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("طلب غير صالح (CSRF)");
    }

    // تعقيم المدخلات
    $first_name     = htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8');
    $last_name      = htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8');
    $phone_number   = htmlspecialchars(trim($_POST['phone_number']), ENT_QUOTES, 'UTF-8');

    // التعامل مع غرف متعددة
    $selected_room_ids = [];
    if (isset($_POST['room_ids']) && is_array($_POST['room_ids'])) {
        $selected_room_ids = array_map('intval', $_POST['room_ids']);
    }

    if (empty($selected_room_ids)) {
        $_SESSION['error'] = "الرجاء تحديد غرفة واحدة على الأقل للحجز.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // حساب المبلغ الإجمالي على جانب الخادم
    $calculated_total_amount = 0;
    if (!empty($selected_room_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_room_ids), '?'));
        $sql_price = "SELECT SUM(price_per_night) AS total_base_price FROM rooms WHERE room_id IN ($placeholders)";
        $stmt_price = $con->prepare($sql_price);
        if ($stmt_price) {
            $types = str_repeat('i', count($selected_room_ids));
            $stmt_price->bind_param($types, ...$selected_room_ids);
            $stmt_price->execute();
            $result_price = $stmt_price->get_result();
            $row_price = $result_price->fetch_assoc();
            $total_base_price_per_night = (float) $row_price['total_base_price'];
            $stmt_price->close();

            $check_in_date = new DateTime($_POST['check_in']);
            $check_out_date = new DateTime($_POST['check_out']);
            $interval = $check_in_date->diff($check_out_date);
            $days = $interval->days;

            if ($days > 0) {
                $subtotal = $total_base_price_per_night * $days;
                $tax_percent = (float) $_POST['tax'];
                $discount_percent = (float) $_POST['discount'];

                $tax_amount = $subtotal * ($tax_percent / 100);
                $discount_amount = $subtotal * ($discount_percent / 100);
                $calculated_total_amount = $subtotal + $tax_amount - $discount_amount;
            }
        }
    }
    
    $amount         = $calculated_total_amount;
    $check_in       = $_POST['check_in'];
    $check_out      = $_POST['check_out'];
    $payment_method = $_POST['payment_method'];
    
    // التصحيح: تطبيق htmlspecialchars على كلا الجزئين من التعبير الشرطي
    $bank_account   = ($payment_method == "كاش") ? 
        "لا يوجد" : 
        htmlspecialchars(trim($_POST['bank_account']), ENT_QUOTES, 'UTF-8');
    
    $tax            = (float) $_POST['tax'];
    $discount       = (float) $_POST['discount'];
    $come_date      = date('Y-m-d H:i:s');

    // بدء المعاملة
    $con->begin_transaction();
    
    try {
        // إدخال بيانات العميل
        $stmt = $con->prepare("INSERT INTO customers 
            (first_name, last_name, phone_number, check_in, check_out,
            payment_method, amount, bank_account, tax, discount, come_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssssssdsdds",
            $first_name, $last_name, $phone_number, $check_in, $check_out,
            $payment_method, $amount, $bank_account, $tax, $discount, $come_date);
            
        if (!$stmt->execute()) {
            throw new Exception("خطأ في إضافة بيانات العميل: " . $stmt->error);
        }
        
        $customer_id = $stmt->insert_id;
        $stmt->close();
        
        // إضافة العلاقة بين العميل والغرف
        $insert_booking_room_stmt = $con->prepare("INSERT INTO booking_rooms (customer_id, room_id) VALUES (?, ?)");
        foreach ($selected_room_ids as $room_id) {
            $insert_booking_room_stmt->bind_param("ii", $customer_id, $room_id);
            if (!$insert_booking_room_stmt->execute()) {
                throw new Exception("خطأ في إضافة غرفة للحجز: " . $insert_booking_room_stmt->error);
            }
        }
        $insert_booking_room_stmt->close();
        
        // تحديث حالة الغرف إلى محجوزة
        $update_room_stmt = $con->prepare("UPDATE rooms SET current_status = 'محجوزة' WHERE room_id = ?");
        foreach ($selected_room_ids as $room_id) {
            $update_room_stmt->bind_param("i", $room_id);
            if (!$update_room_stmt->execute()) {
                throw new Exception("خطأ في تحديث حالة الغرفة: " . $update_room_stmt->error);
            }
        }
        $update_room_stmt->close();
        
        // تأكيد المعاملة
        $con->commit();
        
        $_SESSION['success'] = "تم إضافة الحجز بنجاح!";
        header('Location: bookings.php');
        exit();
        
    } catch (Exception $e) {
        // التراجع عن المعاملة في حالة الخطأ
        $con->rollback();
        $_SESSION['error'] = "حدث خطأ أثناء إضافة الحجز: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8');?> - إضافة حجز جديد</title>
    <?php include_once "header.php";?>
    <style>

        body {
            background-color: #f5f7fa;
            font-family: 'Tahoma', 'Arial', sans-serif;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: bold;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .room-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.3s;
            background: white;
        }
        
        .room-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .room-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(58, 110, 165, 0.1);
        }
        
        .amount-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--secondary-color);
            padding: 15px;
            background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
            border-radius: 8px;
            text-align: center;
        }
        
        .section-title {
            border-right: 4px solid var(--primary-color);
            padding-right: 12px;
            margin: 20px 0 15px;
        }
        
        .alert-message {
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
        }
    </style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const checkInInput = document.querySelector('input[name="check_in"]');
    const checkOutInput = document.querySelector('input[name="check_out"]');

    const amountInput = document.querySelector('input[name="amount"]'); // مخفي للحفظ
    const totalDisplay = document.getElementById('total-display');      // span للعرض

    // عرض أو إخفاء معلومات البنك حسب طريقة الدفع
    function togglePaymentFields() {
        let method = document.getElementById("payment_method").value;
        document.getElementById("bank_info").style.display = (method === "تحويل بنكي") ? "block" : "none";
    }

    // حساب عدد الأيام حسب قاعدة "اليوم يبدأ 12 ظهرًا"
    function calculateHotelNights(check_in, check_out) {
        let inDate = new Date(check_in);
        inDate.setHours(12,0,0,0);

        let outDate = new Date(check_out);
        outDate.setHours(12,0,0,0);

        let diff = (outDate - inDate) / (1000 * 3600 * 24);
        return diff > 0 ? diff : 0;
    }

    // تحديث المبلغ الإجمالي
    function updateAmount() {
        const selectedRoomCheckboxes = document.querySelectorAll('.room-checkbox:checked');
        const selectedRoomIds = Array.from(selectedRoomCheckboxes).map(cb => cb.value);

        const check_in_str = checkInInput.value;
        const check_out_str = checkOutInput.value;
        const tax_percent = parseFloat(document.querySelector('input[name="tax"]').value) || 0;
        const discount_percent = parseFloat(document.querySelector('input[name="discount"]').value) || 0;

        // التحقق من التواريخ
        if (!check_in_str || !check_out_str) {
            totalDisplay.innerText = '0.00';
            amountInput.value = 0;
            return;
        }

        const check_in = new Date(check_in_str);
        const check_out = new Date(check_out_str);

        if (check_out <= check_in) {
            totalDisplay.innerText = '0.00';
            amountInput.value = 0;
            return;
        }

        const days = calculateHotelNights(check_in, check_out);

        if (selectedRoomIds.length === 0) {
            totalDisplay.innerText = '0.00';
            amountInput.value = 0;
            return;
        }

        // عرض رسالة مؤقتة أثناء الحساب
        totalDisplay.innerText = 'جارٍ الحساب...';

        // إرسال الطلب إلى السيرفر لجلب السعر الإجمالي للغرف
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'get_total_room_price.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    const response = JSON.parse(this.responseText);
                    const total_base_price_per_night = parseFloat(response.total_price) || 0;

                    let subtotal = total_base_price_per_night * days;
                    let tax_amount = subtotal * (tax_percent / 100);
                    let discount_amount = subtotal * (discount_percent / 100);
                    let final_amount = subtotal + tax_amount - discount_amount;

                    // تحديث الـ span و الـ input المخفي
                    totalDisplay.innerText = final_amount.toFixed(2);
                    amountInput.value = final_amount.toFixed(2);
                } catch (e) {
                    console.error("Error parsing JSON:", e, this.responseText);
                    totalDisplay.innerText = '0.00';
                    amountInput.value = 0;
                }
            } else {
                console.error("AJAX error:", this.status);
                totalDisplay.innerText = '0.00';
                amountInput.value = 0;
            }
        };

        // تجهيز البيانات للإرسال
        let data = `check_in=${encodeURIComponent(check_in_str)}&check_out=${encodeURIComponent(check_out_str)}`;
        selectedRoomIds.forEach(id => data += `&room_ids[]=${encodeURIComponent(id)}`);
        xhr.send(data);
    }

    // تفعيل/إلغاء تمييز الغرفة عند التحديد
    function toggleRoomSelection(checkbox) {
        const roomCard = checkbox.closest('.room-card');
        if (checkbox.checked) {
            roomCard.classList.add('selected');
        } else {
            roomCard.classList.remove('selected');
        }
        updateAmount();
    }

    // إضافة مستمعين للأحداث لجميع مربعات الغرف
    document.querySelectorAll('.room-checkbox').forEach(cb => {
        cb.addEventListener('change', function() { toggleRoomSelection(this); });
        if (cb.checked) toggleRoomSelection(cb);
    });

    // إضافة أحداث للتواريخ والضرائب والخصومات
    [checkInInput, checkOutInput].forEach(input => {
        input.addEventListener('change', updateAmount);
        input.addEventListener('input', updateAmount); // للتحديث أثناء الكتابة
    });
    document.querySelector('input[name="tax"]').addEventListener('input', updateAmount);
    document.querySelector('input[name="discount"]').addEventListener('input', updateAmount);
    document.querySelector('select[name="payment_method"]').addEventListener('change', function() {
        togglePaymentFields();
        updateAmount();
    });

    // حساب أولي عند تحميل الصفحة
    updateAmount();
    togglePaymentFields();
});
</script>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <?php include "leftbar.php";?>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">إضافة حجز جديد</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        if (isset($_SESSION['error'])) {
                            echo '<div class="alert alert-danger alert-message">' . $_SESSION['error'] . '</div>';
                            unset($_SESSION['error']);
                        }
                        ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');?>">
                            
                            <h5 class="section-title">معلومات العميل</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">الاسم الأول</label>
                                        <input type="text" name="first_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">الاسم الأخير</label>
                                        <input type="text" name="last_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">رقم الهاتف</label>
                                        <input type="text" name="phone_number"
                                        class="form-control" value="0" required>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="section-title">تفاصيل الحجز</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">تاريخ الوصول</label>
                                        <input type="date" name="check_in" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">تاريخ المغادرة</label>
                                        <input type="date" name="check_out" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">الغرف المتاحة</label>
                                <div class="row" id="available_rooms_checkboxes">
                                    <?php
                                    $stmt = $con->prepare("SELECT room_id, room_number, room_type, price_per_night FROM rooms WHERE current_status = 'متاحة'");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        while ($room = $result->fetch_assoc()) {
                                            $room_id = htmlspecialchars($room['room_id'], ENT_QUOTES, 'UTF-8');
                                            $room_number = htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8');
                                            $room_type = htmlspecialchars($room['room_type'], ENT_QUOTES, 'UTF-8');
                                            $price = htmlspecialchars($room['price_per_night'], ENT_QUOTES, 'UTF-8');

                                            echo "<div class='col-md-6 mb-3'>";
                                            echo "<div class='room-card'>";
                                            echo "<div class='form-check'>";
                                            echo "<input class='form-check-input room-checkbox' type='checkbox' name='room_ids[]' value='$room_id' id='room_$room_id'>";
                                            echo "<label class='form-check-label w-100' for='room_$room_id'>";
                                            echo "<strong>غرفة $room_number</strong> - $room_type<br>";
                                            echo "<small>السعر: $price جنيه/ليلة</small>";
                                            echo "</label>";
                                            echo "</div>";
                                            echo "</div>";
                                            echo "</div>";
                                        }
                                    } else {
                                        echo "<div class='col-12'><p class='text-center text-muted py-3'>لا توجد غرف متاحة حاليًا.</p></div>";
                                    }
                                    $stmt->close();
                                    ?>
                                </div>
                                <small class="form-text text-muted">يرجى تحديد غرفة واحدة أو أكثر للحجز.</small>
                            </div>
                            
                            <h5 class="section-title">التفاصيل المالية</h5>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">طريقة الدفع</label>
                                        <select name="payment_method" id="payment_method" class="form-control" onchange="togglePaymentFields()" required>
                                            <option value="كاش">كاش</option>
                                            <option value="تحويل بنكي">تحويل بنكي</option>
                                            <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">الضريبة (%)</label>
                                        <input type="number" name="tax"
                                        class="form-control" value="0" min="0"
                                        max="100">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"> السياحة +
                                        الخصم (إختياري )  (%)</label>
                                        <input type="number" name="discount"
                                        class="form-control" value="0" min="0"
                                        max="100">
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div id="bank_info" class="mb-3" style="display:none;">
                                        <label class="form-label">رقم الحساب البنكي</label>
                                        <input type="text" name="bank_account" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="amount-display mb-3">
                                        <span>المبلغ الإجمالي: </span>
                                        <span id="total-display">0.00</span>
                                        <span> جنيه</span>
                                        <input type="hidden" name="amount" >
                                    </div>
                                </div>
                            </div>
                            

                            <div class="d-flex justify-content-between align-items-center">
                                <button type="submit" name="save" class="btn
                                btn-outline-primary">
                                    <i class="fa fa-save me-2"></i> حفظ الحجز
                                </button>
                                <a href="bookings.php" class="btn
                                btn-outline-primary">
                                    <i class="fa fa-arrow-right me-2"></i> رجوع
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once "footer.php";?>
</body>
</html>