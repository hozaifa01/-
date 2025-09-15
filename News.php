<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام حجوزات الفندق</title>
    
    
    
    <style>
        body {
            padding-top: 70px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .time-alert-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            border-bottom: 2px solid #ffc107;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: slideInDown 0.5s ease;
        }
        
        .alert-content {
            padding: 12px 0;
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
        }
        
        .alert-icon {
            font-size: 24px;
            color: #ff9800;
            margin-left: 15px;
        }
        
        .alert-title {
            font-weight: bold;
            color: #7d6608;
            margin-bottom: 5px;
        }
        
        .alert-message {
            color: #7d6608;
            margin-bottom: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: #7d6608;
            font-size: 18px;
            cursor: pointer;
            transition: color 0.3s;
            margin-right: 15px;
        }
        
        .close-btn:hover {
            color: #ff5722;
        }
        
        .show-alert-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .example-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #ffc107;
            left: 50%;
            margin-left: -2px;
        }
        
        .timeline-item {
            margin-bottom: 40px;
            position: relative;
            width: 50%;
            padding: 0 40px;
            box-sizing: border-box;
        }
        
        .timeline-item:nth-child(odd) {
            left: 0;
            text-align: left;
        }
        
        .timeline-item:nth-child(even) {
            left: 50%;
            text-align: right;
        }
        
        .timeline-content {
            padding: 20px;
            background: #fff8e1;
            border-radius: 10px;
            border: 1px solid #ffd54f;
            position: relative;
        }
        
        .timeline-content::after {
            content: '';
            position: absolute;
            top: 20px;
            width: 0;
            height: 0;
            border-style: solid;
        }
        
        .timeline-item:nth-child(odd) .timeline-content::after {
            right: -10px;
            border-width: 10px 0 10px 10px;
            border-color: transparent transparent transparent #ffd54f;
        }
        
        .timeline-item:nth-child(even) .timeline-content::after {
            left: -10px;
            border-width: 10px 10px 10px 0;
            border-color: transparent #ffd54f transparent transparent;
        }
        
        .timeline-time {
            font-weight: bold;
            color: #ff9800;
            margin-bottom: 5px;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
            }
            to {
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>

<!-- شريط التنبيه -->
<div class="time-alert-bar" id="timeAlertBar">
    <div class="alert-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="alert-icon">
                        <i class="fa fa-clock"></i>
                    </div>
                    <div>
                        <div class="alert-title">تنبيه هام: توقيت الفندق يبدأ وينتهي عند الساعة 12 ظهرًا</div>
                        <p class="alert-message mb-0">اليوم يبدأ من الساعة 12 ظهراً وينتهي 12 ظهراً من اليوم التالي. يرجى مراعاة ذلك عند إجراء الحجوزات.</p>
                    </div>
                </div>
                <button class="close-btn" id="closeAlert">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- محتوى الصفحة الرئيسية -->
<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <h1 class="text-center mb-4">نظام حجوزات الفندق</h1>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">معلومات النظام</h5>
                </div>
                <div class="card-body">
                    <p>مرحبًا بك في نظام حجوزات الفندق. يرجى الانتباه إلى أن نظام التوقيت في فندقنا يعمل بشكل مختلف عن التوقيت التقليدي.</p>
                    <p class="mb-0">اليوم الفندقي يبدأ من الساعة <strong>12 ظهرًا</strong> وينتهي في الساعة <strong>12 ظهرًا من اليوم التالي</strong>. هذا يعني أن الحجز الذي يبدأ يوم الاثنين الساعة 12 ظهراً ينتهي يوم الثلاثاء الساعة 12 ظهراً.</p>
                </div>
            </div>
            
            <div class="example-section">
                <h4 class="text-center mb-4">أمثلة على توقيت الحجوزات</h4>
                
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-time">الاثنين 12 ظهرًا - الثلاثاء 12 ظهرًا</div>
                            <p>حجز ليوم واحد يبدأ يوم الاثنين الساعة 12 ظهرًا وينتهي يوم الثلاثاء الساعة 12 ظهرًا</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-time">الثلاثاء 2 عصرًا - الأربعاء 12 ظهرًا</div>
                            <p>حجز يبدأ يوم الثلاثاء الساعة 2 عصرًا وينتهي يوم الأربعاء الساعة 12 ظهرًا</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-time">الأربعاء 10 صباحًا - الخميس 12 ظهرًا</div>
                            <p>حجز يبدأ يوم الأربعاء الساعة 10 صباحًا وينتهي يوم الخميس الساعة 12 ظهرًا</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- زر لإظهار التنبيه مرة أخرى -->
<button class="btn btn-warning show-alert-btn pulse" id="showAlertBtn" title="إظهار التنبيه">
    <i class="fa fa-clock"></i>
</button>


<script>
    $(document).ready(function() {
        // إخفاء الشريط عند النقر على زر الإغلاق
        $("#closeAlert").click(function() {
            $("#timeAlertBar").slideUp(300);
        });
        
        // إظهار الشريط عند النقر على زر الإظهار
        $("#showAlertBtn").click(function() {
            $("#timeAlertBar").slideDown(300);
            $(this).removeClass("pulse");
        });
        
        // تأثيرات jQuery UI على البطاقات
        $(".card").hover(
            function() {
                $(this).effect("highlight", { color: "#fff8e1" }, 1000);
            }
        );
        
        // تأثيرات على الأمثلة في الجدول الزمني
        $(".timeline-content").click(function() {
            $(this).effect("bounce", { times: 3, distance: 10 }, 300);
        });
    });
</script>

</body>
</html>
