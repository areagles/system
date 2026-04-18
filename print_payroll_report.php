<?php
require 'auth.php';
require 'config.php';

app_ensure_payroll_schema($conn);

if (!app_user_can_any(['payroll.view', 'finance.view'])) {
    http_response_code(403);
    exit('⛔ غير مصرح لك بطباعة تقرير الرواتب.');
}

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$search = trim((string)($_GET['q'] ?? ''));
$employeeFilter = (int)($_GET['employee_id'] ?? 0);
$statusFilter = trim((string)($_GET['status'] ?? ''));
$monthFilter = trim((string)($_GET['month_year'] ?? ''));

$where = [];
if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $where[] = "(u.full_name LIKE '%{$safeSearch}%' OR p.employee_name_snapshot LIKE '%{$safeSearch}%' OR p.month_year LIKE '%{$safeSearch}%')";
}
if ($employeeFilter > 0) {
    $where[] = "p.employee_id = {$employeeFilter}";
}
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'partially_paid', 'paid'], true)) {
    $safeStatus = $conn->real_escape_string($statusFilter);
    $where[] = "p.status = '{$safeStatus}'";
}
if ($monthFilter !== '') {
    $safeMonth = $conn->real_escape_string($monthFilter);
    $where[] = "p.month_year = '{$safeMonth}'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = [];
$summary = [
    'gross' => 0.0,
    'bonus' => 0.0,
    'other_deductions' => 0.0,
    'loan_deductions' => 0.0,
    'net' => 0.0,
    'paid' => 0.0,
    'remaining' => 0.0,
];

$sql = "SELECT p.*, u.full_name
        FROM payroll_sheets p
        LEFT JOIN users u ON p.employee_id = u.id
        {$whereSql}
        ORDER BY p.month_year DESC, p.id DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $loanDeduct = (float)($row['loan_deduction'] ?? 0);
        $otherDeduct = max(0, (float)($row['deductions'] ?? 0) - $loanDeduct);
        $row['employee_label'] = app_payroll_employee_label($row);
        $row['loan_deduction_value'] = $loanDeduct;
        $row['other_deduction_value'] = $otherDeduct;
        $rows[] = $row;

        $summary['gross'] += (float)($row['basic_salary'] ?? 0);
        $summary['bonus'] += (float)($row['bonus'] ?? 0);
        $summary['other_deductions'] += $otherDeduct;
        $summary['loan_deductions'] += $loanDeduct;
        $summary['net'] += (float)($row['net_salary'] ?? 0);
        $summary['paid'] += (float)($row['paid_amount'] ?? 0);
        $summary['remaining'] += (float)($row['remaining_amount'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير الرواتب | <?php echo app_h($appName); ?></title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background:#fff; color:#000; padding:24px; }
        .no-print { margin-bottom: 18px; text-align:center; }
        .btn { display:inline-block; padding:10px 18px; border:1px solid #000; background:#f5f5f5; color:#000; text-decoration:none; margin:0 6px; cursor:pointer; }
        .head { text-align:center; margin-bottom:18px; }
        .head h1 { margin:0 0 8px; font-size:24px; }
        .meta { margin:8px auto 20px; max-width:900px; border:1px dashed #999; padding:10px 12px; line-height:1.8; }
        .cards { display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; margin-bottom:18px; }
        .card { border:1px solid #000; padding:10px; }
        .card .label { font-size:12px; color:#444; margin-bottom:8px; }
        .card .value { font-size:18px; font-weight:bold; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #000; padding:8px; text-align:right; font-size:13px; }
        th { background:#f0f0f0; }
        .muted { color:#555; }
        @media print { .no-print { display:none; } body { padding:0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">طباعة التقرير</button>
        <a class="btn" href="payroll.php?<?php echo http_build_query($_GET); ?>">رجوع</a>
    </div>

    <div class="head">
        <h1>تقرير الرواتب</h1>
        <div><?php echo app_h($appName); ?></div>
    </div>
    <div class="meta">
        <div>البحث: <?php echo app_h($search !== '' ? $search : 'الكل'); ?></div>
        <div>الحالة: <?php echo app_h($statusFilter !== '' ? $statusFilter : 'الكل'); ?></div>
        <div>الشهر: <?php echo app_h($monthFilter !== '' ? $monthFilter : 'كل الشهور'); ?></div>
        <div>عدد المسيرات: <?php echo count($rows); ?></div>
    </div>

    <div class="cards">
        <div class="card"><div class="label">إجمالي الأساسي</div><div class="value"><?php echo number_format($summary['gross'], 2); ?></div></div>
        <div class="card"><div class="label">إجمالي الصافي</div><div class="value"><?php echo number_format($summary['net'], 2); ?></div></div>
        <div class="card"><div class="label">المدفوع</div><div class="value"><?php echo number_format($summary['paid'], 2); ?></div></div>
        <div class="card"><div class="label">المتبقي</div><div class="value"><?php echo number_format($summary['remaining'], 2); ?></div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>الشهر</th>
                <th>الموظف</th>
                <th>الأساسي</th>
                <th>الإضافي</th>
                <th>خصم السلف</th>
                <th>خصومات أخرى</th>
                <th>الصافي</th>
                <th>المدفوع</th>
                <th>المتبقي</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo app_h((string)$row['month_year']); ?></td>
                        <td><?php echo app_h((string)$row['employee_label']); ?></td>
                        <td><?php echo number_format((float)$row['basic_salary'], 2); ?></td>
                        <td><?php echo number_format((float)$row['bonus'], 2); ?></td>
                        <td><?php echo number_format((float)$row['loan_deduction_value'], 2); ?></td>
                        <td><?php echo number_format((float)$row['other_deduction_value'], 2); ?></td>
                        <td><?php echo number_format((float)$row['net_salary'], 2); ?></td>
                        <td><?php echo number_format((float)$row['paid_amount'], 2); ?></td>
                        <td><?php echo number_format((float)$row['remaining_amount'], 2); ?></td>
                        <td><?php echo app_h((string)$row['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="10" class="muted">لا توجد بيانات مطابقة للمرشحات الحالية.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
