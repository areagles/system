<?php
// print_purchase.php - طباعة فاتورة مشتريات (نسخة Arab Eagles الاحترافية)
require 'auth.php'; 
require 'config.php';

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$appLogo = app_brand_logo_path($conn, 'assets/img/Logo.png');

if(!isset($_GET['id'])) die("رقم الفاتورة غير صحيح");
$id = intval($_GET['id']);

// جلب البيانات
$sql = "SELECT p.*, COALESCE(NULLIF(p.supplier_display_name, ''), s.name) as sup_name, s.name as supplier_master_name, s.phone as sup_phone, s.address as sup_address 
        FROM purchase_invoices p 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.id = $id";
$res = $conn->query($sql);

if($res->num_rows == 0) die("الفاتورة غير موجودة");
$inv = $res->fetch_assoc();
$inv['purchase_number'] = $inv['purchase_number'] ?? '';
if ((string)$inv['purchase_number'] === '') {
    $inv['purchase_number'] = app_assign_document_number($conn, 'purchase_invoices', (int)$id, 'purchase_number', 'purchase', (string)($inv['inv_date'] ?? date('Y-m-d')));
}
$purchaseRef = trim((string)($inv['purchase_number'] ?? ''));
if ($purchaseRef === '') {
    $purchaseRef = '#' . str_pad((string)$inv['id'], 5, '0', STR_PAD_LEFT);
}
$items = json_decode($inv['items_json'] ?? '[]', true);
$isTaxPurchase = trim((string)($inv['eta_uuid'] ?? '')) !== '' || trim((string)($inv['eta_status'] ?? '')) !== '';
$hasCustomSupplierName = trim((string)($inv['supplier_display_name'] ?? '')) !== '' && trim((string)($inv['supplier_display_name'] ?? '')) !== trim((string)($inv['supplier_master_name'] ?? ''));
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>فاتورة مشتريات <?php echo app_h($purchaseRef); ?> | <?php echo app_h($appName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --dark: #111; }
        body { font-family: 'Cairo', sans-serif; background: #555; padding: 20px; color: #000; }
        
        .invoice-box { 
            max-width: 210mm; /* A4 Width */
            margin: auto; 
            background: #fff; 
            padding: 40px; 
            min-height: 297mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.5); 
            position: relative;
        }

        /* الترويسة */
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--gold); padding-bottom: 20px; margin-bottom: 30px; }
        .brand-logo-print { width: 20px; height: 20px; border-radius: 10px; object-fit: cover; margin-bottom: 10px; border: 1px solid #ddd; }
        .company-info h1 { margin: 0; color: var(--gold); text-transform: uppercase; font-size: 28px; font-weight: 800; letter-spacing: 1px; }
        .company-info p { margin: 5px 0 0; font-size: 14px; color: #555; }
        
        .invoice-title { text-align: left; }
        .invoice-title h2 { margin: 0; font-size: 32px; color: var(--dark); text-transform: uppercase; }
        .invoice-title .status { display: inline-block; background: #eee; padding: 5px 15px; border-radius: 5px; font-size: 14px; margin-top: 5px; font-weight: bold; }
        .doc-kind-badge { display:inline-flex; align-items:center; gap:8px; margin-top:10px; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; }
        .doc-kind-badge.non-tax { background:#e9fff0; color:#1f7a44; border:1px solid #9fd9b5; }
        .doc-kind-badge.tax { background:#fff0eb; color:#b33b1e; border:1px solid #f2b6a5; }

        /* معلومات الفاتورة والمورد */
        .info-section { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .info-box { width: 48%; }
        .info-box h3 { border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; color: var(--gold); font-size: 16px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .info-row span { font-weight: bold; }

        /* الجدول */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th { background: var(--dark); color: #fff; padding: 12px; border: 1px solid var(--dark); text-align: center; }
        td { padding: 10px; border: 1px solid #ddd; text-align: center; vertical-align: middle; }
        tr:nth-child(even) { background: #f9f9f9; }

        /* الإجماليات */
        .totals-section { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-box { width: 40%; background: #fcfcfc; border: 1px solid #eee; padding: 15px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .grand-total { border-top: 2px solid var(--gold); padding-top: 10px; font-size: 18px; font-weight: bold; color: var(--dark); }

        /* التذييل */
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 20px; }

        /* إعدادات الطباعة */
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-box { box-shadow: none; border: none; margin: 0; width: 100%; max-width: 100%; padding: 20px; }
            .no-print { display: none !important; }
            .header { border-bottom-color: #000; } /* توفير حبر ملون */
            th { background: #000 !important; color: #fff !important; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="position:fixed; top:20px; left:20px; z-index:999;">
        <button onclick="window.print()" style="padding:12px 25px; background:var(--gold); color:#000; border:none; cursor:pointer; font-weight:bold; border-radius:5px; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
            <i class="fa-solid fa-print"></i> طباعة الفاتورة
        </button>
        <a href="invoices.php?tab=purchases" style="padding:12px 25px; background:#333; color:#fff; text-decoration:none; margin-right:10px; border-radius:5px; display:inline-block;">
            رجوع
        </a>
    </div>

    <div class="invoice-box">
        <div class="header">
            <div class="company-info">
                <img src="<?php echo app_h($appLogo); ?>" alt="logo" class="brand-logo-print" onerror="this.style.display='none'">
                <h1><?php echo app_h($appName); ?></h1>
                <p>للحلول الصناعية والتجارية</p>
                <p>سجل تجاري: 123456 | بطاقة ضريبية: 987-654</p>
            </div>
            <div class="invoice-title">
                <h2>فاتورة مشتريات</h2>
                <div class="status"><?php echo app_h($purchaseRef); ?></div>
                <div class="doc-kind-badge <?php echo $isTaxPurchase ? 'tax' : 'non-tax'; ?>">
                    <i class="fa-solid <?php echo $isTaxPurchase ? 'fa-file-shield' : 'fa-file-circle-check'; ?>"></i>
                    <?php echo app_h($isTaxPurchase ? app_tr('فاتورة ضريبية / ETA', 'Tax / ETA invoice') : app_tr('فاتورة غير ضريبية', 'Non-tax invoice')); ?>
                </div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-box">
                <h3><i class="fa-solid fa-user-tie"></i> بيانات المورد</h3>
                <div class="info-row"><span>الاسم:</span> <?php echo $inv['sup_name']; ?></div>
                <?php if($hasCustomSupplierName): ?>
                <div class="info-row" style="color:#666;"><span>الاسم الأساسي:</span> <?php echo app_h($inv['supplier_master_name']); ?></div>
                <?php endif; ?>
                <div class="info-row"><span>الهاتف:</span> <?php echo $inv['sup_phone'] ?: '-'; ?></div>
                <div class="info-row"><span>العنوان:</span> <?php echo $inv['sup_address'] ?: '-'; ?></div>
            </div>
            <div class="info-box">
                <h3><i class="fa-solid fa-file-invoice"></i> تفاصيل الفاتورة</h3>
                <div class="info-row"><span>التاريخ:</span> <?php echo $inv['inv_date']; ?></div>
                <div class="info-row"><span>تاريخ الاستحقاق:</span> <?php echo $inv['due_date']; ?></div>
                <div class="info-row"><span>أنشئت بواسطة:</span> Admin</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th style="text-align:right;">البيان / الصنف</th>
                    <th width="10%">الكمية</th>
                    <th width="15%">سعر الوحدة</th>
                    <th width="20%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php if($items): foreach($items as $k => $item): ?>
                <tr>
                    <td><?php echo $k+1; ?></td>
                    <td style="text-align:right; font-weight:bold;"><?php echo $item['desc']; ?></td>
                    <td><?php echo $item['qty']; ?></td>
                    <td><?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5">لا توجد أصناف مسجلة</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-box">
                <div class="total-row"><span>المجموع الفرعي:</span> <span><?php echo number_format($inv['sub_total'], 2); ?></span></div>
                <div class="total-row"><span>(+) الضريبة:</span> <span><?php echo number_format($inv['tax'], 2); ?></span></div>
                <div class="total-row"><span>(-) الخصم:</span> <span><?php echo number_format($inv['discount'], 2); ?></span></div>
                <div class="total-row grand-total">
                    <span>الإجمالي النهائي:</span> 
                    <span><?php echo number_format($inv['total_amount'], 2); ?> EGP</span>
                </div>
                
                <div style="margin-top:10px; padding-top:10px; border-top:1px dashed #ccc; font-size:0.9rem;">
                    <div class="total-row" style="color:#2ecc71;"><span>المدفوع:</span> <span><?php echo number_format($inv['paid_amount'], 2); ?></span></div>
                    <div class="total-row" style="color:#e74c3c;"><span>المتبقي:</span> <span><?php echo number_format($inv['remaining_amount'], 2); ?></span></div>
                </div>
            </div>
        </div>

        <?php if($inv['notes']): ?>
        <div style="margin-top:30px; background:#f9f9f9; padding:15px; border-right:4px solid var(--gold);">
            <strong><i class="fa-solid fa-note-sticky"></i> ملاحظات:</strong><br>
            <?php echo nl2br($inv['notes']); ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>تم إصدار هذه الفاتورة إلكترونياً من نظام <?php echo app_h($appName); ?> للإدارة المالية.</p>
            <p><?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>

</body>
</html>
