<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <link rel="stylesheet" href="dist/Font-Awesome/CSS/font-awesome.min.css">
        <?php include_once "bootstrap.rtl.min.php"; ?>
              <link rel="stylesheet" href="jquery-ui.min.css">
  <!-- مكتبات JavaScript -->
  <script src="jquery-3.7.0.min.js"></script>
  <script src="jquery-ui.min.js"></script>
  <script src="chart.js"></script>
    <script src="jspdf.min.js"></script>
  <script src="jspdf.umd.min.js"></script>
    <script src="html2canvas.min.js"></script>
  <script src="bootstrap.min.js"></script>
  <script src="bootstrap.bundle.min.js"></script>
  <script src="popper.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سياسة الخصوصية واتفاقية المستخدم - نظام حجز الغرف</title>
  <style>
        :root {
            --primary-color: #3a6ea5;
            --secondary-color: #004e98;
            --accent-color: #ff6b6b;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            line-height: 1.8;
            color: #333;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .last-updated {
            text-align: center;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            color: var(--secondary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 1.5rem;
        }
        
        .section-title i {
            margin-left: 10px;
            background: var(--secondary-color);
            color: white;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .subsection {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-right: 4px solid var(--primary-color);
        }
        
        .subsection-title {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        ul {
            padding-right: 20px;
            margin: 10px 0;
        }
        
        li {
            margin-bottom: 8px;
        }
        
        .highlight {
            background-color: #fff9e6;
            padding: 15px;
            border-radius: 8px;
            border-right: 4px solid #ffc107;
            margin: 15px 0;
        }
        
        .contact-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 30px;
        }
        
        .contact-info h3 {
            color: var(--secondary-color);
            margin-bottom: 15px;
        }
        
        .contact-details {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .contact-item i {
            margin-left: 10px;
            color: var(--primary-color);
        }
        
        footer {
            text-align: center;
            padding: 20px;
            background: var(--dark-color);
            color: white;
        }
        
        .agree-section {
            text-align: center;
            margin: 25px 0;
            padding: 15px;
            background: #e8f5e9;
            border-radius: 8px;
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 10px;
            font-weight: 600;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .section-title {
                font-size: 1.3rem;
            }
            
            .contact-details {
                flex-direction: column;
                align-items: center;
            }
            
            header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-outline-primary" onclick="generatePDF()"><i class="fa fa-file-pdf-o"></i> تصدير إلى PDF</button>
            </div>
    <div id="reportContent" class="container">
        <header>
            <h1><i class="fa fa-shield"></i> سياسة الخصوصية واتفاقية المستخدم</h1>
            <p>نظام حجز الغرف - الإصدار 0.1</p>
        </header>
        
        <div class="content">
            <div class="last-updated">
                <i class="fa fa-history"></i> آخر تحديث: 9 سبتمبر  2025            </div>
            
            <div class="section">
                <h2 class="section-title"><i class="fa fa-info-circle"></i> مقدمة</h2>
                <p>مرحبًا بكم في نظام حجز الغرف. تحدد سياسة الخصوصية هذه كيفية
                التعامل مع معلوماتكم الشخصية عند استخدامكم لنظام حجز الغرف. باستخدامكم
                للنظام، فإنكم توافقون على الممارسات الموضحة في هذه السياسة.</p>
            </div>
            
            <div class="section">
                <h2 class="section-title"><i class="fa fa-info"></i> المعلومات التي نجمعها</h2>
                
                <div class="subsection">
                    <h3 class="subsection-title">المعلومات الشخصية</h3>
                    <p>عند إجراء حجز، قد نجمع المعلومات التالية:</p>
                    <ul>
                        <li><i class="fa fa-user"></i> الاسم الكامل</li>
                        <li><i class="fa fa-phone"></i> رقم الهاتف</li>
                        <li><i class="fa fa-credit-card"></i> معلومات الدفع (يتم معالجتها بشكل آمن)</li>
                        <li><i class="fa fa-user"></i> معلومات هوية أخرى عند الضرورة</li>
                    </ul>
                </div>
                
                <div class="subsection">
                    <h3 class="subsection-title">معلومات الاستخدام</h3>
                    <p>نقوم تلقائيًا بجمع معلومات معينة حول كيفية استخدامكم للنظام، بما في ذلك:</p>
                    <ul>
                        <li><i class="fa fa-desktop"></i> نوع الجهاز والمتصفح</li>
                        <li><i class="fa fa-globe"></i> عنوان IP والموقع التقريبي</li>
                        <li><i class="fa fa-check"></i> أوقات الوصول ومدة الاستخدام</li>
                        <li><i class="fa fa-search"></i> الصفحات التي تم زيارتها والإجراءات المتخذة</li>
                    </ul>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title"><i class="fa fa-lock"></i> كيفية استخدام معلوماتكم</h2>
                <p>نستخدم المعلومات التي نجمعها للأغراض التالية:</p>
                <ul>
                    <li>معالجة الحجوزات وإدارة الحسابات</li>
                    <li>تحسين تجربة المستخدم وتطوير النظام</li>
                    <li> حساب عدد ايام الحجز والتحديثات</li>
                    <li>توفير خدمة العملاء والدعم</li>
                    <li>الامتثال للالتزامات القانونية والتنظيمية</li>
                </ul>
                
                <div class="highlight">
                    <p><i class="fa fa-exclamation-circle"></i> <strong>ملاحظة هامة:</strong> نحن لا نبيع أو نؤجر معلوماتكم الشخصية إلى أطراف ثالثة لأغراض التسويق.</p>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title"><i class="fa fa-database"></i> حماية المعلومات</h2>
                <p>نحن نعمل على حماية معلوماتكم الشخصية من خلال:</p>
                <ul>
                    <li>استخدام تشفير SSL لتأمين نقل البيانات</li>
                    <li>تخزين المعلومات في بيئات آمنة ومحمية</li>
                    <li>الحد من الوصول إلى المعلومات للموظفين المصرح لهم فقط</li>
                    <li>      الكود يتم اختباره بشكل دوري للامان ،وقد تم
                    سد جميع الثغرات الموجودة حتى تاريخ اخر تحديث.      </li>                    <li>مراجعة وتحديث ممارسات
                    الأمان بشكل منتظم</li>
                </ul>
            </div>
            
            <div class="section">
                <h2 class="section-title"><i class="fa fa-cogs"></i> ملفات تعريف الارتباط (Cookies)</h2>
                <p>نستخدم ملفات تعريف الارتباط لتذكر تفضيلاتكم وتحسين تجربة استخدام النظام. يمكنكم ضبط إعدادات المتصفح لرفض ملفات تعريف الارتباط، لكن هذا قد يؤثر على بعض وظائف النظام.</p>
            </div>
            
            <div class="section">
                <h2 class="section-title"><i class="fa fa-users"></i> حقوقكم</h2>
                <p>لديكم الحق في:</p>
                <ul>
                    <li>الوصول إلى معلوماتكم الشخصية التي نحتفظ بها</li>
                    <li>طلب تصحيح المعلومات غير الدقيقة</li>
                    <li>طلب حذف معلوماتكم الشخصية في ظروف معينة</li>
                    <li>معارضة معالجة معلوماتكم لأغراض محددة</li>
                    <li>طلب نسخة من معلوماتكم بصيغة قابلة للنقل</li>
                </ul>
            </div>
            
            <div class="section">
                <h2 class="section-title"><i class="fa fa-balance-scale"></i> اتفاقية المستخدم</h2>
                <p>باستخدامكم لنظام حجز الغرف، فإنكم توافقون على:</p>
                <ul>
                    <li>تقديم معلومات دقيقة وصحيحة</li>
                    <li>الحفاظ على سرية معلومات حسابكم</li>
                    <li>استخدام النظام لأغراض مشروعة فقط</li>
                    <li>الالتزام بسياسات الإلغاء والاسترداد</li>
                    <li>عدم إساءة استخدام النظام أو محاولة اختراقه</li>
                </ul>
                
                <div class="highlight">
                    <p><i class="fa fa-exclamation-triangle"></i> <strong>تحذير:</strong> قد يؤدي انتهاك شروط الاستخدام هذه إلى تعليق أو إنهاء حقكم في استخدام النظام.</p>
                </div>
            </div>
            
            <div class="agree-section">
                <h3><i class="fa fa-handshake"></i> موافقة على الشروط</h3>
                <p>باستمراركم في استخدام نظام حجز الغرف، فإنكم تقرون بأنكم قد قرأتم وفهمتكم وتوافقون على الالتزام بسياسة الخصوصية وشروط الاستخدام هذه.</p>
                <div style="margin-top: 20px;">
                    <button class="btn" onclick="acceptPolicy()"><i class="fa fa-check"></i> أوافق على الشروط</button>
                    <button class="btn btn-outline" onclick="declinePolicy()"><i class="fa fa-times"></i> لا أوافق</button>
                </div>
            </div>
            
            <div class="contact-info">
                <h3><i class="fa fa-headset"></i> للاستفسارات أو الأسئلة</h3>
                <p>إذا كانت لديكم أي أسئلة حول سياسة الخصوصية أو اتفاقية المستخدم، يرجى التواصل معنا:</p>
                
                <div class="contact-details">
                    <div class="contact-item">
                        <i class="fa fa-envelope"></i>
                        <span type='email'>hozaifa01@gmail.com</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa fa-phone"></i>
                        <span>+249 9 0381 4680</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa fa-clock"></i>
                        <span>من الأحد إلى الخميس  </span>
                    </div>
                </div>
            </div>
        </div>
        
        <footer>
            <p>© 2025 نظام حجز الغرف الفندقية. جميع الحقوق محفوظة.</p>
        </footer>
    </div>

    <script>
        function acceptPolicy() {
            alert('شكرًا لموافقتكم على شروط الاستخدام وسياسة الخصوصية. يمكنكم الآن استخدام نظام حجز الغرف بشكل كامل.');
            // هنا يمكنك إضافة كود لتخزين موافقة المستخدم في قاعدة البيانات
        }
        
        function declinePolicy() {
            if(confirm('عذرًا، لا يمكنكم استخدام نظام حجز الغرف دون الموافقة على الشروط والأحكام. هل ترغب في مغادرة الصفحة؟')) {
                window.location.href = 'index.php'; // أو الصفحة الرئيسية الخاصة بك
            }
        }
        
        // تأثيرات بسيطة عند التمرير
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.section');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            sections.forEach(section => {
                section.style.opacity = 0;
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(section);
            });
        });
        
// وظيفة تصدير PDF
function generatePDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF('p', 'pt', 'a4');

  const element = document.getElementById("reportContent");

  html2canvas(element, {
    scale: 2, // جودة أعلى
    useCORS: true,
    windowHeight: element.scrollHeight 
  }).then(canvas => {
    const imgData = canvas.toDataURL("image/png");
    const pageWidth = doc.internal.pageSize.getWidth();
    // const pageHeight = doc.internal.pageSize.getHeight(); // غير مستخدم مباشرة هنا

    // حساب الأبعاد للحفاظ على نسبة العرض إلى الارتفاع
    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;
    const ratio = canvasWidth / canvasHeight;
    const imgWidth = pageWidth - 40; // مع هوامش
    const imgHeight = imgWidth / ratio;
    
    let heightLeft = imgHeight;
    let position = 20; // الهامش العلوي

    doc.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight);
    heightLeft -= doc.internal.pageSize.getHeight();

    // إضافة صفحات جديدة إذا كان المحتوى أطول من صفحة واحدة
    while (heightLeft > 0) {
      position = -imgHeight + heightLeft;
      doc.addPage();
      doc.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight);
      heightLeft -= doc.internal.pageSize.getHeight();
    }
    
    doc.save("تقرير-الفترة-المحددة.pdf");
  });
}
    </script>
    
</body>
</html>