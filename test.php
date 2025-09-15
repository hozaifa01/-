<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة المخازن - المواد الغذائية</title>
    <?php include "header.php";?>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- الشريط الجانبي -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar bg-custom">
                <div class="logo mb-4">
                    <i class="bi bi-shop"></i> نظام المخازن
                </div>
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="bi bi-speedometer2"></i> لوحة التحكم
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-boxes"></i> إدارة الأصناف
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-arrow-down-circle"></i> إذن دخول
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-arrow-up-circle"></i> إذن صرف
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-people"></i> الموردين
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-person-badge"></i> العملاء
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-graph-up"></i> التقارير
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- المحتوى الرئيسي -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <div class="dashboard-header">
                    <h1>لوحة تحكم نظام إدارة المخازن</h1>
                    <p>مرحبًا بك في نظام إدارة مخازن المواد الغذائية</p>
                </div>

                <div class="row">
                    <!-- بطاقات الإحصائيات -->
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <i class="bi bi-box-seam" style="font-size: 2rem;"></i>
                                <h3 class="stat-number">245</h3>
                                <p>إجمالي الأصناف</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <i class="bi bi-arrow-down-circle" style="font-size: 2rem;"></i>
                                <h3 class="stat-number">42</h3>
                                <p>إذن دخول هذا الشهر</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <i class="bi bi-arrow-up-circle" style="font-size: 2rem;"></i>
                                <h3 class="stat-number">38</h3>
                                <p>إذن صرف هذا الشهر</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                                <h3 class="stat-number">7</h3>
                                <p>أصناف منتهية الصلاحية</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- الأصناف المنتهية الصلاحية -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>تنبيهات الأصناف المنتهية الصلاحية</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-expiry">
                                    <strong>أرز بسمتي</strong> - ينتهي الصلاحية في 2023-12-15
                                </div>
                                <div class="alert alert-expiry">
                                    <strong>زيت زيتون</strong> - ينتهي الصلاحية في 2023-12-20
                                </div>
                                <div class="alert alert-expiry">
                                    <strong>حليب طازج</strong> - ينتهي الصلاحية في 2023-12-10
                                </div>
                                <a href="#" class="btn btn-outline-warning">عرض الكل</a>
                            </div>
                        </div>
                    </div>

                    <!-- الأصناف الأكثر مبيعًا -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>الأصناف الأكثر مبيعًا</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        سكر
                                        <span class="badge bg-primary rounded-pill">120 كيس</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        دقيق
                                        <span class="badge bg-primary rounded-pill">95 كيس</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        زيت طهي
                                        <span class="badge bg-primary rounded-pill">80 عبوة</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جدول حركة المخزون -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>أحدث حركة المخزون</h5>
                    </div>
                    <div class="card-body">
                        <table id="inventoryTable" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>نوع الحركة</th>
                                    <th>الصنف</th>
                                    <th>الكمية</th>
                                    <th>التاريخ</th>
                                    <th>المستخدم</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-success">دخول</span></td>
                                    <td>سكر</td>
                                    <td>50 كيس</td>
                                    <td>2023-11-25</td>
                                    <td>أحمد محمد</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">صرف</span></td>
                                    <td>دقيق</td>
                                    <td>30 كيس</td>
                                    <td>2023-11-24</td>
                                    <td>علي حسن</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">دخول</span></td>
                                    <td>زيت زيتون</td>
                                    <td>20 عبوة</td>
                                    <td>2023-11-23</td>
                                    <td>أحمد محمد</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">صرف</span></td>
                                    <td>أرز</td>
                                    <td>40 كيس</td>
                                    <td>2023-11-22</td>
                                    <td>محمد علي</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">دخول</span></td>
                                    <td>حليب</td>
                                    <td>60 عبوة</td>
                                    <td>2023-11-21</td>
                                    <td>علي حسن</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- الروابط إلى مكتبات JavaScript -->
    <script>
        $(document).ready(function() {
            // تهيئة DataTable
            $('#inventoryTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
                },
                pageLength: 5,
                responsive: true
            });

            // تأثيرات الواجهة
            $('.card').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );
        });
    </script>
</body>
</html>