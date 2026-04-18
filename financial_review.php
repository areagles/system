<?php
// financial_review.php - (Royal Financial Portal V2.0 - Interactive Invoices)
// بوابة تفاعلية للعملاء والموردين لمراجعة الحسابات، المصادقة، رفع الإيصالات، وعرض تفاصيل الفواتير
require 'config.php';
require_once __DIR__ . '/modules/finance/receipts_runtime.php';
app_start_session();

// 1. التحقق من الرابط والتوكن
if(!isset($_GET['token']) || empty($_GET['token'])) 
    die("<div style='background:#000;color:#d4af37;height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;'><h3>⛔ الرابط غير صالح.</h3></div>");

$token = trim((string)$_GET['token']);
$type = $_GET['type'] ?? 'client'; // client | supplier
if (!in_array($type, ['client', 'supplier', 'employee'], true)) {
    $type = 'client';
}

// تحديد الجدول المستهدف
$table = ($type == 'supplier') ? 'suppliers' : (($type == 'employee') ? 'users' : 'clients');
$col_id = ($type == 'supplier') ? 'supplier_id' : (($type == 'employee') ? 'employee_id' : 'client_id');
$canUploadReceipt = ($type !== 'employee');

// جلب بيانات الطرف
$stmt_entity = $conn->prepare("SELECT * FROM $table WHERE access_token = ? LIMIT 1");
$stmt_entity->bind_param("s", $token);
$stmt_entity->execute();
$res = $stmt_entity->get_result();

if($res->num_rows == 0) 
    die("<div style='background:#000;color:#d4af37;height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;'><h3>⛔ الرابط منتهي الصلاحية.</h3></div>");

$entity = $res->fetch_assoc();
$stmt_entity->close();
$entity_id = (int)$entity['id'];
$name = $entity['name'];
$safeRedirectToken = rawurlencode($token);
$safeRedirectType = rawurlencode($type);

// 2. معالجة الإجراءات (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!app_verify_csrf($_POST['_csrf_token'] ?? '')) {
        echo "<script>alert('انتهت صلاحية الجلسة. برجاء تحديث الصفحة.');</script>";
    } else {
    // أ. المصادقة على الرصيد
    if (isset($_POST['confirm_balance'])) {
        $now = date('Y-m-d H:i:s');
        $stmt_confirm = $conn->prepare("UPDATE $table SET last_balance_confirm = ? WHERE id = ?");
        $stmt_confirm->bind_param("si", $now, $entity_id);
        $stmt_confirm->execute();
        $stmt_confirm->close();
        echo "<script>alert('✅ تم تسجيل مصادقتك على الرصيد بنجاح.'); window.location.href='?token={$safeRedirectToken}&type={$safeRedirectType}';</script>";
    }

    // ب. رفع إيصال سداد/تحويل
    if ($canUploadReceipt && isset($_POST['upload_receipt']) && !empty($_FILES['receipt_file']['name'])) {
        $upload = app_store_uploaded_file($_FILES['receipt_file'], [
            'dir' => 'uploads/finance',
            'prefix' => 'pay_' . $type . '_' . $entity_id . '_',
            'max_size' => 10 * 1024 * 1024,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        ]);

        if ($upload['ok']) {
            $noteText = trim((string)($_POST['notes'] ?? ''));
            $noteText = mb_substr($noteText, 0, 500);
            $note = "📎 تم رفع إيصال سداد عبر البوابة المالية.\nالتاريخ: " . date('Y-m-d') . "\nالملف: " . $upload['path'] . "\nملاحظات: " . $noteText;
            $stmt_note = $conn->prepare("UPDATE $table SET notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?");
            $notePrefix = "\n" . $note;
            $stmt_note->bind_param("si", $notePrefix, $entity_id);
            $stmt_note->execute();
            $stmt_note->close();
            echo "<script>alert('✅ تم رفع الإيصال وإبلاغ الإدارة المالية.'); window.location.href='?token={$safeRedirectToken}&type={$safeRedirectType}';</script>";
        } else {
            echo "<script>alert('⛔ فشل رفع الملف: " . app_h($upload['error']) . "');</script>";
        }
    }
    }
}

// 3. حساب الرصيد الحي (Live Balance Calculation)
if ($type == 'client') {
    $snapshot = function_exists('financeClientBalanceSnapshot')
        ? financeClientBalanceSnapshot($conn, $entity_id)
        : ['net_balance' => 0.0];
    $balance = (float)($snapshot['net_balance'] ?? 0);
    $label_pos = "مستحق عليك";
    $label_neg = "رصيد دائن لك (فائض)";
} elseif ($type == 'supplier') {
    $snapshot = function_exists('financeSupplierBalanceSnapshot')
        ? financeSupplierBalanceSnapshot($conn, $entity_id)
        : ['net_balance' => 0.0];
    $balance = (float)($snapshot['net_balance'] ?? 0);
    $label_pos = "مستحق لك";
    $label_neg = "مدين علينا (دفعة مقدمة)";
} else {
    $out_total = $conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE employee_id=$entity_id AND type='out'")->fetch_row()[0];
    $in_total = $conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE employee_id=$entity_id AND type='in'")->fetch_row()[0];
    $balance = $out_total - $in_total;
    $label_pos = "صافي مستحقات/عهدة للموظف";
    $label_neg = "صافي مسدد من الموظف للشركة";
}

// 4. آخر الحركات للعرض (مع سحب تفاصيل الفاتورة items_json)
if($type == 'client'){
    $hist_sql = "
        SELECT id, inv_date as t_date, 'فاتورة' as type, total_amount as amount, items_json
        FROM invoices
        WHERE client_id=$entity_id
          AND COALESCE(status, '') <> 'cancelled'
        UNION ALL 
        SELECT id, trans_date as t_date, 'سداد' as type, amount, NULL as items_json FROM financial_receipts WHERE client_id=$entity_id AND type='in' 
        ORDER BY t_date DESC LIMIT 15
    ";
} elseif ($type == 'supplier') {
    $hist_sql = "
        SELECT id, inv_date as t_date, 'توريد' as type, total_amount as amount, items_json
        FROM purchase_invoices
        WHERE supplier_id=$entity_id AND COALESCE(status, '') <> 'cancelled'
        UNION ALL 
        SELECT id, trans_date as t_date, 'دفعة' as type, amount, NULL as items_json FROM financial_receipts WHERE supplier_id=$entity_id AND type='out' 
        ORDER BY t_date DESC LIMIT 15
    ";
} else {
    $hist_sql = "
        SELECT id, STR_TO_DATE(CONCAT(month_year, '-01'), '%Y-%m-%d') as t_date, 'مسير راتب' as type, net_salary as amount, NULL as items_json
        FROM payroll_sheets
        WHERE employee_id = $entity_id
        UNION ALL
        SELECT id, trans_date as t_date,
               CASE WHEN type='out' THEN 'صرف' ELSE 'توريد' END as type,
               amount, NULL as items_json
        FROM financial_receipts
        WHERE employee_id = $entity_id
        ORDER BY t_date DESC LIMIT 15
    ";
}
$history = $conn->query($hist_sql);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>الملف المالي | <?php echo htmlspecialchars($name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; }
        body { background: var(--bg); color: var(--text); font-family: 'Cairo', sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; }
        
        .header { text-align: center; margin-bottom: 25px; border-bottom: 1px solid #333; padding-bottom: 15px; }
        .brand { color: var(--gold); font-size: 1.5rem; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 10px; }
        
        .balance-card {
            background: linear-gradient(135deg, #181818 0%, #222 100%);
            padding: 30px; border-radius: 15px; text-align: center;
            border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 20px; position: relative; overflow: hidden;
        }
        .balance-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; background: <?php echo $balance > 0 ? '#e74c3c' : '#2ecc71'; ?>; }
        .balance-amount { font-size: 2.5rem; font-weight: bold; color: <?php echo $balance > 0 ? '#e74c3c' : '#2ecc71'; ?>; direction: ltr; margin: 10px 0; }
        .balance-label { font-size: 0.9rem; color: #aaa; }
        
        .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: bold; font-family: 'Cairo'; cursor: pointer; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
        .btn-confirm { background: var(--gold); color: #000; }
        .btn-confirm:hover { background: #b8860b; }
        .btn-upload { background: #2a2a2a; color: #fff; border: 1px solid #444; }
        .btn-upload:hover { background: #333; border-color: var(--gold); }
        
        .history-box { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid #333; margin-top: 25px; }
        .h-item { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 12px; border-bottom: 1px solid #2a2a2a; border-radius: 8px; transition: 0.2s; 
        }
        .h-item:last-child { border: none; }
        .h-item.clickable:hover { background: rgba(212, 175, 55, 0.05); transform: translateX(-5px); border-color: var(--gold); }
        .h-icon { width: 40px; height: 40px; border-radius: 10px; background: #111; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .h-icon.inv { color: var(--gold); }
        .h-icon.pay { color: #2ecc71; }
        
        /* Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 999; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-content { background: #1a1a1a; padding: 25px; border-radius: 15px; width: 95%; max-width: 450px; border: 1px solid #444; position: relative; max-height: 90vh; overflow-y: auto; }
        .close-btn { position: absolute; top: 15px; left: 15px; background: none; border: none; color: #888; font-size: 1.2rem; cursor: pointer; }
        .close-btn:hover { color: #e74c3c; }
        
        input, textarea { width: 100%; background: #0a0a0a; border: 1px solid #333; color: #fff; padding: 12px; border-radius: 8px; margin: 10px 0; font-family: 'Cairo'; box-sizing: border-box; }
        input:focus, textarea:focus { border-color: var(--gold); outline: none; }

        /* Invoice Table inside Modal */
        .inv-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.9rem; }
        .inv-table th { background: #222; color: var(--gold); padding: 10px; text-align: right; border-bottom: 1px solid #444; }
        .inv-table td { padding: 10px; border-bottom: 1px solid #2a2a2a; color: #ddd; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="brand"><i class="fa-solid fa-eagle"></i> ARAB EAGLES</div>
        <div style="font-size:0.9rem; color:#888;">بوابة المطابقة والخدمات المالية</div>
    </div>

    <div class="balance-card">
        <div class="balance-label">الرصيد المحاسبي المجمّع</div>
        <div class="balance-amount"><?php echo number_format(abs($balance), 2); ?></div>
        <div style="color: <?php echo $balance > 0 ? '#e74c3c' : '#2ecc71'; ?>; font-size:0.95rem; font-weight:bold;">
            <?php echo $balance > 0 ? $label_pos : $label_neg; ?>
        </div>

        <?php if(!empty($entity['last_balance_confirm'])): ?>
            <div style="margin-top:15px; font-size:0.85rem; color:#2ecc71; background:rgba(46, 204, 113, 0.1); padding:8px; border-radius:8px; display:inline-block;">
                <i class="fa-solid fa-check-double"></i> تمت المصادقة آخر مرة: <?php echo date('Y/m/d', strtotime($entity['last_balance_confirm'])); ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: <?php echo $canUploadReceipt ? '1fr 1fr' : '1fr'; ?>; gap: 15px;">
        <button onclick="document.getElementById('confirmModal').style.display='flex'" class="btn btn-confirm">
            <i class="fa-solid fa-handshake"></i> مصادقة الرصيد
        </button>
        <?php if($canUploadReceipt): ?>
            <button onclick="document.getElementById('uploadModal').style.display='flex'" class="btn btn-upload">
                <i class="fa-solid fa-cloud-arrow-up"></i> رفع إيصال سداد
            </button>
        <?php endif; ?>
    </div>

    <div class="history-box">
        <h4 style="margin:0 0 15px 0; color:var(--gold); display:flex; justify-content:space-between; align-items:center;">
            <span><i class="fa-solid fa-clock-rotate-left"></i> كشف الحساب السريع</span>
            <small style="color:#666; font-size:0.8rem;">آخر 15 حركة</small>
        </h4>
        
        <?php if($history && $history->num_rows > 0): ?>
            <?php while($h = $history->fetch_assoc()): 
                $is_inv = in_array($h['type'], ['فاتورة', 'توريد']);
                $icon_class = $is_inv ? 'fa-file-invoice inv' : 'fa-money-bill-transfer pay';
                $cursor_class = $is_inv ? 'clickable' : '';
                $json_data = $is_inv ? htmlspecialchars($h['items_json'], ENT_QUOTES, 'UTF-8') : '';
                $onClick = $is_inv ? "onclick='showInvoiceDetails({$h['id']}, \"{$h['t_date']}\", {$h['amount']}, this.getAttribute(\"data-items\"))'" : "";
                $receiptSummary = null;
                $displayAmount = (float)$h['amount'];
                if (!$is_inv && function_exists('financeReceiptAllocationSummary')) {
                    $receiptRes = $conn->query("SELECT * FROM financial_receipts WHERE id = " . (int)$h['id'] . " LIMIT 1");
                    $receiptRow = $receiptRes ? $receiptRes->fetch_assoc() : null;
                    if ($receiptRow) {
                        $receiptSummary = financeReceiptAllocationSummary($conn, $receiptRow);
                        if (is_array($receiptSummary)) {
                            $allocatedAmount = (float)($receiptSummary['allocated_amount'] ?? 0);
                            $unallocatedAmount = (float)($receiptSummary['unallocated_amount'] ?? 0);
                            $displayAmount = $allocatedAmount > 0.00001 ? $allocatedAmount : $unallocatedAmount;
                            if ($displayAmount <= 0.00001) {
                                $displayAmount = (float)$h['amount'];
                            }
                        }
                    }
                }
            ?>
            <div class="h-item <?php echo $cursor_class; ?>" <?php echo $onClick; ?> data-items="<?php echo $json_data; ?>" <?php echo $is_inv ? 'title="اضغط لعرض التفاصيل"' : ''; ?>>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="h-icon <?php echo $is_inv ? 'inv' : 'pay'; ?>">
                        <i class="fa-solid <?php echo $is_inv ? 'fa-file-invoice' : 'fa-receipt'; ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight:bold; font-size:0.95rem;">
                            <?php echo $h['type']; ?> #<?php echo $h['id']; ?>
                        </div>
                        <div style="font-size:0.8rem; color:#888;"><?php echo date('Y-m-d', strtotime($h['t_date'])); ?></div>
                        <?php if(!$is_inv && is_array($receiptSummary)): ?>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                <?php if ((int)($receiptSummary['count'] ?? 0) > 0): ?>
                                    <span style="font-size:0.72rem; color:#9fd9ff; background:rgba(52,152,219,0.12); border:1px solid rgba(52,152,219,0.22); padding:2px 8px; border-radius:999px;">
                                        <?php echo app_h(app_tr('تسويات', 'Allocations')); ?> x<?php echo (int)$receiptSummary['count']; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ((float)($receiptSummary['unallocated_amount'] ?? 0) > 0.00001): ?>
                                    <span style="font-size:0.72rem; color:#ffe69b; background:rgba(212,175,55,0.12); border:1px solid rgba(212,175,55,0.22); padding:2px 8px; border-radius:999px;">
                                        <?php echo app_h(app_tr('رصيد غير مخصص', 'Unallocated')); ?>: <?php echo number_format((float)$receiptSummary['unallocated_amount'], 2); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="font-family:sans-serif; font-weight:bold; font-size:1.1rem; color: <?php echo $is_inv ? '#e74c3c' : '#2ecc71'; ?>;">
                        <?php echo number_format($displayAmount, 2); ?>
                    </div>
                    <?php if($is_inv): ?>
                        <i class="fa-solid fa-chevron-left" style="color:#555; font-size:0.8rem;"></i>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:20px; color:#666;">لا توجد حركات مالية مسجلة.</div>
        <?php endif; ?>
    </div>
</div>

<div id="confirmModal" class="modal">
    <div class="modal-content" style="text-align:center;">
        <button class="close-btn" onclick="this.closest('.modal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        <div style="font-size:3rem; color:var(--gold); margin-bottom:10px;"><i class="fa-solid fa-handshake-angle"></i></div>
        <h3 style="color:var(--gold); margin-top:0;">إقرار مصادقة</h3>
        <p style="color:#ccc; font-size:0.9rem; line-height:1.6;">بموجب هذا الإجراء، أقر بصحة الرصيد المالي الموضح في البوابة وقدره <strong>(<?php echo number_format(abs($balance), 2); ?>)</strong> حتى تاريخ اليوم.</p>
        <form method="POST" style="margin-top:20px;">
            <?php echo app_csrf_input(); ?>
            <button type="submit" name="confirm_balance" class="btn btn-confirm">نعم، أصادق وأوافق</button>
            <button type="button" onclick="this.closest('.modal').style.display='none'" class="btn btn-upload">تراجع</button>
        </form>
    </div>
</div>

<?php if($canUploadReceipt): ?>
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="this.closest('.modal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
            <h3 style="color:var(--gold); margin-top:0;"><i class="fa-solid fa-cloud-arrow-up"></i> إرسال إشعار سداد</h3>
            <form method="POST" enctype="multipart/form-data">
                <?php echo app_csrf_input(); ?>
                <label style="font-size:0.9rem; color:#aaa;">صورة الإيصال / الحوالة:</label>
                <input type="file" name="receipt_file" accept="image/*,.pdf" required>
                <label style="font-size:0.9rem; color:#aaa;">ملاحظات إضافية:</label>
                <textarea name="notes" rows="3" placeholder="اكتب رقم الحوالة، اسم البنك، أو أي تفاصيل..."></textarea>
                <button type="submit" name="upload_receipt" class="btn btn-confirm" style="margin-top:15px;">إرسال المرفق</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div id="invoiceModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="this.closest('.modal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        <h3 style="color:var(--gold); margin-top:0; border-bottom:1px solid #333; padding-bottom:10px;">
            <i class="fa-solid fa-file-invoice"></i> تفاصيل الفاتورة #<span id="inv_modal_id"></span>
        </h3>
        
        <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:#aaa; margin-bottom:15px;">
            <span>التاريخ: <span id="inv_modal_date" style="color:#fff;"></span></span>
            <span>الإجمالي: <strong id="inv_modal_amount" style="color:var(--gold); font-size:1rem;"></strong> ج.م</span>
        </div>

        <table class="inv-table">
            <thead>
                <tr>
                    <th>الصنف / البيان</th>
                    <th style="text-align:center;">الكمية</th>
                    <th style="text-align:center;">السعر</th>
                    <th style="text-align:center;">الإجمالي</th>
                </tr>
            </thead>
            <tbody id="inv_modal_items">
                </tbody>
        </table>
    </div>
</div>

<script>
// إغلاق النوافذ المنبثقة عند الضغط خارجها
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = "none";
    }
}

// دالة عرض تفاصيل الفاتورة (محدثة لقراءة كافة مفاتيح JSON المحتملة)
function showInvoiceDetails(id, date, amount, itemsJson) {
    document.getElementById('inv_modal_id').innerText = id;
    document.getElementById('inv_modal_date').innerText = date.split(' ')[0];
    document.getElementById('inv_modal_amount').innerText = parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    let tbody = document.getElementById('inv_modal_items');
    tbody.innerHTML = '';
    
    if(itemsJson && itemsJson.trim() !== '') {
        try {
            let items = JSON.parse(itemsJson);
            
            // معالجة إضافية في حال كان الـ JSON محولاً إلى نص مرتين
            if(typeof items === 'string') {
                items = JSON.parse(items);
            }

            if(items.length > 0) {
                items.forEach(item => {
                    // توسيع دائرة البحث عن مفتاح "اسم الصنف" ليطابق هيكل قاعدة بياناتك
                    let itemName = item.name || item.item_name || item.item || item.description || item.desc || item.product_name || item.product || item.title || 'صنف غير محدد';
                    
                    let qty = item.qty || item.quantity || item.count || 1;
                    let price = item.price || item.unit_price || item.cost || 0;
                    let total = item.total || (qty * price);
                    
                    let tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${itemName}</td>
                        <td style="text-align:center; font-family:sans-serif;">${qty}</td>
                        <td style="text-align:center; font-family:sans-serif;">${parseFloat(price).toLocaleString()}</td>
                        <td style="text-align:center; font-family:sans-serif; color:var(--gold); font-weight:bold;">${parseFloat(total).toLocaleString()}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#888;">تفاصيل الأصناف غير متوفرة لهذه الفاتورة.</td></tr>';
            }
        } catch(e) {
            console.error("JSON Parse Error:", e);
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#e74c3c;">حدث خطأ أثناء قراءة بيانات الفاتورة.</td></tr>';
        }
    } else {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#888;">لم يتم تسجيل أصناف تفصيلية في هذه الفاتورة.</td></tr>';
    }
    
    document.getElementById('invoiceModal').style.display = 'flex';
}
</script>

</body>
</html>
