<?php
// update_room_status.php - كود تحديث حالة الغرف
function updateAllRoomStatus($con) {
    // الحصول على الوقت الحالي
    $now = new DateTime();
    $current_time = $now->format('H:i:s');
    
    // تحديد بداية اليوم (12 ظهرًا)
    if ($current_time < '12:00:00') {
        $today_start = (new DateTime('yesterday'))->setTime(12, 0, 0);
    } else {
        $today_start = (new DateTime())->setTime(12, 0, 0);
    }
    
    $today_start_str = $today_start->format('Y-m-d H:i:s');
    $today_end_str = $today_start->modify('+1 day')->format('Y-m-d H:i:s');

    // استرجاع كل الغرف
    $rooms_result = $con->query("SELECT room_id FROM rooms");
    
    if (!$rooms_result) {
        error_log("Error in rooms query: " . $con->error);
        return;
    }
    
    while ($room = $rooms_result->fetch_assoc()) {
        $room_id = $room['room_id'];
        $status = 'متاحة'; // الحالة الافتراضية

        // استعلام للتحقق من الحجز الحالي
        $sql = "
            SELECT br.booking_room_id, c.check_in, c.check_out
            FROM booking_rooms br
            JOIN customers c ON br.customer_id = c.customer_id
            WHERE br.room_id = ? 
            AND (
                (c.check_in <= ? AND c.check_out > ?) OR
                (c.check_in >= ? AND c.check_in < ?)
            )
            AND c.status IN ('confirmed', 'checked_in')
            ORDER BY c.check_in DESC
            LIMIT 1
        ";
        
        $stmt = $con->prepare($sql);
        
        if (!$stmt) {
            error_log("Error preparing statement: " . $con->error);
            continue; // الانتقال للغرفة التالية
        }
        
        $stmt->bind_param("issss", $room_id, $today_end_str, $today_start_str, 
                         $today_start_str, $today_end_str);
        
        if (!$stmt->execute()) {
            error_log("Error executing statement: " . $stmt->error);
            $stmt->close();
            continue;
        }
        
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $status = 'محجوزة';
        }

        $stmt->close();

        // تحديث حالة الغرفة
        $update_sql = "UPDATE rooms SET current_status = ? WHERE room_id = ?";
        $update_stmt = $con->prepare($update_sql);
        
        if (!$update_stmt) {
            error_log("Error preparing update statement: " . $con->error);
            continue;
        }
        
        $update_stmt->bind_param("si", $status, $room_id);
        
        if (!$update_stmt->execute()) {
            error_log("Error updating room status: " . $update_stmt->error);
        }
        
        $update_stmt->close();
    }
    
    if (isset($rooms_result)) {
        $rooms_result->free();
    }
}

// استدعاء الدالة في كل صفحة تحتاج إلى عرض حالة الغرف
updateAllRoomStatus($con);
?>