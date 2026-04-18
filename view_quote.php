<?php
// view_quote.php - النسخة الماسية (متوافق 100% مع الموبايل)
ob_start();
require 'config.php'; 
app_start_session();
app_ensure_quotes_schema($conn);
app_ensure_taxation_schema($conn);

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$appLogo = app_brand_logo_path($conn, 'assets/img/Logo.png');
$brandProfile = app_brand_profile($conn);
$outputTheme = app_brand_output_theme($conn);
$outputShowFooter = !empty($brandProfile['show_footer']);
$outputShowQr = !empty($brandProfile['show_qr']);
$footerLines = app_brand_output_lines($brandProfile, 'footer', true);
$legacyFooterLine1 = trim(app_setting_get($conn, 'brand_footer_line1', $appName));
$legacyFooterLine2 = trim(app_setting_get($conn, 'brand_footer_line2', ''));
$legacyFooterLine3 = trim(app_setting_get($conn, 'brand_footer_line3', (string)parse_url(SYSTEM_URL, PHP_URL_HOST)));
if (empty($footerLines)) {
    $footerLines = array_values(array_filter([$legacyFooterLine1, $legacyFooterLine2, $legacyFooterLine3], static function ($line): bool {
        return trim((string)$line) !== '';
    }));
}

// 1. تحديد طريقة الوصول
$quote = null;
$access_mode = '';
$stmt = null;

if (isset($_GET['token'])) {
    $token = trim((string)$_GET['token']);
    $stmt = $conn->prepare("
        SELECT q.*, c.name as client_name, c.phone as client_phone, c.address as client_addr
        FROM quotes q
        JOIN clients c ON q.client_id = c.id
        WHERE q.access_token = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $access_mode = 'token';
} elseif (isset($_GET['id'])) {
    require_once 'auth.php'; 
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("
        SELECT q.*, c.name as client_name, c.phone as client_phone, c.address as client_addr
        FROM quotes q
        JOIN clients c ON q.client_id = c.id
        WHERE q.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $id);
    $access_mode = 'id';
} else {
    die("<div style='text-align:center; padding:50px; color:white; background:#000;'>رابط غير صالح.</div>");
}

$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows == 0) die("<div style='text-align:center; padding:50px; color:white; background:#000;'>العرض غير موجود.</div>");
$quote = $res->fetch_assoc();
$stmt->close();
$quote_id = $quote['id'];
$linkedInvoiceId = (int)($quote['converted_invoice_id'] ?? 0);
$linkedInvoiceToken = $linkedInvoiceId > 0 ? app_public_token('invoice_view', $linkedInvoiceId) : '';
$conversionNotice = '';
$conversionError = '';
$flowNotice = '';
$flowNoticeTone = 'info';
$status_value = strtolower((string)($quote['status'] ?? 'pending'));
if (!in_array($status_value, ['pending', 'approved', 'rejected'], true)) {
    $status_value = 'pending';
}
$quote_access_token = (string)($quote['access_token'] ?? '');
$client_phone_digits = preg_replace('/\D+/', '', (string)($quote['client_phone'] ?? ''));
$whatsapp_phone = ltrim($client_phone_digits, '0');
if ($whatsapp_phone !== '' && strpos($whatsapp_phone, '20') !== 0) {
    $whatsapp_phone = '20' . $whatsapp_phone;
}

$buildQuoteRedirect = static function (array $params = []) use ($access_mode, $quote_access_token, $quote_id): string {
    $base = ($access_mode === 'token')
        ? ('?token=' . rawurlencode($quote_access_token))
        : ('?id=' . intval($quote_id));
    if (empty($params)) {
        return $base;
    }

    return $base . '&' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

$backHref = $access_mode === 'id' ? 'quotes.php' : 'javascript:history.back()';
$backLabel = $access_mode === 'id' ? 'رجوع للعروض' : 'رجوع';

// 2. معالجة القرار
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (!app_verify_csrf($_POST['_csrf_token'] ?? '')) {
        http_response_code(419);
        die("<div style='text-align:center; padding:50px; color:white; background:#000;'>انتهت صلاحية الجلسة. أعد تحميل الصفحة.</div>");
    }

    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, ['approve', 'reject', 'convert_to_invoice'], true)) {
        $client_note = trim((string)($_POST['client_note'] ?? ''));
        $client_note = mb_substr($client_note, 0, 2000);
        if (in_array($action, ['approve', 'reject'], true)) {
            $status = ($action === 'approve') ? 'approved' : 'rejected';

            $update_stmt = $conn->prepare("UPDATE quotes SET status = ?, client_comment = ? WHERE id = ?");
            $update_stmt->bind_param('ssi', $status, $client_note, $quote_id);
            $update_stmt->execute();
            $update_stmt->close();
        }

        if ($access_mode === 'id' && ($action === 'approve' || $action === 'convert_to_invoice')) {
            $actorName = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'Quote Approval'));
            $conversion = app_quote_convert_to_invoice($conn, (int)$quote_id, $actorName);
            if (empty($conversion['ok'])) {
                app_safe_redirect($buildQuoteRedirect([
                    'decision' => $action === 'approve' ? 'approved' : 'converted',
                    'conversion_error' => (string)($conversion['error'] ?? 'conversion_failed'),
                ]), 'view_quote.php');
            } else {
                $invoiceId = (int)($conversion['invoice_id'] ?? 0);
                app_safe_redirect($buildQuoteRedirect([
                    'decision' => $action === 'approve' ? 'approved' : 'converted',
                    'converted_invoice_id' => $invoiceId,
                    'already_converted' => !empty($conversion['already_converted']) ? '1' : '0',
                ]), 'view_quote.php');
            }
        }

        app_safe_redirect($buildQuoteRedirect([
            'decision' => $action === 'reject' ? 'rejected' : 'updated',
        ]), 'view_quote.php');
    }
}

if (isset($_GET['converted_invoice_id'])) {
    $linkedInvoiceId = max(0, (int)$_GET['converted_invoice_id']);
    if ($linkedInvoiceId > 0) {
        $linkedInvoiceToken = app_public_token('invoice_view', $linkedInvoiceId);
        $conversionNotice = 'تم إنشاء فاتورة تشغيلية من هذا العرض بنجاح.';
    }
}
if (isset($_GET['conversion_error'])) {
    $conversionError = trim((string)$_GET['conversion_error']);
}
if (isset($_GET['decision'])) {
    $decision = strtolower(trim((string)$_GET['decision']));
    $alreadyConverted = trim((string)($_GET['already_converted'] ?? '0')) === '1';
    if ($decision === 'approved') {
        $flowNoticeTone = $conversionError === '' ? 'success' : 'warning';
        if ($access_mode === 'token') {
            $flowNotice = 'تم اعتماد العرض بنجاح وإرسال الموافقة للإدارة لاستكمال التنفيذ الداخلي.';
        } elseif ($conversionError === '') {
            $flowNotice = $alreadyConverted
                ? 'تم اعتماد العرض بنجاح. الفاتورة التشغيلية كانت منشأة بالفعل وتم ربطك بها.'
                : 'تم اعتماد العرض بنجاح وإنشاء الفاتورة التشغيلية تلقائيًا.';
        } else {
            $flowNotice = 'تم اعتماد العرض بنجاح، لكن تعذر إنشاء الفاتورة التشغيلية تلقائيًا الآن. ستتم المتابعة من الإدارة.';
        }
    } elseif ($decision === 'rejected') {
        $flowNoticeTone = 'danger';
        $flowNotice = 'تم تسجيل اعتذارك/رفضك للعرض وإرسال ملاحظتك للإدارة.';
    } elseif ($decision === 'converted') {
        $flowNoticeTone = $conversionError === '' ? 'success' : 'warning';
        $flowNotice = $conversionError === ''
            ? ($alreadyConverted
                ? 'الفاتورة التشغيلية موجودة بالفعل لهذا العرض.'
                : 'تم إنشاء الفاتورة التشغيلية لهذا العرض بنجاح.')
            : 'تعذر إنشاء الفاتورة التشغيلية حاليًا. حاول مرة أخرى بعد لحظات.';
    }
}

$items = $conn->query("SELECT * FROM quote_items WHERE quote_id = $quote_id");
$quoteTaxLines = app_tax_decode_lines((string)($quote['taxes_json'] ?? '[]'));

$public_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$public_link = app_base_url() . $public_dir . "/view_quote.php?token=" . rawurlencode($quote_access_token);
$wa_msg = "مرحباً أ/" . (string)($quote['client_name'] ?? '') . "،\nمرفق عرض السعر من " . (string)$appName . ":\n" . $public_link;
$qrPayload = app_brand_qr_payload($brandProfile, [
    'Document' => 'Quotation View',
    'Quote ID' => (string)($quote['quote_number'] ?? $quote_id),
    'Client' => (string)($quote['client_name'] ?? ''),
    'Total' => number_format((float)($quote['total_amount'] ?? 0), 2, '.', ''),
    'Link' => $public_link,
]);
$qrUrl = ($outputShowQr && $qrPayload !== '') ? app_brand_qr_url($qrPayload, 140) : '';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>عرض سعر #<?php echo $quote_id; ?> | <?php echo app_h($appName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- المتغيرات الملكية --- */
        :root { 
            --bg-body: <?php echo app_h($outputTheme['bg']); ?>; --card-bg: <?php echo app_h($outputTheme['card']); ?>; --gold: <?php echo app_h($outputTheme['accent']); ?>;
            --gold-soft: <?php echo app_h($outputTheme['accent_soft']); ?>; --paper: <?php echo app_h($outputTheme['paper']); ?>; --ink: <?php echo app_h($outputTheme['ink']); ?>;
            --text-main: #ffffff; --text-sub: #aaaaaa; --border: rgba(255,255,255,0.12); --line: <?php echo app_h($outputTheme['line']); ?>; --tint: <?php echo app_h($outputTheme['tint']); ?>;
        }

        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Cairo', sans-serif; margin: 0; padding: 15px; }
        
        .container { 
            max-width: 860px; margin: 0 auto; 
            background: var(--card-bg); 
            border-radius: 22px; 
            box-shadow: 0 24px 60px rgba(0,0,0,0.45);
            position: relative; margin-bottom: 100px; 
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--gold), #b8860b, var(--gold));
        }

        .invoice-box { padding: 42px; }
        .quote-top-actions { display:flex; justify-content:flex-start; margin-bottom:18px; }
        .quote-top-back {
            display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:999px;
            text-decoration:none; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.10);
            color:var(--gold); font-weight:700; transition:0.3s;
        }
        .quote-top-back:hover { transform:translateY(-2px); background:var(--tint); }

        /* --- الهيدر المركزي الجديد --- */
        .header { 
            display: flex; justify-content: space-between; align-items: center; 
            border-bottom: 1px solid rgba(255,255,255,0.10); padding-bottom: 20px; margin-bottom: 24px; 
        }

        .header-side { flex: 1; text-align: center; }
        .header-side.right { text-align: right; }
        .header-side.left { text-align: left; }

        .invoice-title { font-size: 2rem; font-weight: 900; color: var(--gold-soft); margin: 0; line-height: 1; }
        .invoice-id { font-size: 1rem; color: #fff; letter-spacing: 2px; margin-top: 5px; opacity: 0.8; font-family: monospace; }

        .logo-img { width: 90px; max-width: 100%; display: block; margin: 0 auto; }

        .date-item { font-size: 0.85rem; color: var(--text-sub); margin-bottom: 4px; }
        .date-item strong { color: #fff; display: inline-block; width: 70px; }

        /* الأقسام الأخرى */
        .client-section { margin-bottom: 30px; background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)); padding: 18px; border-radius: 16px; border-right: 4px solid var(--gold); }
        .section-label { font-size: 0.8rem; color: var(--gold-soft); text-transform: uppercase; margin-bottom: 5px; font-weight: bold; }
        .client-name { font-size: 1.3rem; font-weight: 700; margin: 0; color: #fff; }
        .client-details { font-size: 0.9rem; color: var(--text-sub); margin: 3px 0 0 0; }

        /* الجدول */
        .items-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; }
        .items-table th { background: var(--tint); color: var(--gold); padding: 12px; text-align: center; font-size: 0.9rem; border-bottom: 1px solid rgba(255,255,255,0.08); font-weight: bold; }
        .items-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; font-size: 0.95rem; color: #eee; vertical-align: middle; }
        .items-table td.desc { text-align: right; width: 50%; color: #fff; }
        .total-row td { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; font-weight: 900; font-size: 1.2rem; border: none; }
        .total-row td:first-child { border-radius: 0 10px 10px 0; }
        .total-row td:last-child { border-radius: 10px 0 0 10px; }

        /* الشروط */
        .terms-box { border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 14px; font-size: 0.85rem; color: var(--text-sub); line-height: 1.8; page-break-inside: avoid; background: rgba(0,0,0,0.2); }
        .terms-box strong { color: var(--gold-soft); display: block; margin-bottom: 8px; font-size: 0.95rem; }

        /* الفوتر */
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.09); font-size: 0.8rem; color: var(--text-sub); line-height: 1.8; }
        .footer-meta { display:flex; justify-content:space-between; align-items:flex-end; gap:14px; flex-wrap:wrap; }
        .footer-lines { flex:1; min-width:220px; text-align:right; }
        .footer-lines p { margin:0 0 4px; }
        .footer-lines p:last-child { margin-bottom:0; }
        .footer-qr img { width:90px; height:90px; border:1px solid var(--line); border-radius:8px; background:#fff; padding:4px; }
        .footer a { color: var(--gold); text-decoration: none; }

        /* ختم الحالة */
        .status-stamp { 
            position: absolute; top: 150px; left: 50%; transform: translateX(-50%) rotate(-10deg);
            padding: 10px 40px; border: 4px double; 
            font-weight: 900; text-transform: uppercase; 
            opacity: 0.15; font-size: 3rem; letter-spacing: 10px;
            z-index: 0; pointer-events: none; white-space: nowrap;
        }
        .st-pending { color: #f39c12; border-color: #f39c12; }
        .st-approved { color: #2ecc71; border-color: #2ecc71; opacity: 0.8; }
        .st-rejected { color: #e74c3c; border-color: #e74c3c; opacity: 0.8; }

        /* --- منطقة القرار --- */
        .decision-area { 
            background: linear-gradient(135deg, rgba(255,255,255,0.04) 0%, rgba(0,0,0,0.18) 100%);
            border: 1px solid rgba(255,255,255,0.10); padding: 30px; border-radius: 20px; 
            margin-top: 40px; text-align: center; page-break-inside: avoid;
            box-shadow: 0 0 30px rgba(0,0,0,0.18);
            position: relative; overflow: hidden;
        }
        .decision-title { font-weight: 800; margin-bottom: 20px; font-size: 1.2rem; color: #fff; }
        .decision-btns { display: flex; gap: 20px; justify-content: center; margin-top: 20px; position: relative; z-index: 2; }

        .btn-decision { 
            padding: 15px 35px; border-radius: 50px; border: none; font-weight: 800; 
            cursor: pointer; font-size: 1rem; transition: 0.3s; color: #fff;
            display: inline-flex; align-items: center; gap: 10px;
        }
        .btn-accept { background: linear-gradient(45deg, #27ae60, #2ecc71); box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3); }
        .btn-accept:hover { transform: translateY(-3px); }
        .btn-reject { background: rgba(231, 76, 60, 0.1); border: 1px solid #c0392b; color: #e74c3c; }
        .btn-reject:hover { background: #c0392b; color: #fff; }

        /* --- حقل الملاحظات الذهبي المتحرك --- */
        .client-note-wrapper { position: relative; max-width: 600px; margin: 0 auto; }
        .client-note { 
            width: 100%; padding: 15px; border: 1px solid rgba(255,255,255,0.10); border-radius: 12px; 
            margin-bottom: 10px; font-family: 'Cairo'; resize: vertical;
            background: rgba(0,0,0,0.18); color: #fff; transition: 0.3s;
            position: relative; z-index: 2; outline: none;
        }
        @keyframes borderPulse {
            0% { box-shadow: 0 0 5px rgba(212, 175, 55, 0.1); border-color: #444; }
            50% { box-shadow: 0 0 15px rgba(212, 175, 55, 0.4); border-color: var(--gold); }
            100% { box-shadow: 0 0 5px rgba(212, 175, 55, 0.1); border-color: #444; }
        }
        .client-note:focus { animation: borderPulse 3s infinite; }

        /* شريط التحكم */
        .actions-bar {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(10px);
            padding: 10px 30px; border-radius: 50px; width: 90%; max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8); display: flex; gap: 10px; justify-content: center;
            border: 1px solid var(--gold); z-index: 1000;
        }
        .btn { border: none; padding: 10px 20px; border-radius: 30px; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.9rem; font-family: 'Cairo'; color: #fff; transition: 0.3s; flex: 1; white-space: nowrap; }
        .btn:hover { transform: translateY(-3px); }
        .btn-print { background: var(--gold); color: #000; }
        .btn-wa { background: #2ecc71; }
        .btn-back { background: #333; }

        /* =========================================
           📱 تحسينات الموبايل القوية (Mobile Force)
           ========================================= */
        @media (max-width: 768px) {
            .invoice-box { padding: 20px 15px; }
            .quote-top-actions { margin-bottom:14px; }
            
            /* الهيدر العمودي */
            .header { flex-direction: column; text-align: center; gap: 20px; }
            .header-side { width: 100%; text-align: center !important; }
            .header-side.center { order: -1; margin-bottom: 10px; } /* اللوجو الأول */
            .logo-img { width: 60px; max-width: 80%; }
            .invoice-title { font-size: 1.8rem; }
            
            /* الجدول المتجاوب */
            .items-table { display: block; overflow-x: auto; white-space: nowrap; }
            .items-table th, .items-table td { padding: 10px 5px; font-size: 0.85rem; }
            
            /* أزرار القرار */
            .decision-btns { flex-direction: column; gap: 10px; }
            .btn-decision { width: 100%; justify-content: center; }
            
            /* شريط التحكم */
            .actions-bar { padding: 10px; bottom: 10px; flex-wrap: wrap; }
            .btn { font-size: 0.8rem; padding: 8px 5px; }
            
            /* الختم */
            .status-stamp { font-size: 1.5rem; top: auto; bottom: 20%; opacity: 0.1; }
        }

        /* =========================================
           وضع الطباعة (Clean White Paper)
           ========================================= */
        @media print {
            body { background: #fff; color: #000; padding: 0; margin: 0; }
            .container { box-shadow: none; border: none; margin: 0; max-width: 100%; width: 100%; background: #fff; color: #000; border-radius: 0; }
            .container::before { display: none; }
            .invoice-box { padding: 0; }
            
            .header { border-bottom: 2px solid #000; flex-direction: row; text-align: left; }
            .header-side { text-align: inherit; }
            .header-side.right { text-align: right; }
            .invoice-title { color: #000; text-shadow: none; }
            .invoice-id { color: #333; }
            .date-item { color: #000; }
            .date-item strong { color: #000; }
            .logo-img { width: 45px; margin: 0; }
            
            .client-section { background: #fff; border: 1px solid #ddd; border-right: 4px solid #000; }
            .section-label { color: #000; }
            .client-name { color: #000; }
            .client-details { color: #333; }
            
            .items-table { display: table; width: 100%; overflow: visible; white-space: normal; }
            .items-table th { background: #f0f0f0; color: #000; border-bottom: 2px solid #000; }
            .items-table td { color: #000; border-bottom: 1px solid #ddd; }
            .total-row td { background: #eee !important; color: #000 !important; -webkit-print-color-adjust: exact; }
            
            .terms-box { border: 1px solid #ccc; background: #fff; color: #000; }
            .terms-box strong { color: #000; }
            
            .footer { color: #000; border-top: 1px solid #ccc; }
            .footer-qr img { border-color:#aaa; }
            .footer a { color: #000; }
            
            .actions-bar, .decision-area { display: none !important; }
            .status-stamp { opacity: 0.1; color: #000 !important; border-color: #000 !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-box">
        <div class="quote-top-actions">
            <a href="<?php echo app_h($backHref); ?>" class="quote-top-back">
                <i class="fa-solid fa-arrow-right"></i> <?php echo app_h($backLabel); ?>
            </a>
        </div>
        
        <div class="status-stamp st-<?php echo app_h($status_value); ?>">
            <?php 
            if($status_value == 'pending') echo 'PENDING';
            elseif($status_value == 'approved') echo 'APPROVED';
            else echo 'REJECTED';
            ?>
        </div>

        <div class="header">
            <div class="header-side right">
                <h1 class="invoice-title">عرض سعر</h1>
                <div class="invoice-id">#<?php echo str_pad($quote_id, 4, '0', STR_PAD_LEFT); ?></div>
                <?php if (!empty($quote['pricing_source_ref'])): ?>
                    <div class="invoice-id" style="font-size:.95rem;opacity:.8;">PRC: <?php echo app_h((string)$quote['pricing_source_ref']); ?></div>
                <?php endif; ?>
            </div>

            <div class="header-side center">
                <img src="<?php echo app_h($appLogo); ?>" alt="<?php echo app_h($appName); ?>" class="logo-img">
            </div>

            <div class="header-side left">
                    <div class="date-item"><strong>الإصدار:</strong> <?php echo app_h((string)$quote['created_at']); ?></div>
                    <div class="date-item"><strong>الصلاحية:</strong> <?php echo app_h((string)$quote['valid_until']); ?></div>
                </div>
            </div>

        <div class="client-section">
            <div class="section-label">مقدم إلى السيد / السادة:</div>
            <h2 class="client-name"><?php echo app_h((string)$quote['client_name']); ?></h2>
            <p class="client-details">
                <i class="fa-solid fa-phone"></i> <?php echo app_h((string)$quote['client_phone']); ?> 
                <?php if(!empty($quote['client_addr'])) echo " &nbsp;|&nbsp; <i class='fa-solid fa-location-dot'></i> " . app_h((string)$quote['client_addr']); ?>
            </p>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="50%">البيان / الوصف</th>
                    <th width="15%">الكمية / الوحدة</th>
                    <th width="15%">السعر</th>
                    <th width="15%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                while($item = $items->fetch_assoc()): 
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td class="desc"><?php echo nl2br(app_h((string)$item['item_name'])); ?></td>
                    <?php
                        $unitVal = trim((string)($item['unit'] ?? ''));
                        $qtyLabel = number_format((float)$item['quantity'], 2);
                        if ($unitVal !== '') {
                            $qtyLabel .= ' ' . app_h($unitVal);
                        }
                    ?>
                    <td><?php echo $qtyLabel; ?></td>
                    <td><?php echo number_format((float)$item['price'], 2); ?></td>
                    <td><?php echo number_format((float)$item['total'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
                
                <?php foreach ($quoteTaxLines as $taxLine): ?>
                <?php
                    $taxMode = (string)($taxLine['mode'] ?? 'add');
                    $taxName = (string)($taxLine['name'] ?? 'ضريبة');
                    $taxAmount = (float)($taxLine['amount'] ?? 0);
                ?>
                <tr>
                    <td colspan="4" style="text-align:left; padding-left:20px; color:<?php echo ($taxMode === 'subtract') ? '#ff9f9f' : 'var(--gold)'; ?>;">
                        <?php echo app_h($taxName); ?>
                    </td>
                    <td style="color:<?php echo ($taxMode === 'subtract') ? '#ff9f9f' : 'var(--gold)'; ?>;"><?php echo ($taxMode === 'subtract' ? '-' : '+') . number_format($taxAmount, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4" style="text-align: left; padding-left: 20px;">الإجمالي النهائي (EGP)</td>
                    <td><?php echo number_format((float)$quote['total_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="terms-box">
            <strong><i class="fa-solid fa-gavel"></i> الشروط والأحكام والملاحظات:</strong>
            <?php echo nl2br(app_h((string)$quote['notes'])); ?>
        </div>

        <?php if ($flowNotice !== ''): ?>
        <?php
            $flowBg = 'rgba(52,152,219,0.08)';
            $flowBorder = 'rgba(52,152,219,0.35)';
            $flowColor = '#bfe6ff';
            if ($flowNoticeTone === 'success') {
                $flowBg = 'rgba(46,204,113,0.08)';
                $flowBorder = 'rgba(46,204,113,0.35)';
                $flowColor = '#bdf3cf';
            } elseif ($flowNoticeTone === 'warning') {
                $flowBg = 'rgba(241,196,15,0.08)';
                $flowBorder = 'rgba(241,196,15,0.35)';
                $flowColor = '#ffe9a6';
            } elseif ($flowNoticeTone === 'danger') {
                $flowBg = 'rgba(231,76,60,0.08)';
                $flowBorder = 'rgba(231,76,60,0.35)';
                $flowColor = '#ffb7b7';
            }
        ?>
        <div style="margin-top:18px; padding:14px 16px; border-radius:12px; background:<?php echo $flowBg; ?>; border:1px solid <?php echo $flowBorder; ?>; color:<?php echo $flowColor; ?>; text-align:center;">
            <i class="fa-solid fa-circle-info"></i> <?php echo app_h($flowNotice); ?>
        </div>
        <?php endif; ?>
        <?php if ($conversionNotice !== ''): ?>
        <div style="margin-top:18px; padding:14px 16px; border-radius:12px; background:rgba(46,204,113,0.08); border:1px solid rgba(46,204,113,0.35); color:#bdf3cf; text-align:center;">
            <i class="fa-solid fa-file-invoice-dollar"></i> <?php echo app_h($conversionNotice); ?>
        </div>
        <?php endif; ?>
        <?php if ($conversionError !== ''): ?>
        <div style="margin-top:18px; padding:14px 16px; border-radius:12px; background:rgba(231,76,60,0.08); border:1px solid rgba(231,76,60,0.35); color:#ffb7b7; text-align:center;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo app_h('تعذر تحويل العرض إلى فاتورة حالياً: ' . $conversionError); ?>
        </div>
        <?php endif; ?>

        <?php if($status_value == 'pending'): ?>
        <div class="decision-area">
            <div class="decision-title">يرجى اتخاذ قرار بشأن هذا العرض</div>
            <form method="POST" action="">
                <?php echo app_csrf_input(); ?>
                <div class="client-note-wrapper">
                    <textarea name="client_note" class="client-note" placeholder="هل لديك أي ملاحظات أو استفسارات؟ اكتبها هنا..."></textarea>
                </div>
                <div class="decision-btns">
                    <button type="submit" name="action" value="approve" class="btn-decision btn-accept" onclick="return confirm('هل أنت متأكد من الموافقة؟')">
                        <i class="fa-solid fa-check-circle"></i> موافقة واعتماد
                    </button>
                    <button type="submit" name="action" value="reject" class="btn-decision btn-reject" onclick="return confirm('هل أنت متأكد من الرفض؟')">
                        <i class="fa-solid fa-times-circle"></i> اعتذار / رفض
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div style="text-align:center; margin-top:40px; padding:20px; background:rgba(255,255,255,0.05); border-radius:10px; border:1px solid var(--border);">
                <div style="font-size:1.1rem; margin-bottom:10px;">حالة العرض الحالية:</div>
                <?php if($status_value == 'approved' && $access_mode === 'id'): ?>
                    <div style="color:#2ecc71; font-size:1.5rem; font-weight:bold;"><i class="fa-solid fa-check-circle"></i> تمت الموافقة من قبل العميل</div>
                    <?php if($linkedInvoiceId > 0): ?>
                        <div style="margin-top:14px;">
                            <a href="view_invoice.php?id=<?php echo (int)$linkedInvoiceId; ?>&token=<?php echo app_h($linkedInvoiceToken); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:12px 18px;border-radius:999px;background:rgba(212,175,55,0.12);border:1px solid rgba(212,175,55,0.4);color:var(--gold);text-decoration:none;font-weight:800;">
                                <i class="fa-solid fa-file-invoice"></i> فتح الفاتورة التشغيلية
                            </a>
                        </div>
                    <?php elseif($access_mode == 'id'): ?>
                        <form method="POST" action="" style="margin-top:14px;">
                            <?php echo app_csrf_input(); ?>
                            <button type="submit" name="action" value="convert_to_invoice" class="btn-decision btn-accept" style="margin:0 auto;">
                                <i class="fa-solid fa-file-circle-plus"></i> إنشاء فاتورة تشغيلية
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="color:#e74c3c; font-size:1.5rem; font-weight:bold;"><i class="fa-solid fa-times-circle"></i> تم رفض العرض</div>
                <?php endif; ?>
                
                <?php if(!empty($quote['client_comment'])): ?>
                    <div style="margin-top:15px; color:#aaa; font-style:italic;">"<?php echo app_h((string)$quote['client_comment']); ?>"</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($outputShowFooter): ?>
        <div class="footer">
            <div class="footer-meta">
                <div class="footer-lines">
                    <p style="font-weight:bold;"><?php echo app_h((string)($brandProfile['org_name'] ?? $appName)); ?></p>
                    <?php foreach ($footerLines as $line): ?>
                        <p><?php echo app_h($line); ?></p>
                    <?php endforeach; ?>
                    <?php if (trim((string)($brandProfile['org_footer_note'] ?? '')) !== ''): ?>
                        <p><?php echo app_h((string)$brandProfile['org_footer_note']); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($qrUrl !== ''): ?>
                <div class="footer-qr">
                    <img src="<?php echo app_h($qrUrl); ?>" alt="QR">
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<div class="actions-bar">
    <a href="<?php echo app_h($backHref); ?>" class="btn btn-back">
        <i class="fa-solid fa-arrow-right"></i> <?php echo app_h($backLabel); ?>
    </a>
    <?php if($access_mode == 'id'): ?>
    <a href="https://wa.me/<?php echo app_h($whatsapp_phone); ?>?text=<?php echo rawurlencode($wa_msg); ?>" target="_blank" class="btn btn-wa">
        <i class="fa-brands fa-whatsapp"></i> إرسال للعميل
    </a>
    <?php if($status_value === 'approved' && $linkedInvoiceId > 0): ?>
    <a href="view_invoice.php?id=<?php echo (int)$linkedInvoiceId; ?>&token=<?php echo app_h($linkedInvoiceToken); ?>" target="_blank" class="btn btn-back" style="background:#1f3a2a; color:#bdf3cf;">
        <i class="fa-solid fa-file-invoice"></i> الفاتورة
    </a>
    <?php endif; ?>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-print">
        <i class="fa-solid fa-print"></i> طباعة / PDF
    </button>
</div>

</body>
</html>
