<?php
session_start();
if (isset($_POST['hidden'])) {
    $_SESSION['sidebar_hidden'] = (bool)$_POST['hidden'];
    echo "تم تحديث الحالة";
} else {
    echo "خطأ في الطلب";
}
exit();
?>