<?php
// print_finance_voucher.php - طباعة سند قبض / صرف
ob_start();

require 'auth.php';
require 'config.php';
require_once 'finance_engine.php';
app_handle_lang_switch($conn);
financeEnsureAllocationSchema($conn);

$canFinanceVoucher = app_user_can_any([
    'finance.view',
    'finance.transactions.view',
    'finance.transactions.create',
    'finance.transactions.update',
    'finance.transactions.delete',
]);

if (!$canFinanceVoucher) {
    http_response_code(403);
    die('<div style="padding:30px;font-family:Cairo,Arial,sans-serif">غير مصرح لك بطباعة السندات المالية.</div>');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('<div style="padding:30px;font-family:Cairo,Arial,sans-serif">رقم السند غير صالح.</div>');
}

$stmt = $conn->prepare("
    SELECT
        t.*,
        c.name AS client_name,
        c.phone AS client_phone,
        s.name AS supplier_name,
        s.phone AS supplier_phone,
        u.full_name AS employee_name,
        ps.month_year AS payroll_month
    FROM financial_receipts t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN suppliers s ON t.supplier_id = s.id
    LEFT JOIN users u ON t.employee_id = u.id
    LEFT JOIN payroll_sheets ps ON t.payroll_id = ps.id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$voucher = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$voucher) {
    http_response_code(404);
    die('<div style="padding:30px;font-family:Cairo,Arial,sans-serif">السند غير موجود.</div>');
}

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$appLogo = app_brand_logo_path($conn, 'assets/img/Logo.png');
$outputTheme = app_brand_output_theme($conn);
$brandProfile = app_brand_profile($conn);
$outputShowHeader = !empty($brandProfile['show_header']);
$outputShowFooter = !empty($brandProfile['show_footer']);
$outputShowLogo = !empty($brandProfile['show_logo']);
$outputShowQr = !empty($brandProfile['show_qr']);
$headerLines = app_brand_output_lines($brandProfile, 'header', true);
$footerLines = app_brand_output_lines($brandProfile, 'footer', true);

$type = strtolower(trim((string)($voucher['type'] ?? 'in')));
$category = strtolower(trim((string)($voucher['category'] ?? 'general')));
$isReceipt = ($type === 'in');
$voucherTitle = $isReceipt ? 'سند قبض' : 'سند صرف';
$voucherTitleEn = $isReceipt ? 'Receipt Voucher' : 'Payment Voucher';
$docType = $isReceipt ? 'receipt' : 'payment';

$voucherNumber = trim((string)($voucher['voucher_number'] ?? ''));
if ($voucherNumber === '') {
    $voucherNumber = app_assign_document_number(
        $conn,
        'financial_receipts',
        $id,
        'voucher_number',
        $docType,
        (string)($voucher['trans_date'] ?? date('Y-m-d'))
    );
}
if ($voucherNumber === '') {
    $voucherNumber = '#' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

$categoryMap = [
    'general' => 'عام',
    'supplier' => 'موردين',
    'salary' => 'رواتب',
    'loan' => 'سلف',
    'tax' => 'ضرائب',
];
$categoryText = $categoryMap[$category] ?? 'عام';
$taxLawKey = trim((string)($voucher['tax_law_key'] ?? ''));
$taxLawLabel = $taxLawKey !== '' && function_exists('app_tax_find_law')
    ? (string)((app_tax_find_law($conn, $taxLawKey)['name'] ?? $taxLawKey))
    : $taxLawKey;

$counterpartyLabel = $isReceipt ? 'العميل / جهة القبض' : 'الجهة المستفيدة';
$counterpartyValue = '-';
$counterpartyPhone = '';

if ($isReceipt && trim((string)($voucher['client_name'] ?? '')) !== '') {
    $counterpartyLabel = 'العميل';
    $counterpartyValue = (string)$voucher['client_name'];
    $counterpartyPhone = (string)($voucher['client_phone'] ?? '');
} elseif (!$isReceipt && trim((string)($voucher['supplier_name'] ?? '')) !== '') {
    $counterpartyLabel = 'المورد';
    $counterpartyValue = (string)$voucher['supplier_name'];
    $counterpartyPhone = (string)($voucher['supplier_phone'] ?? '');
} elseif (!$isReceipt && trim((string)($voucher['employee_name'] ?? '')) !== '') {
    $counterpartyLabel = 'الموظف';
    $counterpartyValue = (string)$voucher['employee_name'];
}

$reference = '-';
if (!empty($voucher['invoice_id'])) {
    $invoiceId = (int)$voucher['invoice_id'];
    $reference = $isReceipt ? "فاتورة مبيعات #{$invoiceId}" : "فاتورة مشتريات #{$invoiceId}";
}
if (!empty($voucher['payroll_id'])) {
    $payrollId = (int)$voucher['payroll_id'];
    $payrollMonth = trim((string)($voucher['payroll_month'] ?? ''));
    $reference = $payrollMonth !== ''
        ? "كشف راتب #{$payrollId} ({$payrollMonth})"
        : "كشف راتب #{$payrollId}";
}

$amount = (float)($voucher['amount'] ?? 0);
$amountText = number_format($amount, 2);
$createdBy = trim((string)($voucher['created_by'] ?? ''));
if ($createdBy === '') {
    $createdBy = 'System';
}
$transDate = trim((string)($voucher['trans_date'] ?? date('Y-m-d')));
$notes = trim((string)($voucher['description'] ?? ''));
$allocationSummary = financeReceiptAllocationSummary($conn, $voucher);
$balanceSummary = financeCounterpartyBalanceSummary($conn, $voucher);
$referenceValue = $reference;
if (($allocationSummary['count'] ?? 0) === 1 && !empty($allocationSummary['lines'][0]['label'])) {
    $referenceValue = (string)$allocationSummary['lines'][0]['label'];
}
if (($allocationSummary['count'] ?? 0) > 1) {
    $referenceValue = app_tr('توزيع داخلي على عدة فواتير', 'Internal allocation across multiple invoices');
}
$qrPayload = app_brand_qr_payload($brandProfile, [
    'Document' => $voucherTitleEn,
    'Reference' => $voucherNumber,
    'Date' => $transDate,
    'Amount' => number_format($amount, 2, '.', ''),
    'Category' => $categoryText,
]);
$qrUrl = ($outputShowQr && $qrPayload !== '') ? app_brand_qr_url($qrPayload, 140) : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo app_h($voucherTitle . ' ' . $voucherNumber); ?> | <?php echo app_h($appName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: <?php echo app_h($outputTheme['accent']); ?>;
            --gold-soft: <?php echo app_h($outputTheme['accent_soft']); ?>;
            --ink: <?php echo app_h($outputTheme['ink']); ?>;
            --paper: <?php echo app_h($outputTheme['paper']); ?>;
            --muted: <?php echo app_h($outputTheme['muted']); ?>;
            --line: <?php echo app_h($outputTheme['line']); ?>;
            --bg: <?php echo app_h($outputTheme['bg']); ?>;
            --card: <?php echo app_h($outputTheme['card']); ?>;
            --tint: <?php echo app_h($outputTheme['tint']); ?>;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: #fff;
        }
        .sheet {
            max-width: 920px;
            margin: 0 auto 90px auto;
            background: var(--paper);
            color: var(--ink);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.10);
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(0,0,0,0.45);
        }
        .sheet-top {
            height: 6px;
            background: linear-gradient(90deg, var(--gold), #b7870f, var(--gold));
        }
        .sheet-body {
            padding: 34px 36px;
        }
        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            border-bottom: 2px solid var(--line);
            padding-bottom: 18px;
            margin-bottom: 20px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .brand img {
            width: 19px;
            height: 19px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid var(--line);
        }
        .logo-fallback {
            width: 19px;
            height: 19px;
            border-radius: 10px;
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            font-weight: 900;
            color: var(--ink);
            background: var(--tint);
            overflow: hidden;
        }
        .brand h1 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--ink);
            font-weight: 900;
            line-height: 1.1;
        }
        .brand p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 0.86rem;
        }
        .head-info {
            margin: -6px 0 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--tint);
            padding: 10px 12px;
            color: var(--ink);
            font-size: 0.82rem;
            line-height: 1.65;
        }
        .head-info div { margin-bottom: 3px; }
        .head-info div:last-child { margin-bottom: 0; }
        .voucher-meta {
            text-align: left;
        }
        .voucher-meta .title {
            margin: 0;
            color: var(--gold);
            font-size: 1.75rem;
            font-weight: 900;
            line-height: 1.1;
        }
        .voucher-meta .title-en {
            margin: 4px 0 8px;
            color: var(--muted);
            font-size: 0.86rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .voucher-meta .no {
            margin: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-weight: 800;
            font-size: 1.02rem;
            color: #111;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(200px, 1fr));
            gap: 10px 16px;
            margin-bottom: 16px;
        }
        .cell {
            background: #fafafa;
            border: 1px solid #ebebeb;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .cell .label {
            font-size: 0.78rem;
            color: #777;
            margin-bottom: 3px;
        }
        .cell .value {
            font-weight: 800;
            color: #121212;
            font-size: 1rem;
            word-break: break-word;
        }
        .amount-box {
            border: 2px dashed #d1b062;
            background: #fffdf5;
            border-radius: 12px;
            padding: 12px 14px;
            margin: 16px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .amount-box .a-label {
            color: #7b640f;
            font-weight: 700;
        }
        .amount-box .a-value {
            font-size: 1.7rem;
            font-weight: 900;
            color: #101010;
            line-height: 1;
        }
        .notes {
            margin-top: 10px;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px;
            min-height: 95px;
            white-space: pre-wrap;
            line-height: 1.8;
            color: #111;
            background: #fff;
        }
        .allocation-list {
            margin-top: 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fcfcfc;
            padding: 10px 12px;
        }
        .allocation-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 7px 0;
            border-bottom: 1px dashed #e2e2e2;
        }
        .allocation-row:last-child { border-bottom: none; }
        .allocation-row .amount { font-weight: 800; color: #111; }
        .foot {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px dashed #d5d5d5;
        }
        .foot .sign {
            text-align: center;
            min-width: 220px;
        }
        .foot .sign .line {
            margin-top: 34px;
            border-top: 1px solid #777;
            padding-top: 6px;
            color: #555;
            font-size: 0.85rem;
        }
        .foot .stamp {
            color: #777;
            font-size: 0.82rem;
        }
        .foot .stamp .org-line {
            display: block;
            margin-top: 4px;
        }
        .foot .stamp .org-name {
            font-weight: 800;
            color: #444;
        }
        .foot .stamp .qr-box {
            margin-top: 10px;
            text-align: right;
        }
        .foot .stamp .qr-box img {
            width: 90px;
            height: 90px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fff;
            padding: 4px;
        }
        .action-bar {
            position: fixed;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            width: min(620px, calc(100vw - 22px));
            background: rgba(18,18,18,0.95);
            border: 1px solid rgba(212,175,55,0.45);
            border-radius: 999px;
            padding: 8px;
            display: flex;
            gap: 8px;
            z-index: 999;
            backdrop-filter: blur(8px);
        }
        .ab-btn {
            flex: 1;
            border: none;
            border-radius: 999px;
            padding: 11px 14px;
            font-family: 'Cairo', sans-serif;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }
        .ab-btn.print { background: var(--gold); color: #111; }
        .ab-btn.back { background: #2a2a2a; color: #fff; }

        @media (max-width: 760px) {
            .sheet-body { padding: 22px 16px; }
            .head { flex-direction: column; align-items: flex-start; }
            .voucher-meta { text-align: right; width: 100%; }
            .grid { grid-template-columns: 1fr; }
            .amount-box { flex-direction: column; align-items: flex-start; }
            .foot { flex-direction: column; align-items: stretch; }
            .foot .sign { min-width: 0; }
            .foot .stamp .qr-box { text-align: center; }
        }

        @media print {
            @page { size: A4; margin: 10mm; }
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                color: #000 !important;
            }
            .sheet {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
            .action-bar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="sheet-top"></div>
        <div class="sheet-body">
            <div class="head">
                <div class="brand">
                    <?php if ($outputShowLogo): ?>
                        <img src="<?php echo app_h($appLogo); ?>" alt="logo" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="logo-fallback"><?php echo app_h(mb_substr((string)($brandProfile['org_name'] ?? $appName), 0, 2)); ?></div>
                    <?php endif; ?>
                    <div>
                        <h1><?php echo app_h((string)($brandProfile['org_name'] ?? $appName)); ?></h1>
                        <p>Financial Document</p>
                    </div>
                </div>
                <div class="voucher-meta">
                    <p class="title"><?php echo app_h($voucherTitle); ?></p>
                    <p class="title-en"><?php echo app_h($voucherTitleEn); ?></p>
                    <p class="no"><?php echo app_h($voucherNumber); ?></p>
                </div>
            </div>
            <?php if ($outputShowHeader && !empty($headerLines)): ?>
                <div class="head-info">
                    <?php foreach ($headerLines as $line): ?>
                        <div><?php echo app_h($line); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="grid">
                <div class="cell">
                    <div class="label">تاريخ السند</div>
                    <div class="value"><?php echo app_h($transDate); ?></div>
                </div>
                <div class="cell">
                    <div class="label">التصنيف</div>
                    <div class="value"><?php echo app_h($categoryText); ?></div>
                </div>
                <div class="cell">
                    <div class="label">نوع الضريبة</div>
                    <div class="value"><?php echo app_h($taxLawLabel !== '' ? $taxLawLabel : '-'); ?></div>
                </div>
                <div class="cell">
                    <div class="label"><?php echo app_h($counterpartyLabel); ?></div>
                    <div class="value"><?php echo app_h($counterpartyValue); ?></div>
                </div>
                <div class="cell">
                    <div class="label">الهاتف / وسيلة الاتصال</div>
                    <div class="value"><?php echo app_h($counterpartyPhone !== '' ? $counterpartyPhone : '-'); ?></div>
                </div>
                <div class="cell">
                    <div class="label">مرجع الحركة</div>
                    <div class="value"><?php echo app_h($referenceValue); ?></div>
                </div>
                <div class="cell">
                    <div class="label">تم التسجيل بواسطة</div>
                    <div class="value"><?php echo app_h($createdBy); ?></div>
                </div>
                <div class="cell">
                    <div class="label">حالة الرصيد بعد السند</div>
                    <div class="value"><?php echo app_h((string)($balanceSummary['label'] ?? '-')); ?><?php echo isset($balanceSummary['amount']) ? ' - ' . number_format((float)$balanceSummary['amount'], 2) . ' ج.م' : ''; ?></div>
                </div>
                <div class="cell">
                    <div class="label">الموزع داخلياً</div>
                    <div class="value"><?php echo number_format((float)($allocationSummary['allocated_amount'] ?? 0), 2); ?> ج.م<?php if (($allocationSummary['unallocated_amount'] ?? 0) > 0.00001): ?> | <?php echo app_h(app_tr('متبق غير موزع', 'Unallocated remainder')); ?>: <?php echo number_format((float)$allocationSummary['unallocated_amount'], 2); ?><?php endif; ?></div>
                </div>
            </div>

            <div class="amount-box">
                <div class="a-label">قيمة السند</div>
                <div class="a-value"><?php echo app_h($amountText); ?> ج.م</div>
            </div>

            <?php if (($allocationSummary['count'] ?? 0) > 0): ?>
                <div class="allocation-list">
                    <div class="label" style="margin-bottom:6px;">تفصيل التوزيع الداخلي</div>
                    <?php foreach (($allocationSummary['lines'] ?? []) as $line): ?>
                        <div class="allocation-row">
                            <div><?php echo app_h((string)($line['label'] ?? '')); ?></div>
                            <div class="amount"><?php echo number_format((float)($line['amount'] ?? 0), 2); ?> ج.م</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="cell" style="background:#fff;">
                <div class="label">البيان / الملاحظات</div>
                <div class="notes"><?php echo app_h($notes !== '' ? $notes : '-'); ?></div>
            </div>

            <div class="foot">
                <div class="stamp">
                    رقم الحركة الداخلي: #<?php echo (int)$voucher['id']; ?><br>
                    وقت الطباعة: <?php echo app_h(date('Y-m-d H:i:s')); ?>
                    <?php if ($outputShowFooter): ?>
                        <span class="org-line org-name"><?php echo app_h((string)($brandProfile['org_name'] ?? $appName)); ?></span>
                        <?php foreach ($footerLines as $line): ?>
                            <span class="org-line"><?php echo app_h($line); ?></span>
                        <?php endforeach; ?>
                        <?php if (trim((string)($brandProfile['org_footer_note'] ?? '')) !== ''): ?>
                            <span class="org-line"><?php echo app_h((string)$brandProfile['org_footer_note']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($qrUrl !== ''): ?>
                        <div class="qr-box">
                            <img src="<?php echo app_h($qrUrl); ?>" alt="QR">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sign">
                    <div class="line">توقيع المستلم / المعتمد</div>
                </div>
            </div>
        </div>
    </div>

    <div class="action-bar">
        <button class="ab-btn print" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
        <a class="ab-btn back" href="finance.php"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
