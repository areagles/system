<?php
// print_invoice.php - (Royal Print Engine V5.0 - PDF Bulletproof)

ob_start();
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php';

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$appLogo = app_brand_logo_path($conn, 'assets/img/Logo.png');
$brandProfile = app_brand_profile($conn);
$outputTheme = app_brand_output_theme($conn);
$outputShowHeader = !empty($brandProfile['show_header']);
$outputShowFooter = !empty($brandProfile['show_footer']);
$outputShowLogo = !empty($brandProfile['show_logo']);
$outputShowQr = !empty($brandProfile['show_qr']);
$headerLines = app_brand_output_lines($brandProfile, 'header', true);
$footerLines = app_brand_output_lines($brandProfile, 'footer', true);
$legacyFooterLine1 = trim(app_setting_get($conn, 'brand_footer_line1', $appName));
$legacyFooterLine2 = trim(app_setting_get($conn, 'brand_footer_line2', ''));
$legacyFooterLine3 = trim(app_setting_get($conn, 'brand_footer_line3', (string)parse_url(SYSTEM_URL, PHP_URL_HOST)));
if (empty($footerLines)) {
    $footerLines = array_values(array_filter([$legacyFooterLine1, $legacyFooterLine2, $legacyFooterLine3], static function ($line): bool {
        return trim((string)$line) !== '';
    }));
}

if(!isset($_GET['id'])) die("<div style='text-align:center; padding:50px; background:#050505; color:white; height:100vh;'><h2><i class='fa-solid fa-triangle-exclamation'></i> رقم الفاتورة مفقود!</h2></div>");

$id = intval($_GET['id']);
$stmtInv = $conn->prepare("SELECT i.*, c.name as client_name, c.phone as client_phone, c.address as client_address FROM invoices i JOIN clients c ON i.client_id=c.id WHERE i.id = ?");
$stmtInv->bind_param('i', $id);
$stmtInv->execute();
$res = $stmtInv->get_result();

if(!$res || $res->num_rows == 0) die("<div style='text-align:center; padding:50px; font-family:sans-serif; background:#050505; color:#d4af37; height:100vh;'><h2><i class='fa-solid fa-file-circle-xmark'></i> الفاتورة غير موجودة.</h2><br><a href='invoices.php' style='color:#fff; text-decoration:none; border:1px solid #d4af37; padding:10px 20px; border-radius:5px;'>العودة للسجل</a></div>");

$inv = $res->fetch_assoc();
$stmtInv->close();

$itemsRaw = $inv['items_json'];
$items = [];

if($itemsRaw) {
    $decoded = json_decode($itemsRaw, true);
    if(is_string($decoded)) $decoded = json_decode($decoded, true);
    if(is_array($decoded)) $items = $decoded;
}
$invoiceTaxLines = app_tax_decode_lines((string)($inv['taxes_json'] ?? '[]'));

$sub_total = isset($inv['sub_total']) ? floatval($inv['sub_total']) : 0;
$tax = isset($inv['tax']) ? floatval($inv['tax']) : 0;
$discount = isset($inv['discount']) ? floatval($inv['discount']) : 0;
$total = floatval($inv['total_amount']);
$paid = floatval($inv['paid_amount']);
$remaining = isset($inv['remaining_amount']) ? floatval($inv['remaining_amount']) : ($total - $paid);
if ($remaining < 0) {
    $remaining = 0;
}

if ($sub_total == 0 && $total > 0) {
    $sub_total = $total - $tax + $discount;
}

$statusRaw = strtolower(trim((string)($inv['status'] ?? 'unpaid')));
$stamp_text = "غير مدفوع";
$stamp_class = "st-rejected";
if ($statusRaw === 'paid' || $remaining <= 0.00001) {
    $stamp_text = "خالص الدفع";
    $stamp_class = "st-approved";
} elseif (in_array($statusRaw, ['partially_paid', 'partial'], true) || $paid > 0) {
    $stamp_text = "دفعة جزئية";
    $stamp_class = "st-pending";
}

$token = app_public_token('invoice_view', $id);
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
if ($basePath === '.' || $basePath === '/') { $basePath = ''; }
$public_link = app_base_url() . $basePath . "/view_invoice.php?id=$id&type=sales&token=$token";
$inv['invoice_number'] = $inv['invoice_number'] ?? '';
if ((string)$inv['invoice_number'] === '') {
    $inv['invoice_number'] = app_assign_document_number($conn, 'invoices', (int)$id, 'invoice_number', 'invoice', (string)($inv['inv_date'] ?? date('Y-m-d')));
}
$invoiceRef = trim((string)($inv['invoice_number'] ?? ''));
if ($invoiceRef === '') {
    $invoiceRef = '#' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}
$wa_msg = "السيد/ة {$inv['client_name']}\nرابط الفاتورة الصادرة من {$appName}:\n$public_link";
$qrPayload = app_brand_qr_payload($brandProfile, [
    'Document' => 'Sales Invoice',
    'Reference' => $invoiceRef,
    'Date' => date('Y-m-d', strtotime((string)($inv['inv_date'] ?? date('Y-m-d')))),
    'Total' => number_format($total, 2, '.', ''),
    'Public Link' => $public_link,
]);
$qrUrl = ($outputShowQr && $qrPayload !== '') ? app_brand_qr_url($qrPayload, 140) : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>فاتورة <?php echo app_h($invoiceRef); ?> | <?php echo app_h($appName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --bg-body: <?php echo app_h($outputTheme['bg']); ?>; --card-bg: <?php echo app_h($outputTheme['card']); ?>; --gold: <?php echo app_h($outputTheme['accent']); ?>; --gold-soft: <?php echo app_h($outputTheme['accent_soft']); ?>; --paper: <?php echo app_h($outputTheme['paper']); ?>; --ink: <?php echo app_h($outputTheme['ink']); ?>; --text-main: #ffffff; --text-sub: #aaaaaa; --border: rgba(255,255,255,0.12); --line: <?php echo app_h($outputTheme['line']); ?>; --tint: <?php echo app_h($outputTheme['tint']); ?>; }
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Cairo', sans-serif; margin: 0; padding: 15px; }
        
        .container { max-width: 860px; margin: 20px auto 100px auto; background: var(--card-bg); border-radius: 22px; box-shadow: 0 24px 60px rgba(0,0,0,0.45); position: relative; border: 1px solid var(--border); overflow: hidden; }
        .container::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--gold), #b8860b, var(--gold)); }
        .invoice-box { padding: 42px; position: relative; z-index: 1; }

        .status-stamp { position: absolute; top: 200px; left: 50%; transform: translateX(-50%) rotate(-15deg); padding: 10px 40px; border: 4px double; font-weight: 900; text-transform: uppercase; opacity: 0.1; font-size: 4rem; letter-spacing: 5px; z-index: 0; pointer-events: none; white-space: nowrap; }
        .st-pending { color: #f1c40f; border-color: #f1c40f; opacity: 0.15; }
        .st-approved { color: #2ecc71; border-color: #2ecc71; opacity: 0.15; }
        .st-rejected { color: #e74c3c; border-color: #e74c3c; opacity: 0.15; }

        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.10); padding-bottom: 20px; margin-bottom: 24px; position: relative; z-index: 2; }
        .header-side { flex: 1; text-align: center; }
        .header-side.right { text-align: right; }
        .header-side.left { text-align: left; }

        .invoice-title { font-size: 1.7rem; font-weight: 900; color: var(--gold-soft); margin: 0; line-height: 1.2; letter-spacing: 0.5px; }
        .invoice-id { font-size: 1.1rem; color: #fff; letter-spacing: 2px; margin-top: 5px; opacity: 0.8; font-family: monospace; }
        .brand-name-fallback { font-size: 1.1rem; font-weight: 800; color: #fff; text-align: center; }
        .logo-img { width: 75px; max-width: 100%; display: block; margin: 0 auto; filter: drop-shadow(0 0 10px rgba(212,175,55,0.2)); }
        .date-item { font-size: 0.85rem; color: var(--text-sub); margin-bottom: 4px; }
        .date-item strong { color: #fff; display: inline-block; width: 70px; }
        .header-info-list { margin-bottom: 20px; padding: 12px 16px; border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; background: rgba(255,255,255,0.03); font-size: 0.82rem; color: #d7d7d7; line-height: 1.7; }
        .header-info-list div { margin-bottom: 4px; }
        .header-info-list div:last-child { margin-bottom: 0; }

        .client-section { margin-bottom: 30px; background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)); padding: 22px; border-radius: 16px; border-right: 4px solid var(--gold); position: relative; z-index: 2; }
        .section-label { font-size: 0.8rem; color: var(--gold-soft); text-transform: uppercase; margin-bottom: 5px; font-weight: bold; }
        .client-name { font-size: 1.4rem; font-weight: 800; margin: 0; color: #fff; }
        .client-details { font-size: 0.9rem; color: var(--text-sub); margin: 8px 0 0 0; display: flex; gap: 15px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; position: relative; z-index: 2; }
        .items-table th { background: var(--tint); color: var(--gold); padding: 13px 12px; text-align: center; font-size: 0.9rem; border-bottom: 1px solid rgba(255,255,255,0.09); font-weight: 800; }
        .items-table td { padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; font-size: 0.98rem; color: #eee; vertical-align: middle; }
        .items-table td.desc { text-align: right; width: 50%; color: #fff; font-weight: 600; }
        .items-table tr:hover { background: rgba(255,255,255,0.01); }

        /* منطقة الإجماليات بصيغة table للحفاظ على توافق PDF */
        .totals-wrapper { width: 100%; display: table; position: relative; z-index: 2; margin-bottom: 30px; }
        .totals-table { width: 50%; float: left; border-collapse: collapse; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; overflow: hidden; }
        .totals-table td { padding: 11px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); font-size: 0.95rem; }
        .totals-table td:first-child { color: #888; text-align: right; font-weight: bold; }
        .totals-table td:last-child { color: #fff; text-align: left; font-family: sans-serif; font-weight: bold; }
        .totals-table tr.grand { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); }
        .totals-table tr.grand td { color: #111; font-size: 1.3rem; font-weight: 900; }
        .clear-fix { clear: both; }

        .terms-box { border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 14px; font-size: 0.85rem; color: var(--text-sub); line-height: 1.8; page-break-inside: avoid; background: rgba(0,0,0,0.18); }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.09); font-size: 0.8rem; color: var(--text-sub); line-height: 1.8; position: relative; z-index: 2; }
        .footer-meta { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .footer-lines { flex: 1; min-width: 240px; text-align: right; }
        .footer-lines p { margin: 0 0 4px; }
        .footer-lines p:last-child { margin-bottom: 0; }
        .qr-box { min-width: 104px; text-align: center; }
        .qr-box img { width: 94px; height: 94px; display: inline-block; background: #fff; border-radius: 10px; border: 1px solid #2f2f2f; padding: 4px; }
        .qr-box small { display: block; margin-top: 4px; color: #8d8d8d; }
        
        .actions-bar { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(10px); padding: 10px 30px; border-radius: 50px; width: 90%; max-width: 600px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); display: flex; gap: 10px; justify-content: center; border: 1px solid var(--gold); z-index: 1000; }
        .btn { border: none; padding: 12px 20px; border-radius: 30px; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.95rem; font-family: 'Cairo'; color: #fff; transition: 0.3s; flex: 1; white-space: nowrap; }
        .btn-print { background: var(--gold); color: #000; }
        .btn-wa { background: #2ecc71; color: #fff; }
        .btn-back { background: #333; color: #fff; }

        /* =========================================
           🖨️ وضع الطباعة (Solid PDF Mode)
           ========================================= */
        @media print {
            @page { size: A4; margin: 10mm; }
            body { background: var(--paper); color: var(--ink); padding: 0; margin: 0; }
            .container { box-shadow: none; border: none; margin: 0; max-width: 100%; width: 100%; background: var(--paper); color: var(--ink); border-radius: 0; }
            .container::before { display: none; }
            .invoice-box { padding: 0; }
            
            .header { border-bottom: 2px solid var(--line); }
            .invoice-title, .client-name, .section-label, .date-item strong, .invoice-id, .date-item, .brand-name-fallback { color: var(--ink) !important; text-shadow: none; }
            .logo-img { width: 54px; filter: none; }
            .header-info-list { border-color: var(--line); color: var(--ink); background: var(--paper); }
            .client-section { background: var(--paper); border: 1px solid var(--line); padding: 15px; }
            
            .items-table { border-color: var(--line); }
            .items-table th { background: var(--tint) !important; color: var(--ink) !important; border-bottom: 2px solid var(--line); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .items-table td { color: var(--ink) !important; border-bottom: 1px solid var(--line); }
            .items-table td.desc { color: var(--ink) !important; }
            
            .totals-table { border-color: var(--line); width: 50%; float: left; background: var(--paper); }
            .totals-table td { color: var(--ink) !important; border-bottom-color: var(--line); }
            .totals-table td:first-child { color: var(--muted) !important; }
            .totals-table tr.grand { background: var(--tint) !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .totals-table tr.grand td { color: var(--ink) !important; border-top: 2px solid var(--line); }
            
            .terms-box { border: 1px solid var(--line); background: var(--paper); color: var(--ink); }
            .footer { color: var(--ink); border-top: 1px solid var(--line); }
            .qr-box img { border-color: var(--line); }
            .qr-box small { color: var(--muted); }
            .actions-bar { display: none !important; }
            .status-stamp { opacity: 0.1 !important; color: var(--ink) !important; border-color: var(--ink) !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-box">
        
        <div class="status-stamp <?php echo $stamp_class; ?>"><?php echo $stamp_text; ?></div>

        <div class="header">
            <div class="header-side right">
                <h1 class="invoice-title">فاتورة مبيعات</h1>
                <div class="invoice-id"><?php echo app_h($invoiceRef); ?></div>
            </div>
            <div class="header-side center">
                <?php if ($outputShowLogo): ?>
                    <img src="<?php echo app_h($appLogo); ?>" alt="Logo" class="logo-img" onerror="this.style.display='none'">
                <?php else: ?>
                    <div class="brand-name-fallback"><?php echo app_h((string)($brandProfile['org_name'] ?? $appName)); ?></div>
                <?php endif; ?>
            </div>
            <div class="header-side left">
                <div class="date-item"><strong>الإصدار:</strong> <?php echo date('Y-m-d', strtotime($inv['inv_date'])); ?></div>
                <div class="date-item"><strong>الحالة:</strong> <?php echo $stamp_text; ?></div>
            </div>
        </div>
        <?php if ($outputShowHeader && !empty($headerLines)): ?>
            <div class="header-info-list">
                <?php foreach ($headerLines as $line): ?>
                    <div><?php echo app_h($line); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="client-section">
            <div class="section-label">فاتورة إلى العميل:</div>
            <h2 class="client-name"><?php echo htmlspecialchars($inv['client_name']); ?></h2>
            <div class="client-details">
                <?php if(!empty($inv['client_phone'])): ?><span><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($inv['client_phone']); ?></span><?php endif; ?>
                <?php if(!empty($inv['client_address'])): ?><span><i class='fa-solid fa-location-dot'></i> <?php echo htmlspecialchars($inv['client_address']); ?></span><?php endif; ?>
            </div>
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
                if(!empty($items)): 
                    $count = 1;
                    foreach($items as $item): 
                        // 🔥 PHP Smart Data Extractor
                        $itemName = 'صنف غير محدد';
                        foreach($item as $key => $val) {
                            $kl = strtolower($key);
                            if(!in_array($kl, ['qty','quantity','count','price','cost','unit_price','total','subtotal','tax','id','unit']) && is_string($val) && !is_numeric($val) && trim($val) !== '') {
                                $itemName = $val; break;
                            }
                        }

                        $qty = isset($item['qty']) ? floatval($item['qty']) : (isset($item['quantity']) ? floatval($item['quantity']) : 1);
                        $unitVal = trim((string)($item['unit'] ?? ''));
                        $qtyLabel = $qty;
                        if ($unitVal !== '') {
                            $qtyLabel .= ' ' . htmlspecialchars($unitVal);
                        }
                        $price = isset($item['price']) ? floatval($item['price']) : (isset($item['unit_price']) ? floatval($item['unit_price']) : 0);
                        $itemTotal = isset($item['total']) ? floatval($item['total']) : ($qty * $price);
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td class="desc"><?php echo nl2br(htmlspecialchars($itemName)); ?></td>
                    <td style="font-family: sans-serif;"><?php echo $qtyLabel; ?></td>
                    <td style="font-family: sans-serif;"><?php echo number_format($price, 2); ?></td>
                    <td style="font-family: sans-serif; font-weight: bold; color: var(--gold);"><?php echo number_format($itemTotal, 2); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:30px; color:#888;">لم يتم إدراج أصناف تفصيلية في هذه الفاتورة.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-wrapper">
            <table class="totals-table">
                <?php if($sub_total > 0): ?>
                <tr>
                    <td>المجموع الفرعي:</td>
                    <td><?php echo number_format($sub_total, 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if($discount > 0): ?>
                <tr>
                    <td>قيمة الخصم:</td>
                    <td style="color: #e74c3c;">- <?php echo number_format($discount, 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php foreach ($invoiceTaxLines as $taxLine): ?>
                <?php
                    $taxName = (string)($taxLine['name'] ?? 'ضريبة');
                    $taxAmount = (float)($taxLine['amount'] ?? 0);
                    $taxMode = (string)($taxLine['mode'] ?? 'add');
                    $taxColor = ($taxMode === 'subtract') ? '#e74c3c' : 'var(--gold)';
                    $taxPrefix = ($taxMode === 'subtract') ? '- ' : '';
                ?>
                <tr>
                    <td><?php echo app_h($taxName); ?>:</td>
                    <td style="color: <?php echo $taxColor; ?>;"><?php echo $taxPrefix . number_format($taxAmount, 2); ?></td>
                </tr>
                <?php endforeach; ?>

                <tr>
                    <td>المبلغ المدفوع:</td>
                    <td style="color: #2ecc71;"><?php echo number_format($paid, 2); ?></td>
                </tr>
                <tr>
                    <td>المتبقي للدفع:</td>
                    <td style="color: #e74c3c;"><?php echo number_format($remaining, 2); ?></td>
                </tr>
                
                <tr class="grand">
                    <td>الإجمالي النهائي:</td>
                    <td><?php echo number_format($total, 2); ?> <small style="font-size:0.8rem; font-weight:normal;">EGP</small></td>
                </tr>
            </table>
            <div class="clear-fix"></div>
        </div>

        <?php if(!empty(trim($inv['notes']))): ?>
        <div class="terms-box">
            <strong><i class="fa-solid fa-file-pen"></i> ملاحظات الفاتورة:</strong>
            <?php echo nl2br(htmlspecialchars($inv['notes'])); ?>
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
                    <div class="qr-box">
                        <img src="<?php echo app_h($qrUrl); ?>" alt="QR">
                        <small>QR</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="actions-bar">
    <a href="invoices.php?tab=sales" class="btn btn-back"><i class="fa-solid fa-arrow-right"></i> السجل</a>
    <a href="https://wa.me/20<?php echo ltrim($inv['client_phone'], '0'); ?>?text=<?php echo urlencode($wa_msg); ?>" target="_blank" class="btn btn-wa"><i class="fa-brands fa-whatsapp"></i> إرسال</a>
    <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> طباعة / PDF</button>
</div>

</body>
</html>
