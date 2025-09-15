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

// التحقق من وجود معرف الحجز
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: bookings.php');
    exit();
}
$booking_id = (int) $_GET['id'];

// جلب بيانات الحجز الحالية
$stmt = $con->prepare("
    SELECT c.*, GROUP_CONCAT(br.room_id) as selected_rooms 
    FROM customers c 
    LEFT JOIN booking_rooms br ON c.customer_id = br.customer_id 
    WHERE c.customer_id = ? 
    GROUP BY c.customer_id
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header('Location: bookings.php');
    exit();
}

// تحويل الغرف المحددة إلى مصفوفة
$selected_rooms = !empty($booking['selected_rooms']) ? explode(',', $booking['selected_rooms']) : [];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8');?> - تعديل حجز</title>
    <?php include_once "header.php";?>
    <script>
        function togglePaymentFields() {
            let method = document.getElementById("payment_method").value;
            document.getElementById("bank_info").style.display = (method === "تحويل بنكي")? "block": "none";
        }

        // الوظيفة الرئيسية لحساب المبلغ - تدعم اختيار غرف متعددة
        function updateAmount() {
            let selectedRoomCheckboxes = document.querySelectorAll('.room-checkbox:checked');
            let selectedRoomIds = Array.from(selectedRoomCheckboxes).map(cb => cb.value);

            let check_in_str = document.querySelector('input[name="check_in"]').value;
            let check_out_str = document.querySelector('input[name="check_out"]').value;
            let tax_percent = parseFloat(document.querySelector('input[name="tax"]').value) || 0;
            let discount_percent = parseFloat(document.querySelector('input[name="discount"]').value) || 0;

            let check_in = new Date(check_in_str);
            let check_out = new Date(check_out_str);

            // حساب عدد الأيام
            let days = 0;
            if (check_in_str && check_out_str && check_out > check_in) {
                days = (check_out.getTime() - check_in.getTime()) / (1000 * 3600 * 24);
            } else {
                document.querySelector('input[name="amount"]').value = (0).toFixed(2);
                return; // الخروج إذا كانت التواريخ غير صالحة أو لم يتم تحديدها
            }

            if (selectedRoomIds.length > 0) {
                let xhr = new XMLHttpRequest();
                xhr.open('POST', 'get_total_room_price.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    if (this.status == 200) {
                        try {
                            let response = JSON.parse(this.responseText);
                            let total_base_price_per_night = parseFloat(response.total_price);

                            let subtotal = total_base_price_per_night * days;
                            let tax_amount = subtotal * (tax_percent / 100);
                            let discount_amount = subtotal * (discount_percent / 100);
                            let final_amount = subtotal + tax_amount - discount_amount;

                            document.querySelector('input[name="amount"]').value = final_amount.toFixed(2);

                        } catch (e) {
                            console.error("Error parsing JSON response or response is not a number:", e, this.responseText);
                            document.querySelector('input[name="amount"]').value = (0).toFixed(2);
                        }
                    } else {
                        console.error("AJAX error: " + this.status);
                        document.querySelector('input[name="amount"]').value = (0).toFixed(2);
                    }
                };

                // إعداد البيانات لإرسالها عبر POST مع مصفوفة معرفات الغرف
                let data = 'check_in=' + check_in_str + '&check_out=' + check_out_str;
                selectedRoomIds.forEach(function(roomId) {
                    data += '&room_ids[]=' + roomId; // إرسال معرفات الغرف كمصفوفة
                });
                xhr.send(data);
            } else {
                // لا توجد غرف مختارة، تعيين المبلغ إلى 0
                document.querySelector('input[name="amount"]').value = (0).toFixed(2);
            }
        }

        document.addEventListener('DOMContentLoaded', function(){
            // إضافة مستمعين للأحداث لجميع مربعات اختيار الغرف
            document.querySelectorAll('.room-checkbox').forEach(function (checkbox) {
                checkbox.addEventListener('change', updateAmount);
            });
            document.querySelector('input[name="check_in"]').addEventListener('change', updateAmount);
            document.querySelector('input[name="check_out"]').addEventListener('change', updateAmount);
            document.querySelector('input[name="tax"]').addEventListener('change', updateAmount);
            document.querySelector('input[name="discount"]').addEventListener('change', updateAmount);
            document.querySelector('select[name="payment_method"]').addEventListener('change', updateAmount);
            
            // حساب أولي للمبلغ عند تحميل الصفحة
            updateAmount();
            
            // عرض/إخفاء معلومات البنك بناءً على طريقة الدفع المحددة
            togglePaymentFields();
        });
    </script>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <?php include_once "leftbar.php";?>
            </div>
            <div class="col-md-9">
                <h2 class="mb-4">تعديل حجز</h2>
                <hr />
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');?>">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">اسم العميل</label>
                        <input type="text" name="first_name" class="form-control mt-2" placeholder="الاسم الأول" 
                               value="<?php echo htmlspecialchars($booking['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <input type="text" name="last_name" class="form-control mt-2" placeholder="الاسم الأخير" 
                               value="<?php echo htmlspecialchars($booking['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="رقم الهاتف"
                               value="<?php echo htmlspecialchars($booking['phone_number'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">الغرف المتاحة</label>
                        <div id="available_rooms_checkboxes" class="form-control" style="height: auto; min-height: 100px; overflow-y: auto;">
                            <?php
                            // جلب جميع الغرف مع حالة كل غرفة
                            $stmt = $con->prepare("
                                SELECT room_id, room_number, room_type, price_per_night, 
                                       CASE 
                                           WHEN room_id IN (SELECT room_id FROM booking_rooms WHERE customer_id != ?) 
                                           AND room_id NOT IN (SELECT room_id FROM booking_rooms WHERE customer_id = ?) 
                                           THEN 'محجوزة'
                                           ELSE 'متاحة'
                                       END as availability
                                FROM rooms
                            ");
                            $stmt->bind_param("ii", $booking_id, $booking_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                while ($room = $result->fetch_assoc()) {
                                    $room_id = htmlspecialchars($room['room_id'], ENT_QUOTES, 'UTF-8');
                                    $room_number = htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8');
                                    $room_type = htmlspecialchars($room['room_type'], ENT_QUOTES, 'UTF-8');
                                    $price = htmlspecialchars($room['price_per_night'], ENT_QUOTES, 'UTF-8');
                                    $is_available = ($room['availability'] === 'متاحة' || in_array($room['room_id'], $selected_rooms));
                                    $is_checked = in_array($room['room_id'], $selected_rooms);
                                    
                                    echo "<div class='form-check'>";
                                    if ($is_available) {
                                        echo "<input class='form-check-input room-checkbox' type='checkbox' name='room_ids[]' 
                                               value='$room_id' id='room_$room_id' " . ($is_checked ? "checked" : "") . ">";
                                        echo "<label class='form-check-label' for='room_$room_id'>غرفة $room_number ($room_type) - $price$</label>";
                                    } else {
                                        echo "<input class='form-check-input' type='checkbox' disabled>";
                                        echo "<label class='form-check-label text-muted'>غرفة $room_number ($room_type) - $price$ (محجوزة)</label>";
                                    }
                                    echo "</div>";
                                }
                            } else {
                                echo "<p class='text-muted'>لا توجد غرف متاحة.</p>";
                            }
                            $stmt->close();
                            ?>
                        </div>
                        <small class="form-text text-muted">يرجى تحديد غرفة واحدة أو أكثر للحجز.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">تاريخ الوصول</label>
                        <input type="date" name="check_in" class="form-control" 
                               value="<?php echo htmlspecialchars($booking['check_in'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">تاريخ المغادرة</label>
                        <input type="date" name="check_out" class="form-control" 
                               value="<?php echo htmlspecialchars($booking['check_out'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">طريقة الدفع</label>
                        <select name="payment_method" id="payment_method" class="form-control" onchange="togglePaymentFields()" required>
                            <option value="كاش" <?php echo $booking['payment_method'] === 'كاش' ? 'selected' : ''; ?>>كاش</option>
                            <option value="تحويل بنكي" <?php echo $booking['payment_method'] === 'تحويل بنكي' ? 'selected' : ''; ?>>تحويل بنكي</option>
                            <option value="تحويل بنكي" <?php echo
                            $booking['payment_method'] === 'لا يوجد ' ?
                            'selected' : ''; ?>>لم يسدد </option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">المبلغ المدفوع</label>
                        <input type="number" step="0.01" name="amount" class="form-control" 
                               value="<?php echo htmlspecialchars($booking['amount'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المبلغ المدفوع </label>
                        <input type="number" step="0.01" name="des_amount" class="form-control" 
                               value="<?php echo htmlspecialchars($booking['amount'], ENT_QUOTES, 'UTF-8'); ?>" >
                    </div>
                    
                    <div id="bank_info" class="mb-3" style="display:<?php echo $booking['payment_method'] === 'تحويل بنكي' ? 'block' : 'none'; ?>;">
                        <label class="form-label">تفاصيل الحساب البنكي</label>
                        <input type="text" name="bank_account" class="form-control" 
                               value="<?php echo htmlspecialchars($booking['bank_account'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">الضريبة (%)</label>
                        <input type="number" name="tax" class="form-control" value="<?php echo htmlspecialchars($booking['tax'], ENT_QUOTES, 'UTF-8'); ?>" min="0" max="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">الخصم (%)</label>
                        <input type="number" name="discount" class="form-control" value="<?php echo htmlspecialchars($booking['discount'], ENT_QUOTES, 'UTF-8'); ?>" min="0" max="100">
                    </div>
                    
                    <button type="submit" name="update" class="btn btn-primary">تحديث</button>
                    <a href="bookings.php" class="btn btn-secondary">رجوع</a>
                </form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    // التحقق من CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("طلب غير صالح (CSRF)");
    }

    // تعقيم المدخلات
    $first_name     = htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8');
    $last_name      = htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8');
    $phone_number   = htmlspecialchars(trim($_POST['phone_number']), ENT_QUOTES, 'UTF-8');
    $booking_id     = (int) $_POST['booking_id'];
    
    // التعامل مع غرف متعددة
    $selected_room_ids = [];
    if (isset($_POST['room_ids']) && is_array($_POST['room_ids'])) {
        $selected_room_ids = array_map('intval', $_POST['room_ids']);
    }

    if (empty($selected_room_ids)) {
        echo '<script>alert("الرجاء تحديد غرفة واحدة على الأقل للحجز.")</script>';
        echo '<script>window.history.back();</script>';
        exit();
    }
$des_amount=$_POST['amount']-$_POST['des_amount'];
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
    $bank_account   = ($payment_method === "كاش")? "لا يوجد": htmlspecialchars(trim($_POST['bank_account']), ENT_QUOTES, 'UTF-8');
    $tax            = (float) $_POST['tax'];
    $discount       = (float) $_POST['discount'];

    // بدء المعاملة
    $con->begin_transaction();
    
    try {
        // تحديث بيانات الحجز
        $stmt = $con->prepare("UPDATE customers SET 
            first_name = ?, last_name = ?, phone_number = ?, 
            check_in = ?, check_out = ?, payment_method = ?, 
            amount = ?, bank_account = ?, tax = ?, discount = ? 
            WHERE customer_id = ?");
        
        $stmt->bind_param("ssssssdsddi",
            $first_name, $last_name, $phone_number, 
            $check_in, $check_out, $payment_method,
            $amount, $bank_account, $tax, $discount, $booking_id);
            
        if (!$stmt->execute()) {
            throw new Exception("خطأ في تحديث بيانات الحجز: " . $stmt->error);
        }
        $stmt->close();
        
        // حذف الغرف القديمة المرتبطة بهذا الحجز
        $delete_stmt = $con->prepare("DELETE FROM booking_rooms WHERE customer_id = ?");
        $delete_stmt->bind_param("i", $booking_id);
        if (!$delete_stmt->execute()) {
            throw new Exception("خطأ في حذف الغرف القديمة: " . $delete_stmt->error);
        }
        $delete_stmt->close();
        
        // إضافة الغرف الجديدة
        $insert_stmt = $con->prepare("INSERT INTO booking_rooms (customer_id, room_id) VALUES (?, ?)");
        foreach ($selected_room_ids as $room_id) {
            $insert_stmt->bind_param("ii", $booking_id, $room_id);
            if (!$insert_stmt->execute()) {
                throw new Exception("خطأ في إضافة الغرف الجديدة: " . $insert_stmt->error);
            }
        }
        $insert_stmt->close();
        
        // تحديث حالة الغرف (إعادة جميع الغرف السابقة إلى "متاحة" ثم تحديث الغرف الجديدة إلى "محجوزة")
        $reset_rooms_stmt = $con->prepare("
            UPDATE rooms 
            SET current_status = 'متاحة' 
            WHERE room_id IN (
                SELECT room_id FROM booking_rooms WHERE customer_id = ?
            )
        ");
        $reset_rooms_stmt->bind_param("i", $booking_id);
        if (!$reset_rooms_stmt->execute()) {
            throw new Exception("خطأ في إعادة تعيين حالة الغرف: " . $reset_rooms_stmt->error);
        }
        $reset_rooms_stmt->close();
        
        $update_rooms_stmt = $con->prepare("UPDATE rooms SET current_status = 'محجوزة' WHERE room_id IN (" . implode(',', $selected_room_ids) . ")");
        if (!$update_rooms_stmt->execute()) {
            throw new Exception("خطأ في تحديث حالة الغرف الجديدة: " . $update_rooms_stmt->error);
        }
        $update_rooms_stmt->close();
        
        // تأكيد المعاملة
        $con->commit();
        
        echo '<script>alert("تم تحديث الحجز بنجاح")</script>';
        echo '<script>window.location.href="bookings.php"</script>';
        
    } catch (Exception $e) {
        // التراجع عن المعاملة في حالة الخطأ
        $con->rollback();
        error_log($e->getMessage());
        echo '<script>alert("حدث خطأ أثناء تحديث الحجز: ' . $e->getMessage() . '")</script>';
    }
}
?>
            </div>
        </div>
    </div>
    <?php include_once "footer.php";?>
</body>
</html>