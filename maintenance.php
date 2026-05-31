<?php
// maintenance.php - صفحة عرض الصيانة
session_start();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الصيانة - سوق الرمال</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: linear-gradient(135deg, #f5a623, #f8e1b0);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 30px;
            padding: 50px 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .icon {
            font-size: 70px;
            margin-bottom: 20px;
            animation: bounce 2s ease infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        h1 {
            color: #b8860b;
            font-size: 28px;
            margin-bottom: 15px;
        }
        .message {
            font-size: 18px;
            color: #555;
            line-height: 1.6;
            margin: 25px 0;
            padding: 15px;
            background: #fff8f0;
            border-radius: 15px;
            border-right: 4px solid #b8860b;
        }
        .divider {
            width: 60px;
            height: 3px;
            background: #f0e0c0;
            margin: 20px auto;
            border-radius: 3px;
        }
        .contact {
            font-size: 13px;
            color: #999;
            margin-top: 20px;
        }
        .admin-link {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f0e0c0;
        }
        .admin-link a {
            display: inline-block;
            background: #b8860b;
            color: white;
            text-decoration: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 14px;
            transition: 0.3s;
        }
        .admin-link a:hover {
            background: #9a7008;
            transform: translateY(-2px);
        }
        footer {
            margin-top: 30px;
            font-size: 11px;
            color: #bbb;
        }
        @media (max-width: 600px) {
            .container { padding: 30px 20px; }
            h1 { font-size: 24px; }
            .message { font-size: 15px; }
            .icon { font-size: 55px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>🏜️ سوق الرمال</h1>
        <div class="divider"></div>
        <div class="message">
            🔧 الموقع تحت الصيانة حالياً.<br>
            نعتذر عن الإزعاج، سيعود قريباً.
        </div>
        <div class="contact">
            📧 للاستفسار: <a href="mailto:info@souk-ramal.com" style="color:#b8860b;">info@souk-ramal.com</a>
        </div>
        <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
        <div class="admin-link">
            <a href="admin_dashboard.php?active_tab=maintenance">🔓 دخول كمدير (تعطيل وضع الصيانة)</a>
        </div>
        <?php endif; ?>
        <footer>
            نعتذر عن أي إزعاج، شكراً لتفهمك 💐
        </footer>
    </div>
</body>
</html>