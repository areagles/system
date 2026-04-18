<?php
// portal/api/request_quote.php
ob_start();
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db_connect.php';

api_require_post_csrf();
$clientId = api_require_login();
api_rate_limit_or_fail('client_portal_request_quote', 20, 600, api_rate_limit_scope('request_quote'));

$jobName = api_clean_text($_POST['job_name'] ?? 'طلب تسعير', 180);
$qty = (float) ($_POST['quantity'] ?? 1);
$qty = $qty > 0 ? $qty : 1;
$details = api_clean_text($_POST['details'] ?? '', 3000);
$service = api_clean_text($_POST['service_type'] ?? 'عام', 100);
$token = bin2hex(random_bytes(16));

$fullNotes = "نوع الخدمة: {$service}\nاسم المشروع: {$jobName}\nالكمية: {$qty}\nالتفاصيل: {$details}";

try {
    $stmt = $pdo->prepare(
        'INSERT INTO quotes (client_id, created_at, valid_until, total_amount, status, notes, access_token)
         VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 0.00, "pending", ?, ?)'
    );

    $stmt->execute([$clientId, $fullNotes, $token]);

    api_json([
        'status' => 'success',
        'message' => 'تم إرسال طلب التسعير بنجاح، سيقوم الفريق بمراجعته والرد قريباً.'
    ]);
} catch (PDOException $e) {
    error_log('Request Quote Error: ' . $e->getMessage());
    api_json(['status' => 'error', 'message' => 'تعذر إرسال طلب التسعير حالياً'], 500);
}
