<?php
// statement_employee.php - (Fixed & Royal UI Upgraded)
ob_start();
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 

$financeRoles = ['admin', 'manager', 'accountant'];
if (!app_user_has_any_role($financeRoles)) {
    http_response_code(403);
    require 'header.php';
    echo "<div class='container' style='padding:50px; text-align:center; color:#ffb3b3;'>⛔ غير مصرح لك بالوصول إلى كشف حساب الموظفين.</div>";
    require 'footer.php';
    exit;
}

require 'header.php';

// إصلاح استلام الـ ID (يدعم employee_id أو user_id لتجنب أي تعارض في الروابط)
$emp_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);

if($emp_id == 0){
    die("<div class='container' style='padding:50px; color:#e74c3c; text-align:center; font-size:1.5rem;'><i class='fa-solid fa-triangle-exclamation'></i> لم يتم تحديد الموظف بشكل صحيح.</div>");
}

$date_from = $_GET['from'] ?? date('Y-m-01'); 
$date_to   = $_GET['to']   ?? date('Y-m-d');   
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date_from)) {
    $date_from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date_to)) {
    $date_to = date('Y-m-d');
}

// جلب بيانات الموظف
$stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param('i', $emp_id);
$stmtUser->execute();
$user_data = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if(!$user_data) {
    $fallbackName = '';
    $fallbackStmt = $conn->prepare("SELECT employee_name_snapshot FROM payroll_sheets WHERE employee_id = ? AND TRIM(COALESCE(employee_name_snapshot, '')) <> '' ORDER BY id DESC LIMIT 1");
    $fallbackStmt->bind_param('i', $emp_id);
    $fallbackStmt->execute();
    $fallbackRow = $fallbackStmt->get_result()->fetch_assoc();
    $fallbackStmt->close();
    $fallbackName = trim((string)($fallbackRow['employee_name_snapshot'] ?? ''));
    if ($fallbackName === '') {
        $fallbackName = '#' . $emp_id;
    }
    $user_data = ['id' => $emp_id, 'full_name' => $fallbackName];
}
$brandProfile = app_brand_profile($conn);
$outputShowHeader = !empty($brandProfile['show_header']);
$outputShowFooter = !empty($brandProfile['show_footer']);
$outputShowQr = !empty($brandProfile['show_qr']);
$headerLines = app_brand_output_lines($brandProfile, 'header', true);
$footerLines = app_brand_output_lines($brandProfile, 'footer', true);

// --- جلب الحركات بشكل آمن ---
$stmtTrans = $conn->prepare("
    SELECT trans_date as t_date, id as ref_id, amount, description, type, category
    FROM financial_receipts
    WHERE employee_id = ? AND trans_date BETWEEN ? AND ?
    ORDER BY trans_date ASC
");
$stmtTrans->bind_param('iss', $emp_id, $date_from, $date_to);
$stmtTrans->execute();
$transactions = $stmtTrans->get_result();
$stmtTrans->close();

// حساب الإجماليات مسبقاً لعرضها في المؤشرات
$total_in = 0; // ما ورده/أرجعه الموظف للشركة
$total_out = 0; // ما استلمه الموظف (رواتب/سلف/عهد)
$rows_data = [];
if($transactions->num_rows > 0) {
    while($row = $transactions->fetch_assoc()) {
        if($row['type'] == 'in') $total_in += $row['amount']; 
        else $total_out += $row['amount'];
        $rows_data[] = $row;
    }
}
$net_balance = $total_out - $total_in; // الصافي (عهدة في ذمته أو مستحقات له)
$qrPayload = app_brand_qr_payload($brandProfile, [
    'Report' => 'Employee Statement',
    'Employee' => (string)($user_data['full_name'] ?? ''),
    'From' => $date_from,
    'To' => $date_to,
]);
$qrUrl = ($outputShowQr && $qrPayload !== '') ? app_brand_qr_url($qrPayload, 140) : '';
?>

<style>
    /* ==================================================
       Royal UI - Screen Styles
       ================================================== */
    :root { 
        --gold: #d4af37; 
        --gold-glow: rgba(212, 175, 55, 0.15);
        --bg-dark: #0a0a0a; 
        --surface: #141414;
        --border-color: #2a2a2a;
    }
    
    body { background-color: var(--bg-dark); color: #fff; font-family: 'Cairo', sans-serif; margin: 0; }
    .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    
    /* Control Panel (No Print) */
    .control-panel { 
        background: var(--surface); padding: 20px; border-radius: 15px; 
        border: 1px solid var(--border-color); margin-bottom: 25px;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    }
    .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
    .filter-form input[type="date"] { 
        background: var(--bg-dark); border: 1px solid var(--border-color); color: #fff; 
        padding: 10px 15px; border-radius: 8px; font-family: 'Cairo'; outline: none;
    }
    .filter-form input[type="date"]:focus { border-color: var(--gold); }
    
    .btn { 
        padding: 10px 20px; border-radius: 8px; font-weight: bold; border: none; 
        cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; 
    }
    .btn-gold { background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; box-shadow: 0 4px 15px var(--gold-glow); }
    .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4); }
    .btn-outline { background: transparent; border: 1px solid #555; color: #ccc; }
    .btn-outline:hover { background: #222; color: #fff; border-color: #888; }

    /* KPIs */
    .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .kpi-box { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); text-align: center; }
    .kpi-box h4 { margin: 0 0 10px 0; color: #888; font-size: 0.9rem; }
    .kpi-box .num { font-size: 1.8rem; font-weight: bold; }

    /* Table */
    .report-card { background: var(--surface); border-radius: 15px; border: 1px solid var(--border-color); overflow: hidden; }
    .report-header { text-align: center; padding: 25px; border-bottom: 1px dashed var(--border-color); }
    .report-header h2 { color: var(--gold); margin: 0 0 5px 0; }
    .report-header-extra { margin: 12px auto 0; max-width: 92%; border: 1px dashed #3a3a3a; border-radius: 10px; padding: 10px 12px; text-align: right; font-size: 0.85rem; color: #c9c9c9; line-height: 1.7; }
    .report-header-extra div { margin-bottom: 3px; }
    .report-header-extra div:last-child { margin-bottom: 0; }
    .report-footer { margin: 18px 20px 0; border-top: 1px dashed #2f2f2f; padding-top: 12px; display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
    .report-footer-lines { flex: 1; min-width: 240px; font-size: 0.84rem; color: #cfcfcf; line-height: 1.7; text-align: right; }
    .report-footer-lines div { margin-bottom: 3px; }
    .report-footer-lines div:last-child { margin-bottom: 0; }
    .report-footer-qr img { width: 92px; height: 92px; border: 1px solid #3a3a3a; border-radius: 8px; padding: 4px; background: #fff; }
    
    table { width: 100%; border-collapse: collapse; }
    th { background: rgba(255,255,255,0.03); color: #aaa; padding: 15px; text-align: center; font-size: 0.9rem; border-bottom: 1px solid var(--border-color); }
    td { padding: 15px; text-align: center; border-bottom: 1px solid #1a1a1a; color: #eee; }
    tbody tr:hover { background: rgba(255,255,255,0.02); }
    
    .amount-in { color: #e74c3c; font-weight: bold; } /* من وجهة نظر الموظف: خصم/سداد منه */
    .amount-out { color: #2ecc71; font-weight: bold; } /* من وجهة نظر الموظف: استلام/قبض */
    
    tfoot { background: rgba(212, 175, 55, 0.05); }
    tfoot td { font-weight: bold; color: var(--gold); font-size: 1.1rem; border-top: 2px solid var(--gold); }

    .cat-badge { font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; background: #222; color: #aaa; }

    /* ==================================================
       Print Styles (White Paper Mode)
       ================================================== */
    @media print {
        body { background: #fff !important; color: #000 !important; }
        .no-print { display: none !important; }
        .report-card { border: none !important; box-shadow: none !important; }
        .report-header { border-bottom: 2px solid #000 !important; }
        .report-header h2 { color: #000 !important; }
        .report-header-extra { border-color: #aaa !important; color: #000 !important; }
        .report-footer-lines { color: #000 !important; }
        th { background: #f0f0f0 !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
        td { border-bottom: 1px solid #ccc !important; color: #000 !important; }
        .amount-in, .amount-out { color: #000 !important; }
        tfoot { background: #f9f9f9 !important; }
        tfoot td { color: #000 !important; border-top: 2px solid #000 !important; }
        .kpi-row { display: flex; gap: 10px; }
        .kpi-box { border: 1px solid #000 !important; background: #fff !important; flex: 1; padding: 10px;}
        .kpi-box h4 { color: #333 !important; }
        .kpi-box .num { color: #000 !important; }
    }
</style>

<div class="container">
    
    <div class="control-panel no-print">
        <form method="GET" class="filter-form">
            <input type="hidden" name="employee_id" value="<?php echo $emp_id; ?>">
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="color:#888;"><i class="fa-regular fa-calendar"></i> من:</label>
                <input type="date" name="from" value="<?php echo $date_from; ?>">
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="color:#888;"><i class="fa-regular fa-calendar"></i> إلى:</label>
                <input type="date" name="to" value="<?php echo $date_to; ?>">
            </div>
            <button type="submit" class="btn btn-gold"><i class="fa-solid fa-filter"></i> تصفية التقرير</button>
        </form>
        
        <div>
            <button onclick="window.print()" class="btn btn-outline"><i class="fa-solid fa-print"></i> طباعة الكشف</button>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi-box" style="border-bottom: 3px solid #2ecc71;">
            <h4>إجمالي ما استلمه الموظف (صرف للعهد/الرواتب)</h4>
            <div class="num" style="color:#2ecc71;"><?php echo number_format($total_out, 2); ?></div>
        </div>
        <div class="kpi-box" style="border-bottom: 3px solid #e74c3c;">
            <h4>إجمالي ما ورّده الموظف (تسوية عهد/سداد)</h4>
            <div class="num" style="color:#e74c3c;"><?php echo number_format($total_in, 2); ?></div>
        </div>
        <div class="kpi-box" style="border-bottom: 3px solid var(--gold);">
            <h4>الرصيد الصافي (في ذمته)</h4>
            <div class="num" style="color:var(--gold);"><?php echo number_format($net_balance, 2); ?></div>
        </div>
    </div>

    <div class="report-card printable-area">
        <div class="report-header">
            <div style="font-size: 2.5rem; color: var(--gold); margin-bottom: 10px;"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <h2>كشف حساب تفصيلي (موظف / عهدة)</h2>
            <h3 style="color:#eee; margin: 5px 0;">الاسم: <?php echo htmlspecialchars($user_data['full_name']); ?></h3>
            <p style="color:#888; font-size:0.9rem; margin:0;">الفترة من <?php echo date('Y/m/d', strtotime($date_from)); ?> إلى <?php echo date('Y/m/d', strtotime($date_to)); ?></p>
            <?php if ($outputShowHeader && !empty($headerLines)): ?>
            <div class="report-header-extra">
                <?php foreach ($headerLines as $line): ?>
                    <div><?php echo app_h($line); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">التاريخ</th>
                        <th style="text-align: right; width: 35%;">البيان / الوصف</th>
                        <th>استلام نقدية (+)<br><small style="font-weight:normal;">(راتب، سلفة، عهدة)</small></th>
                        <th>تسوية / توريد (-)<br><small style="font-weight:normal;">(إخلاء عهدة، سداد)</small></th>
                        <th>التصنيف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($rows_data)): ?>
                        <?php foreach($rows_data as $row): 
                            // الترجمة للتصنيفات
                            $cat_map = ['general'=>'عام/عهدة', 'salary'=>'راتب', 'loan'=>'سلفة'];
                            $cat_name = $cat_map[$row['category']] ?? 'أخرى';
                        ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($row['t_date'])); ?><br><small style="color:#666;">#<?php echo $row['ref_id']; ?></small></td>
                            <td style="text-align:right;"><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="amount-out"><?php echo $row['type'] == 'out' ? number_format($row['amount'], 2) : '-'; ?></td>
                            <td class="amount-in"><?php echo $row['type'] == 'in' ? number_format($row['amount'], 2) : '-'; ?></td>
                            <td><span class="cat-badge"><?php echo $cat_name; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="padding: 40px; color: #666; font-size: 1.1rem;"><i class="fa-solid fa-folder-open" style="font-size: 2rem; display:block; margin-bottom: 10px; opacity:0.5;"></i> لا توجد حركات مالية مسجلة لهذا الموظف خلال الفترة المحددة.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align: right;">إجمالي الحركات المجمعة:</td>
                        <td><?php echo number_format($total_out, 2); ?></td>
                        <td><?php echo number_format($total_in, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php if ($outputShowFooter): ?>
        <div class="report-footer">
            <div class="report-footer-lines">
                <div style="font-weight:bold;"><?php echo app_h((string)($brandProfile['org_name'] ?? '')); ?></div>
                <?php foreach ($footerLines as $line): ?>
                    <div><?php echo app_h($line); ?></div>
                <?php endforeach; ?>
                <?php if (trim((string)($brandProfile['org_footer_note'] ?? '')) !== ''): ?>
                    <div><?php echo app_h((string)$brandProfile['org_footer_note']); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($qrUrl !== ''): ?>
            <div class="report-footer-qr">
                <img src="<?php echo app_h($qrUrl); ?>" alt="QR">
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="no-print" style="display: none; margin-top: 50px; text-align: center;">
            </div>
        <style>
            @media print {
                .signatures { display: flex !important; justify-content: space-around; margin-top: 50px; font-weight: bold; }
            }
        </style>
        <div class="signatures" style="display: none;">
            <div>توقيع الموظف المذكور<br><br>........................</div>
            <div>توقيع المحاسب / الإدارة<br><br>........................</div>
            <div>اعتماد الإدارة العليا<br><br>........................</div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ob_end_flush(); ?>
