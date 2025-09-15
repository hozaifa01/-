<?php
// إعدادات الجلسة الآمنة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true
    ]);
}

require_once 'dbconnection.php';
require_once 'update_room_status.php';

// التحقق من الجلسة
if (!isset($_SESSION['aid']) ||!filter_var($_SESSION['aid'], FILTER_VALIDATE_INT)) {
    header('Location: logout.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($site["title"], ENT_QUOTES, 'UTF-8')?> - إدارة الغرف</title>
  <?php include "header.php";?>
</head>
<body class="p-4 bg-custom">
<div class="container-fluid">
  <div class="row">
    <div class="col-md-3"><?php include "leftbar.php";?></div>
    <div class="col-md-9">
      <h2 class="mb-4">إدارة الغرف</h2>
      <hr />
      <form method="POST">
        <a href="add_room.php" class="btn btn-success mb-3">+ إضافة غرفة</a>
        <div class="table-responsive">
          <table id="rooms" class="table-bordered table-hover">
            <thead class="table-custom">
              <tr>
                <th>رقم الغرفة</th>
                <th>النوع</th>
                <th>السعر/ليلة</th>
                <th>الحالة</th>
                <th>إجراءات</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $stmt = $con->prepare("SELECT room_id, room_number, room_type, price_per_night, current_status FROM rooms ORDER BY room_number DESC");
              if ($stmt && $stmt->execute()) {
                  $result = $stmt->get_result();
                  while ($row = $result->fetch_assoc()) {
                      $room_id = (int)$row['room_id'];
                      echo "<tr>
                          <td>". htmlspecialchars($row['room_number'], ENT_QUOTES, 'UTF-8'). "</td>
                          <td>". htmlspecialchars($row['room_type'], ENT_QUOTES, 'UTF-8'). "</td>
                          <td>". htmlspecialchars($row['price_per_night'], ENT_QUOTES, 'UTF-8'). "</td>
                          <td>". htmlspecialchars($row['current_status'], ENT_QUOTES, 'UTF-8'). "</td>
                          <td>
                              <a href='edit_room.php?id={$room_id}' class='btn btn-sm btn-outline-primary'>تعديل</a>
                              <a href='delete_room.php?id={$room_id}' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a>
                          </td>
                      </tr>";
}
                  $stmt->close();
} else {
                  error_log("فشل في جلب بيانات الغرف: ". $con->error);
                  echo "<tr><td colspan='5'>حدث خطأ أثناء تحميل البيانات.</td></tr>";
}
?>
            </tbody>
          </table>
        </div>
        <a href="index.php" class="btn btn-secondary mt-3">رجوع</a>
      </form>
    </div>
  </div>
</div>
<?php include "footer.php";?>
<script>
  $(document).ready(function () {
    $('#rooms').DataTable({
      responsive: true,
      autoFill: false,
      processing: true,
      serverSide: false,
      lengthChange: true,
      pageLength: 10,
      dom: 'Bfrtip',
      buttons: ['Excel', 'copy', 'print'],
      language: {
        search: 'بحث',
        info: 'عرض _START_ إلى _END_ من _TOTAL_ صفحة',
        infoEmpty: 'لا توجد بيانات',
        infoFiltered: '(مفلترة من إجمالي _MAX_ بيانات)',
        lengthMenu: 'عرض _MENU_ بيانات',
        loadingRecords: 'تحميل...',
        processing: 'معالجة...',
        zeroRecords: 'لا توجد بيانات',
        paginate: {
          first: '<<',
          last: '>>',
          next: '>',
          previous: '<'
},
        aria: {
          sortAscending: ': تفعيل لترتيب تصاعدي',
          sortDescending: ': تفعيل لترتيب تنازلي'
},
        buttons: {
          copy: 'نسخ',
          csv: 'CSV',
          excel: 'Excel',
          pdf: 'PDF',
          print: 'طباعة'
}
},
      searching: true,
      paging: true,
      info: true,
      searchBuilder: true,
      fixedColumns: true,
      columnDefs: [{
        targets: '_all',
        visible: true
}],
      stateRestore: true,
      order: [[0, 'asc']]
});
});
</script>
</body>
</html>