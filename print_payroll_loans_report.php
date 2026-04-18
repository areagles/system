<?php
require 'auth.php';
require 'config.php';

app_ensure_payroll_schema($conn);

if (!app_user_can_any(['payroll.view', 'finance.view'])) {
    http_response_code(403);
    exit('⛔ غير مصرح لك بطباعة تقرير السلف.');
}

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$search = trim((string)($_GET['q'] ?? ''));
$employeeFilter = (int)($_GET['employee_id'] ?? 0);

$where = [];
if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $where[] = "u.full_name LIKE '%{$safeSearch}%'";
}
if ($employeeFilter > 0) {
    $where[] = "u.id = {$employeeFilter}";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = [];
$summary = ['issued' => 0.0, 'cash' => 0.0, 'payroll' => 0.0, 'outstanding' => 0.0, 'salary_due' => 0.0];
$sql = "
    SELECT
        u.id,
        u.full_name,
        (SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE employee_id = u.id AND type = 'out' AND category = 'loan') AS loans_issued,
        (SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE employee_id = u.id AND type = 'in' AND category IN ('loan','loan_repayment')) AS repaid_cash,
        (SELECT IFNULL(SUM(loan_deduction),0) FROM payroll_sheets WHERE employee_id = u.id) AS repaid_payroll,
        (SELECT IFNULL(SUM(CASE WHEN remaining_amount > 0 THEN remaining_amount ELSE 0 END),0) FROM payroll_sheets WHERE employee_id = u.id AND status != 'paid') AS salary_due,
        (SELECT COUNT(*) FROM payroll_sheets WHERE employee_id = u.id) AS payroll_count
    FROM users u
    {$whereSql}
    ORDER BY u.full_name ASC
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $issued = (float)($row['loans_issued'] ?? 0);
        $cash = (float)($row['repaid_cash'] ?? 0);
        $payroll = (float)($row['repaid_payroll'] ?? 0);
        $outstanding = max(0, $issued - $cash - $payroll);
        $salaryDue = (float)($row['salary_due'] ?? 0);
        if ($issued <= 0 && $cash <= 0 && $payroll <= 0 && $salaryDue <= 0) {
            continue;
        }
        $row['outstanding'] = $outstanding;
        $rows[] = $row;
        $summary['issued'] += $issued;
        $summary['cash'] += $cash;
        $summary['payroll'] += $payroll;
        $summary['outstanding'] += $outstanding;
        $summary['salary_due'] += $salaryDue;
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير سلف الموظفين | <?php echo app_h($appName); ?></title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background:#fff; color:#000; padding:24px; }
        .no-print { margin-bottom: 18px; text-align:center; }
        .btn { display:inline-block; padding:10px 18px; border:1px solid #000; background:#f5f5f5; color:#000; text-decoration:none; margin:0 6px; cursor:pointer; }
        .head { text-align:center; margin-bottom:18px; }
        .cards { display:grid; grid-template-columns: repeat(5, 1fr); gap:10px; margin-bottom:18px; }
        .card { border:1px solid #000; padding:10px; }
        .card .label { font-size:12px; color:#444; margin-bottom:8px; }
        .card .value { font-size:18px; font-weight:bold; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #000; padding:8px; text-align:right; font-size:13px; }
        th { background:#f0f0f0; }
        @media print { .no-print { display:none; } body { padding:0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">طباعة التقرير</button>
        <a class="btn" href="payroll_loans_report.php?<?php echo http_build_query($_GET); ?>">رجوع</a>
    </div>
    <div class="head">
        <h1>تقرير سلف الموظفين</h1>
        <div><?php echo app_h($appName); ?></div>
    </div>
    <div class="cards">
        <div class="card"><div class="label">إجمالي السلف</div><div class="value"><?php echo number_format($summary['issued'], 2); ?></div></div>
        <div class="card"><div class="label">سداد نقدي</div><div class="value"><?php echo number_format($summary['cash'], 2); ?></div></div>
        <div class="card"><div class="label">سداد من الرواتب</div><div class="value"><?php echo number_format($summary['payroll'], 2); ?></div></div>
        <div class="card"><div class="label">الرصيد القائم</div><div class="value"><?php echo number_format($summary['outstanding'], 2); ?></div></div>
        <div class="card"><div class="label">مستحقات رواتب مفتوحة</div><div class="value"><?php echo number_format($summary['salary_due'], 2); ?></div></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>الموظف</th>
                <th>عدد المسيرات</th>
                <th>السلف المصروفة</th>
                <th>السداد النقدي</th>
                <th>السداد من الرواتب</th>
                <th>الرصيد القائم</th>
                <th>مستحقات رواتب مفتوحة</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo app_h((string)$row['full_name']); ?></td>
                        <td><?php echo number_format((float)$row['payroll_count']); ?></td>
                        <td><?php echo number_format((float)$row['loans_issued'], 2); ?></td>
                        <td><?php echo number_format((float)$row['repaid_cash'], 2); ?></td>
                        <td><?php echo number_format((float)$row['repaid_payroll'], 2); ?></td>
                        <td><?php echo number_format((float)$row['outstanding'], 2); ?></td>
                        <td><?php echo number_format((float)$row['salary_due'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">لا توجد بيانات سلف مطابقة.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
