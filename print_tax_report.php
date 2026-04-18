<?php
require 'auth.php';
require 'config.php';
app_handle_lang_switch($conn);
$isEnglish = app_current_lang($conn) === 'en';

if (!app_user_can('finance.reports.view')) {
    http_response_code(403);
    exit('⛔ ' . ($isEnglish ? 'You are not authorized to print tax reports.' : 'غير مصرح لك بطباعة التقارير الضريبية.'));
}

$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$dateFrom = (string)($_GET['date_from'] ?? date('Y-m-01'));
$dateTo = (string)($_GET['date_to'] ?? date('Y-m-t'));
$lawFilter = strtolower(trim((string)($_GET['law_key'] ?? 'all')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $dateFrom = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $dateTo = date('Y-m-t'); }
if ($dateFrom > $dateTo) { $tmp = $dateFrom; $dateFrom = $dateTo; $dateTo = $tmp; }

$taxDataset = app_tax_report_dataset($conn, $dateFrom, $dateTo, $lawFilter);
$lawCatalog = (array)($taxDataset['law_catalog'] ?? []);
$activeLaws = (array)($taxDataset['active_laws'] ?? []);
$lawSummary = (array)($taxDataset['law_summary'] ?? []);
$salesSummary = (array)($taxDataset['sales_summary'] ?? []);
$lawTypeSummary = (array)($taxDataset['law_type_summary'] ?? []);
$lawBracketSummary = (array)($taxDataset['law_bracket_summary'] ?? []);
$salesInvoiceCount = (int)($taxDataset['sales_invoice_count'] ?? 0);
$outputVatTotal = (float)($taxDataset['output_vat_total'] ?? 0);
$purchaseVatTotal = (float)($taxDataset['purchase_vat_total'] ?? 0);
$netVatDue = (float)($taxDataset['net_vat_due'] ?? 0);
$quoteTaxTotal = (float)($taxDataset['quote_tax_total'] ?? 0);
$purchaseCount = (int)($taxDataset['purchase_count'] ?? 0);
$quoteCount = (int)($taxDataset['quote_count'] ?? 0);
$salesGrandTotal = (float)($taxDataset['sales_grand_total'] ?? 0);
$declarationTitle = (string)($taxDataset['declaration_title'] ?? ($isEnglish ? 'Draft Tax Return' : 'مسودة إقرار ضريبي'));
$declarationBody = (string)($taxDataset['declaration_body'] ?? '');
$selectedLawLabel = $isEnglish ? 'All Laws' : 'كل القوانين';
foreach ($lawCatalog as $lawRow) {
    if ((string)($lawRow['key'] ?? '') === $lawFilter) {
        $selectedLawLabel = (string)($lawRow['name'] ?? $selectedLawLabel);
        break;
    }
}
?>
<!DOCTYPE html>
<html dir="<?php echo $isEnglish ? 'ltr' : 'rtl'; ?>" lang="<?php echo $isEnglish ? 'en' : 'ar'; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo app_h($isEnglish ? 'Print Tax Report' : 'طباعة التقرير الضريبي'); ?> | <?php echo app_h($appName); ?></title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background:#fff; color:#000; padding:24px; }
        .no-print { margin-bottom: 18px; text-align:center; }
        .btn { display:inline-block; padding:10px 18px; border:1px solid #000; background:#f5f5f5; color:#000; text-decoration:none; margin:0 6px; cursor:pointer; }
        .head { text-align:center; margin-bottom:18px; }
        .meta { margin:8px auto 20px; max-width:900px; border:1px dashed #999; padding:10px 12px; line-height:1.8; }
        .cards { display:grid; grid-template-columns: repeat(5, 1fr); gap:10px; margin-bottom:18px; }
        .card { border:1px solid #000; padding:10px; }
        .card .label { font-size:12px; color:#444; margin-bottom:8px; }
        .card .value { font-size:18px; font-weight:bold; }
        table { width:100%; border-collapse:collapse; margin-top:18px; }
        th, td { border:1px solid #000; padding:8px; text-align:<?php echo $isEnglish ? 'left' : 'right'; ?>; font-size:13px; }
        th { background:#f0f0f0; }
        .declaration { border:1px dashed #777; padding:12px; line-height:1.9; margin-top:18px; }
        @media print { .no-print { display:none; } body { padding:0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()"><?php echo app_h($isEnglish ? 'Print Report' : 'طباعة التقرير'); ?></button>
        <a class="btn" href="tax_reports.php?<?php echo http_build_query($_GET); ?>"><?php echo app_h($isEnglish ? 'Back' : 'رجوع'); ?></a>
    </div>
    <div class="head">
        <h1><?php echo app_h($isEnglish ? 'Tax Report & Settlement' : 'التقرير الضريبي والمقاصة'); ?></h1>
        <div><?php echo app_h($appName); ?></div>
    </div>
    <div class="meta">
        <div><?php echo app_h($isEnglish ? 'Period:' : 'الفترة:'); ?> <?php echo $isEnglish ? app_h($dateFrom . ' to ' . $dateTo) : 'من ' . app_h($dateFrom) . ' إلى ' . app_h($dateTo); ?></div>
        <div><?php echo app_h($isEnglish ? 'Law:' : 'القانون:'); ?> <?php echo app_h($selectedLawLabel); ?></div>
        <div><?php echo app_h($isEnglish ? 'Tax sales invoice count:' : 'عدد فواتير المبيعات الضريبية:'); ?> <?php echo number_format($salesInvoiceCount); ?></div>
    </div>
    <div class="cards">
        <div class="card"><div class="label"><?php echo app_h($isEnglish ? 'Active Laws' : 'القوانين المفعلة'); ?></div><div class="value"><?php echo number_format(count($activeLaws)); ?></div></div>
        <div class="card"><div class="label"><?php echo app_h($isEnglish ? 'Output Tax' : 'ضريبة المخرجات'); ?></div><div class="value"><?php echo number_format($outputVatTotal, 2); ?></div></div>
        <div class="card"><div class="label"><?php echo app_h($isEnglish ? 'Input Tax' : 'ضريبة المدخلات'); ?></div><div class="value"><?php echo number_format($purchaseVatTotal, 2); ?></div></div>
        <div class="card"><div class="label"><?php echo app_h($isEnglish ? 'Net Settlement' : 'صافي المقاصة'); ?></div><div class="value"><?php echo number_format($netVatDue, 2); ?></div></div>
        <div class="card"><div class="label"><?php echo app_h($isEnglish ? 'Quotation Taxes' : 'ضرائب عروض الأسعار'); ?></div><div class="value"><?php echo number_format($quoteTaxTotal, 2); ?></div></div>
        <div class="card"><div class="label"><?php echo app_h($isEnglish ? 'Tax Sales Total' : 'إجمالي المبيعات الضريبية'); ?></div><div class="value"><?php echo number_format($salesGrandTotal, 2); ?></div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th><?php echo app_h($isEnglish ? 'Active Law' : 'القانون المفعّل'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Key' : 'المفتاح'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Category' : 'الفئة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Frequency' : 'الدورية'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Settlement' : 'طريقة التسوية'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Brackets' : 'عدد الشرائح'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($activeLaws): ?>
                <?php foreach ($activeLaws as $lawRow): ?>
                    <tr>
                        <td><?php echo app_h((string)($lawRow['name'] ?? '')); ?></td>
                        <td><?php echo app_h((string)($lawRow['key'] ?? '')); ?></td>
                        <td><?php echo app_h((string)($lawRow['category'] ?? '')); ?></td>
                        <td><?php echo app_h((string)($lawRow['frequency'] ?? '')); ?></td>
                        <td><?php echo app_h((string)($lawRow['settlement_mode'] ?? '')); ?></td>
                        <td><?php echo number_format(count((array)($lawRow['brackets'] ?? []))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6"><?php echo app_h($isEnglish ? 'No active tax laws are configured.' : 'لا توجد قوانين ضريبية مفعلة.'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th><?php echo app_h($isEnglish ? 'Law' : 'القانون'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Invoice Count' : 'عدد الفواتير'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Net Base' : 'صافي الأساس'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Tax Total' : 'إجمالي الضرائب'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Document Total' : 'إجمالي المستندات'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($lawSummary): ?>
                <?php foreach ($lawSummary as $row): ?>
                    <tr>
                        <td><?php echo app_h((string)$row['name']); ?></td>
                        <td><?php echo number_format((float)$row['invoice_count']); ?></td>
                        <td><?php echo number_format((float)$row['net_base'], 2); ?></td>
                        <td><?php echo number_format((float)$row['tax_total'], 2); ?></td>
                        <td><?php echo number_format((float)$row['grand_total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5"><?php echo app_h($isEnglish ? 'No data found for the selected period.' : 'لا توجد بيانات ضمن الفترة المحددة.'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th><?php echo app_h($isEnglish ? 'Law' : 'القانون'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Tax Type' : 'نوع الضريبة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Category' : 'الفئة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Rate' : 'النسبة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Base Total' : 'إجمالي الأساس'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Tax Total' : 'إجمالي الضريبة'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($lawTypeSummary): ?>
                <?php foreach ($lawTypeSummary as $lawKey => $group): ?>
                    <?php foreach ((array)($group['taxes'] ?? []) as $taxRow): ?>
                        <tr>
                            <td><?php echo app_h((string)($group['law_name'] ?? $lawKey)); ?></td>
                            <td><?php echo app_h((string)($taxRow['name'] ?? '')); ?></td>
                            <td><?php echo app_h((string)($taxRow['category'] ?? '')); ?></td>
                            <td><?php echo number_format((float)($taxRow['rate'] ?? 0), 2); ?>%</td>
                            <td><?php echo number_format((float)($taxRow['base_total'] ?? 0), 2); ?></td>
                            <td><?php echo number_format((float)($taxRow['amount_total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6"><?php echo app_h($isEnglish ? 'No tax types linked to laws were found in this period.' : 'لا توجد أنواع ضرائب مرتبطة بالقوانين خلال الفترة.'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th><?php echo app_h($isEnglish ? 'Tax' : 'الضريبة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Category' : 'الفئة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Rate' : 'النسبة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Calculation Mode' : 'طريقة الحساب'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Base Total' : 'إجمالي الأساس'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Tax Total' : 'إجمالي الضريبة'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($salesSummary): ?>
                <?php foreach ($salesSummary as $row): ?>
                    <tr>
                        <td><?php echo app_h((string)$row['name']); ?></td>
                        <td><?php echo app_h((string)$row['category']); ?></td>
                        <td><?php echo number_format((float)$row['rate'], 2); ?>%</td>
                        <td><?php echo app_h(((string)$row['mode'] === 'subtract') ? ($isEnglish ? 'Deduct from invoice' : 'خصم من الفاتورة') : ($isEnglish ? 'Add to invoice' : 'إضافة على الفاتورة')); ?></td>
                        <td><?php echo number_format((float)$row['base_total'], 2); ?></td>
                        <td><?php echo number_format((float)$row['amount_total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6"><?php echo app_h($isEnglish ? 'No matching tax detail lines were found.' : 'لا توجد تفاصيل ضرائب مطابقة.'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th><?php echo app_h($isEnglish ? 'Law' : 'القانون'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Applied Basis' : 'الوعاء المعتمد'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Bracket' : 'الشريحة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'From' : 'من'); ?></th>
                <th><?php echo app_h($isEnglish ? 'To' : 'إلى'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Rate' : 'النسبة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Slice Amount' : 'الوعاء داخل الشريحة'); ?></th>
                <th><?php echo app_h($isEnglish ? 'Estimated Tax' : 'الضريبة التقديرية'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($lawBracketSummary): ?>
                <?php foreach ($lawBracketSummary as $lawKey => $lawRow): ?>
                    <?php if (!empty($lawRow['rows'])): ?>
                        <?php foreach ((array)$lawRow['rows'] as $bracketRow): ?>
                            <tr>
                                <td><?php echo app_h((string)($lawRow['law_name'] ?? $lawKey)); ?></td>
                                <td><?php echo app_h((string)($lawRow['basis_label'] ?? ($isEnglish ? 'Basis' : 'الوعاء'))); ?>: <?php echo number_format((float)($lawRow['taxable_amount'] ?? 0), 2); ?></td>
                                <td><?php echo app_h((string)($bracketRow['label'] ?? '')); ?></td>
                                <td><?php echo number_format((float)($bracketRow['from'] ?? 0), 2); ?></td>
                                <td><?php echo $bracketRow['to'] === null ? app_h($isEnglish ? 'Open' : 'مفتوحة') : number_format((float)$bracketRow['to'], 2); ?></td>
                                <td><?php echo number_format((float)($bracketRow['rate'] ?? 0), 4); ?>%</td>
                                <td><?php echo number_format((float)($bracketRow['slice_amount'] ?? 0), 2); ?></td>
                                <td><?php echo number_format((float)($bracketRow['estimated_due'] ?? 0), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td><?php echo app_h((string)($lawRow['law_name'] ?? $lawKey)); ?></td>
                            <td><?php echo app_h((string)($lawRow['basis_label'] ?? ($isEnglish ? 'Basis' : 'الوعاء'))); ?>: <?php echo number_format((float)($lawRow['taxable_amount'] ?? 0), 2); ?></td>
                            <td colspan="6"><?php echo app_h($isEnglish ? 'No brackets are defined for this law in Master Data.' : 'لا توجد شرائح معرفة لهذا القانون في Master Data.'); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8"><?php echo app_h($isEnglish ? 'No matching bracket data was found.' : 'لا توجد بيانات شرائح مطابقة.'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="declaration">
        <strong><?php echo app_h($declarationTitle); ?></strong><br>
        <?php echo app_h($declarationBody); ?><br>
        <?php echo app_h($isEnglish ? 'Purchase invoices used in settlement:' : 'عدد فواتير المشتريات المستخدمة في المقاصة:'); ?> <?php echo number_format($purchaseCount); ?><br>
        <?php echo app_h($isEnglish ? 'Tax quotations during the period:' : 'عدد عروض الأسعار الضريبية خلال الفترة:'); ?> <?php echo number_format($quoteCount); ?>
    </div>
</body>
</html>
