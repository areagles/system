<?php
require 'config.php';
require 'auth.php';

$isEnglish = app_current_lang($conn) === 'en';
$canPricingView = app_user_can('pricing.view') || app_is_super_user() || ((string)($_SESSION['role'] ?? '') === 'admin');
if (!$canPricingView) {
    die($isEnglish ? 'Access denied.' : 'غير مصرح.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die($isEnglish ? 'Invalid pricing file.' : 'ملف تسعير غير صالح.');
}

$stmt = $conn->prepare("
    SELECT pr.*, c.name AS client_name, c.phone AS client_phone
    FROM app_pricing_records pr
    LEFT JOIN clients c ON c.id = pr.client_id
    WHERE pr.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    die($isEnglish ? 'Pricing file not found.' : 'ملف التسعير غير موجود.');
}

$snapshot = json_decode((string)($record['snapshot_json'] ?? ''), true);
$calc = is_array($snapshot['calc'] ?? null) ? $snapshot['calc'] : [];
$appName = app_setting_get($conn, 'app_name', 'Arab Eagles');
$appLogo = app_brand_logo_path($conn, 'assets/img/Logo.png');
$brandProfile = app_brand_profile($conn);
$outputShowFooter = !empty($brandProfile['show_footer']);
$footerLines = app_brand_output_lines($brandProfile, 'footer', true);
?>
<!DOCTYPE html>
<html lang="<?php echo $isEnglish ? 'en' : 'ar'; ?>" dir="<?php echo $isEnglish ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo app_h($isEnglish ? 'Pricing File' : 'ملف التسعير'); ?> #<?php echo (int)$record['id']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{background:#050505;color:#fff;font-family:'Cairo',sans-serif;margin:0;padding:18px}
        .sheet{max-width:860px;margin:0 auto;background:#121212;border:1px solid #333;border-radius:16px;overflow:hidden}
        .head{display:flex;justify-content:space-between;align-items:center;padding:24px;border-bottom:1px solid #333}
        .head h1{margin:0;color:#d4af37;font-size:2rem}
        .head p{margin:6px 0 0;color:#aaa}
        .logo{width:86px;max-width:100%}
        .body{padding:24px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:18px}
        .card{border:1px solid #2d2d2d;border-radius:12px;padding:12px 14px;background:#0e0e0e}
        .card small{display:block;color:#999;margin-bottom:5px}
        .card strong{color:#fff}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px 8px;border-bottom:1px solid #2f2f2f;text-align:right}
        th{color:#d4af37;background:#191919}
        .total{margin-top:18px;padding:16px;border-radius:12px;background:#d4af37;color:#111;font-weight:900;display:flex;justify-content:space-between}
        .notes{margin-top:18px;border:1px dashed #444;border-radius:12px;padding:14px;color:#ddd;line-height:1.8}
        .footer{margin-top:18px;padding-top:16px;border-top:1px solid #333;color:#999;font-size:.9rem}
        .footer p{margin:0 0 4px}
        .actions{max-width:860px;margin:14px auto 0;display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 20px;border-radius:14px;background:linear-gradient(135deg,#e5c44f,#c99a17);color:#111;text-decoration:none;font-weight:800;border:none;cursor:pointer}
        .btn.secondary{background:#171717;color:#f3f4f6;border:1px solid rgba(255,255,255,.12)}
        @media print{
            body{background:#fff;color:#111;padding:0}
            .sheet{border:none;border-radius:0;max-width:none}
            .actions{display:none!important}
            .head,.body,.card,.sheet,.footer,.notes,th,td{color:#111!important;background:#fff!important}
            th{background:#f3f3f3!important}
            .total{background:#f3f3f3!important;color:#111!important}
            @page{size:A4;margin:12mm}
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="head">
            <div>
                <h1><?php echo app_h($isEnglish ? 'Pricing File' : 'ملف التسعير'); ?></h1>
                <p><?php echo app_h((string)$appName); ?> | <?php echo app_h((string)($record['pricing_ref'] ?: ('PRC-' . str_pad((string)$record['id'], 5, '0', STR_PAD_LEFT)))); ?></p>
            </div>
            <img src="<?php echo app_h($appLogo); ?>" alt="<?php echo app_h($appName); ?>" class="logo">
        </div>
        <div class="body">
            <div class="grid">
                <div class="card"><small><?php echo app_h($isEnglish ? 'Client' : 'العميل'); ?></small><strong><?php echo app_h((string)($record['client_name'] ?? '-')); ?></strong></div>
                <div class="card"><small><?php echo app_h($isEnglish ? 'Operation' : 'العملية'); ?></small><strong><?php echo app_h((string)($record['operation_name'] ?? '-')); ?></strong></div>
                <div class="card"><small><?php echo app_h($isEnglish ? 'Phone' : 'الهاتف'); ?></small><strong><?php echo app_h((string)($record['client_phone'] ?? '-')); ?></strong></div>
                <div class="card"><small><?php echo app_h($isEnglish ? 'Date' : 'التاريخ'); ?></small><strong><?php echo app_h((string)($record['created_at'] ?? '')); ?></strong></div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th><?php echo app_h($isEnglish ? 'Item' : 'البند'); ?></th>
                        <th><?php echo app_h($isEnglish ? 'Value' : 'القيمة'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array)($calc['stage_rows'] ?? []) as $row): ?>
                        <tr>
                            <td><?php echo app_h((string)($row['label'] ?? '')); ?></td>
                            <td><?php echo app_h(number_format((float)($row['value'] ?? 0), 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ((array)($calc['printing_rows'] ?? []) as $row): ?>
                        <tr>
                            <td><?php echo app_h((string)($row['label'] ?? '')); ?></td>
                            <td><?php echo app_h(number_format((float)($row['value'] ?? 0), 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ((array)($calc['finishing_rows'] ?? []) as $row): ?>
                        <tr>
                            <td><?php echo app_h((string)($row['label'] ?? '')); ?></td>
                            <td><?php echo app_h(number_format((float)($row['value'] ?? 0), 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total">
                <span><?php echo app_h($isEnglish ? 'Final Total' : 'الإجمالي النهائي'); ?></span>
                <span><?php echo app_h(number_format((float)($record['total_amount'] ?? 0), 2)); ?></span>
            </div>

            <?php if (!empty($record['notes'])): ?>
                <div class="notes">
                    <strong><?php echo app_h($isEnglish ? 'Notes' : 'ملاحظات'); ?></strong><br>
                    <?php echo nl2br(app_h((string)$record['notes'])); ?>
                </div>
            <?php endif; ?>

            <?php if ($outputShowFooter && !empty($footerLines)): ?>
                <div class="footer">
                    <?php foreach ($footerLines as $line): ?>
                        <p><?php echo app_h((string)$line); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions">
        <a class="btn secondary" href="pricing_records.php"><?php echo app_h($isEnglish ? 'Back' : 'رجوع'); ?></a>
        <button class="btn" onclick="window.print()"><?php echo app_h($isEnglish ? 'Print / PDF' : 'طباعة / PDF'); ?></button>
    </div>
</body>
</html>
