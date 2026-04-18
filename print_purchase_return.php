<?php
require 'auth.php';
require 'config.php';

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$appLogo = app_brand_logo_path($conn, 'assets/img/Logo.png');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('رقم مردود الشراء غير صحيح');
}

$stmt = $conn->prepare("
    SELECT r.*, s.name AS supplier_name, s.phone AS supplier_phone, s.address AS supplier_address,
           w.name AS warehouse_name, p.purchase_number
    FROM purchase_invoice_returns r
    JOIN suppliers s ON s.id = r.supplier_id
    LEFT JOIN warehouses w ON w.id = r.warehouse_id
    LEFT JOIN purchase_invoices p ON p.id = r.purchase_invoice_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die('مردود الشراء غير موجود');
}

$items = json_decode((string)($row['items_json'] ?? '[]'), true);
if (!is_array($items)) {
    $items = [];
}
$ref = trim((string)($row['return_number'] ?? ''));
if ($ref === '') {
    $ref = 'PRTN-' . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT);
}
$purchaseRef = trim((string)($row['purchase_number'] ?? ''));
if ($purchaseRef === '') {
    $purchaseRef = '#' . (int)$row['purchase_invoice_id'];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>مردود شراء <?php echo app_h($ref); ?> | <?php echo app_h($appName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --gold:#d4af37; --dark:#111; }
        body { font-family:'Cairo',sans-serif; background:#555; padding:20px; color:#000; }
        .doc { max-width:210mm; min-height:297mm; margin:auto; background:#fff; padding:34px; box-shadow:0 0 20px rgba(0,0,0,.35); }
        .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid var(--gold); padding-bottom:18px; margin-bottom:24px; }
        .logo { width:30px; height:30px; border-radius:10px; object-fit:cover; border:1px solid #ddd; }
        .brand h1 { margin:0; color:var(--gold); font-size:28px; font-weight:800; }
        .brand p { margin:6px 0 0; color:#666; font-size:14px; }
        .title { text-align:left; }
        .title h2 { margin:0; font-size:30px; color:#111; }
        .title .ref { margin-top:6px; font-weight:700; color:#444; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
        .info-box h3 { margin:0 0 10px; color:var(--gold); font-size:16px; border-bottom:1px solid #ddd; padding-bottom:6px; }
        .info-box div { margin-bottom:6px; font-size:14px; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:#111; color:#fff; padding:12px; border:1px solid #111; }
        td { padding:10px; border:1px solid #ddd; text-align:center; }
        .totals { width:42%; margin-right:auto; margin-top:20px; background:#fafafa; border:1px solid #ddd; padding:14px; }
        .totals .row { display:flex; justify-content:space-between; margin-bottom:8px; }
        .totals .grand { border-top:2px solid var(--gold); padding-top:10px; font-weight:800; font-size:18px; }
        .footer { margin-top:36px; border-top:1px solid #eee; padding-top:16px; text-align:center; color:#666; font-size:12px; }
        .no-print { position:fixed; top:20px; left:20px; z-index:999; display:flex; gap:10px; }
        .no-print a, .no-print button { padding:12px 18px; border-radius:8px; text-decoration:none; border:none; cursor:pointer; font-weight:700; }
        .no-print button { background:var(--gold); color:#111; }
        .no-print a { background:#222; color:#fff; }
        @media print {
            body { background:#fff; padding:0; }
            .doc { box-shadow:none; padding:20px; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">طباعة / PDF</button>
    <a href="purchase_returns.php">رجوع</a>
</div>
<div class="doc">
    <div class="header">
        <div class="brand">
            <img src="<?php echo app_h($appLogo); ?>" alt="logo" class="logo" onerror="this.style.display='none'">
            <h1><?php echo app_h($appName); ?></h1>
            <p>مستند مردود شراء</p>
        </div>
        <div class="title">
            <h2>مردود شراء</h2>
            <div class="ref"><?php echo app_h($ref); ?></div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <h3>بيانات المورد</h3>
            <div><strong>الاسم:</strong> <?php echo app_h((string)$row['supplier_name']); ?></div>
            <div><strong>الهاتف:</strong> <?php echo app_h((string)($row['supplier_phone'] ?? '-')); ?></div>
            <div><strong>العنوان:</strong> <?php echo app_h((string)($row['supplier_address'] ?? '-')); ?></div>
        </div>
        <div class="info-box">
            <h3>بيانات المستند</h3>
            <div><strong>فاتورة الشراء:</strong> <?php echo app_h($purchaseRef); ?></div>
            <div><strong>تاريخ المردود:</strong> <?php echo app_h((string)$row['return_date']); ?></div>
            <div><strong>المخزن:</strong> <?php echo app_h((string)($row['warehouse_name'] ?? '-')); ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align:right;">الصنف</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($items): foreach ($items as $idx => $item): ?>
                <tr>
                    <td><?php echo $idx + 1; ?></td>
                    <td style="text-align:right;"><?php echo app_h((string)($item['desc'] ?? '')); ?></td>
                    <td><?php echo app_h((string)($item['qty'] ?? 0)); ?></td>
                    <td><?php echo number_format((float)($item['price'] ?? 0), 2); ?></td>
                    <td><?php echo number_format((float)($item['total'] ?? 0), 2); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5">لا توجد أصناف.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>المجموع الفرعي</span><span><?php echo number_format((float)$row['subtotal_amount'], 2); ?></span></div>
        <div class="row"><span>الضريبة</span><span><?php echo number_format((float)$row['tax_amount'], 2); ?></span></div>
        <div class="row"><span>الخصم</span><span><?php echo number_format((float)$row['discount_amount'], 2); ?></span></div>
        <div class="row grand"><span>الإجمالي</span><span><?php echo number_format((float)$row['total_amount'], 2); ?></span></div>
    </div>

    <?php if (trim((string)($row['notes'] ?? '')) !== ''): ?>
        <div style="margin-top:24px; background:#fafafa; padding:14px; border-right:4px solid var(--gold);">
            <strong>ملاحظات:</strong><br>
            <?php echo nl2br(app_h((string)$row['notes'])); ?>
        </div>
    <?php endif; ?>

    <div class="footer">
        تم إنشاء هذا المستند إلكترونياً من نظام <?php echo app_h($appName); ?>.
    </div>
</div>
</body>
</html>
