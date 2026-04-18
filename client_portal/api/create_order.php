<?php
// client_portal/api/create_order.php
ob_start();
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db_connect.php';

api_require_post_csrf();
$clientId = api_require_login();
api_rate_limit_or_fail('client_portal_create_order', 20, 600, api_rate_limit_scope('create_order'));
$clientName = api_clean_text((string)($_SESSION[APP_CLIENT_SESSION_NAME_KEY] ?? $_SESSION['client_name'] ?? 'Portal User'), 120);

function detect_job_type(string $service): string
{
    if (strpos($service, 'علب') !== false || strpos($service, 'كرتون') !== false) {
        return 'carton';
    }
    if (strpos($service, 'بلاستيك') !== false || strpos($service, 'أكياس') !== false) {
        return 'plastic';
    }
    if (strpos($service, 'تسويق') !== false || strpos($service, 'سوشيال') !== false) {
        return 'social';
    }
    if (strpos($service, 'ويب') !== false || strpos($service, 'موقع') !== false) {
        return 'web';
    }
    if (strpos($service, 'تصميم') !== false) {
        return 'design_only';
    }
    return 'print';
}

function normalize_qty($rawQty): float
{
    $qty = (float) $rawQty;
    if ($qty <= 0) {
        return 1;
    }
    if ($qty > 100000000) {
        return 100000000;
    }
    return $qty;
}

$jobName = api_clean_text($_POST['job_name'] ?? 'طلب من البوابة', 180);
$serviceType = api_clean_text($_POST['service_type'] ?? 'عام', 80);
$quantity = normalize_qty($_POST['quantity'] ?? 1);
$userNotes = api_clean_text($_POST['details'] ?? '', 3000);
$designStatus = api_clean_text($_POST['design_status'] ?? 'needed', 40);
$jobType = detect_job_type($serviceType);

$details = [];
$details[] = '--- طلب وارد من بوابة العملاء ---';
$details[] = 'الخدمة المختارة: ' . $serviceType;

$qty = $quantity;
if ($jobType === 'design_only') {
    $qty = normalize_qty($_POST['design_items_count'] ?? $quantity);
    $details[] = 'عدد البنود: ' . $qty;
} elseif ($jobType === 'print') {
    $qty = normalize_qty($_POST['print_quantity'] ?? $quantity);
    $details[] = 'الكمية المطلوبة: ' . $qty;
    if (!empty($_POST['paper_type'])) {
        $details[] = 'الورق: ' . api_clean_text($_POST['paper_type'], 80);
    }
} elseif ($jobType === 'carton') {
    $qty = normalize_qty($_POST['carton_quantity'] ?? $quantity);
    $details[] = 'الكمية المطلوبة: ' . $qty;
} elseif ($jobType === 'plastic') {
    $qty = normalize_qty($_POST['plastic_quantity'] ?? $quantity);
    $details[] = 'الكمية: ' . $qty;
} elseif ($jobType === 'social') {
    $qty = normalize_qty($_POST['social_items_count'] ?? $quantity);
    $details[] = 'عدد البوستات: ' . $qty;
} elseif ($jobType === 'web') {
    $qty = 1;
    if (!empty($_POST['web_type'])) {
        $details[] = 'نوع الموقع: ' . api_clean_text($_POST['web_type'], 80);
    }
}

if ($userNotes !== '') {
    $details[] = '';
    $details[] = '--- ملاحظات العميل ---';
    $details[] = $userNotes;
}

$finalDetails = implode("\n", $details);
$currentStage = 'pending';

try {
    $insert = $pdo->prepare(
        'INSERT INTO job_orders
         (client_id, job_name, job_type, design_status, status, start_date, delivery_date, created_at, current_stage, quantity, added_by, job_details)
         VALUES
         (?, ?, ?, ?, "pending", NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), ?, ?, ?, ?)'
    );

    $insert->execute([$clientId, $jobName, $jobType, $designStatus, $currentStage, $qty, $clientName, $finalDetails]);
    $newJobId = (int) $pdo->lastInsertId();

    $uploadedCount = 0;
    if (isset($_FILES['attachment'])) {
        $allowExt = ['pdf', 'png', 'jpg', 'jpeg', 'ai', 'psd', 'cdr', 'zip', 'rar'];
        $maxSize = 10 * 1024 * 1024;

        $names = is_array($_FILES['attachment']['name']) ? $_FILES['attachment']['name'] : [$_FILES['attachment']['name']];
        $tmps = is_array($_FILES['attachment']['tmp_name']) ? $_FILES['attachment']['tmp_name'] : [$_FILES['attachment']['tmp_name']];
        $sizes = is_array($_FILES['attachment']['size']) ? $_FILES['attachment']['size'] : [$_FILES['attachment']['size']];
        $errors = is_array($_FILES['attachment']['error']) ? $_FILES['attachment']['error'] : [$_FILES['attachment']['error']];

        for ($i = 0; $i < count($names); $i++) {
            $originalName = (string)($names[$i] ?? '');
            $tmp = (string)($tmps[$i] ?? '');
            $size = (int)($sizes[$i] ?? 0);
            $error = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);

            if ($originalName === '' || $tmp === '' || $size <= 0 || $size > $maxSize || $error !== UPLOAD_ERR_OK) {
                continue;
            }

            $singleFile = [
                'name' => $originalName,
                'type' => (string)($_FILES['attachment']['type'][$i] ?? $_FILES['attachment']['type'] ?? ''),
                'tmp_name' => $tmp,
                'error' => $error,
                'size' => $size,
            ];
            $stored = app_store_uploaded_file($singleFile, [
                'dir' => dirname(__DIR__, 2) . '/uploads/job_files',
                'prefix' => 'portal_' . $newJobId . '_',
                'max_size' => $maxSize,
                'allowed_extensions' => $allowExt,
            ]);
            if (!$stored['ok']) {
                continue;
            }

            $dbPath = ltrim(str_replace(dirname(__DIR__, 2) . '/', '', (string)$stored['path']), '/');
            $fileStmt = $pdo->prepare(
                'INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by, created_at)
                 VALUES (?, ?, ?, "مرفق مبدئي", ?, NOW())'
            );
            $fileStmt->execute([$newJobId, $dbPath, $currentStage, $clientName]);
            $uploadedCount++;
        }
    }

    $fileStatus = $uploadedCount > 0 ? ' وتم رفع المرفقات بنجاح' : '';
    api_json([
        'status' => 'success',
        'message' => "تم إرسال الطلب رقم #{$newJobId} للإدارة للمراجعة{$fileStatus}"
    ]);
} catch (PDOException $e) {
    error_log('Create Order Error: ' . $e->getMessage());
    api_json(['status' => 'error', 'message' => 'تعذر حفظ الطلب حالياً، حاول مرة أخرى'], 500);
}
