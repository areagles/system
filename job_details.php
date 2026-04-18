<?php
// job_details.php - (Universal Logic Fix V6.0)
// هذا التعديل يضمن عمل زر الإنهاء وإعادة الفتح مع جميع أنواع قواعد البيانات

ob_start();
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require_once 'job_engine.php';
app_handle_lang_switch($conn);
$is_en = app_lang_is('en');
$tr = static function (string $ar, string $en) use ($is_en): string {
    return $is_en ? $en : $ar;
};
$is_async_job_request = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
        || trim((string)($_POST['__async_form'] ?? '')) === '1'
        || strpos(strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? ''))), 'application/json') !== false
    )
);

// --- 1. المحرك الذكي لتحديث الحالة (Universal Update Engine) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    if (!app_verify_csrf($_POST['_csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
    
    $action_id = intval($_GET['id']);
    app_require_job_access($conn, $action_id, true);

    $canManageAssignments = app_user_can('jobs.assign') || app_user_can('jobs.manage_all');
    if (isset($_POST['acl_assign_user']) || isset($_POST['acl_unassign_user'])) {
        if (!$canManageAssignments) {
            http_response_code(403);
            die('غير مصرح لك بإدارة أعضاء العملية.');
        }

        if (isset($_POST['acl_assign_user'])) {
            $targetUserId = (int)($_POST['assign_user_id'] ?? 0);
            $assignRole = trim((string)($_POST['assign_role'] ?? 'member'));
            if ($targetUserId > 0) {
                app_assign_user_to_job($conn, $action_id, $targetUserId, $assignRole, app_user_id());
            }
        }

        if (isset($_POST['acl_unassign_user'])) {
            $targetUserId = (int)($_POST['unassign_user_id'] ?? 0);
            if ($targetUserId > 0) {
                app_unassign_user_from_job($conn, $action_id, $targetUserId);
            }
        }

        app_safe_redirect("job_details.php?id=" . $action_id . "&msg=acl_updated", 'index.php');
        exit;
    }

    $canManageCosts = app_user_can_any(['jobs.assign', 'jobs.manage_all', 'inventory.stock.adjust', 'finance.transactions.create']);
    $jobStageMap = [];
    if (isset($_POST['add_service_cost']) || isset($_POST['update_service_cost'])) {
        $actionJob = job_load_header_data($conn, $action_id);
        if (is_array($actionJob)) {
            $actionJobType = (string)($actionJob['job_type'] ?? '');
            $jobStageMap = job_stage_map_for_type($conn, $actionJobType);
        }
    }
    if (isset($_POST['issue_material'])) {
        if (!$canManageCosts) {
            http_response_code(403);
            die('غير مصرح لك بتسجيل تكلفة الخامات.');
        }
        $itemId = (int)($_POST['material_item_id'] ?? 0);
        $warehouseId = (int)($_POST['material_warehouse_id'] ?? 0);
        $qty = (float)($_POST['material_qty'] ?? 0);
        $stageKey = trim((string)($_POST['material_stage_key'] ?? ''));
        $extraNotes = trim((string)($_POST['material_notes'] ?? ''));

        if ($itemId <= 0 || $warehouseId <= 0 || $qty <= 0) {
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=cost_error", 'index.php');
            exit;
        }

        try {
            job_issue_material_cost($conn, $action_id, (int)($_SESSION['user_id'] ?? 0), $itemId, $warehouseId, $qty, $stageKey, $extraNotes);
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=material_cost_added", 'index.php');
            exit;
        } catch (Throwable $e) {
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=cost_error", 'index.php');
            exit;
        }
    }

    if (isset($_POST['delete_material_cost'])) {
        if (!$canManageCosts) {
            http_response_code(403);
            die('غير مصرح لك بحذف تكلفة الخامات.');
        }
        $materialTransId = (int)($_POST['material_transaction_id'] ?? 0);
        if ($materialTransId <= 0) {
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=cost_error", 'index.php');
            exit;
        }
        try {
            job_reverse_material_cost($conn, $action_id, $materialTransId);
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=material_cost_deleted", 'index.php');
            exit;
        } catch (Throwable $e) {
            error_log('job_details delete material failed: ' . $e->getMessage());
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=cost_error", 'index.php');
            exit;
        }
    }

    if (isset($_POST['add_service_cost'])) {
        if (!$canManageCosts) {
            http_response_code(403);
            die('غير مصرح لك بتسجيل تكلفة الخدمات.');
        }
        $stageKey = trim((string)($_POST['service_stage_key'] ?? ''));
        $serviceName = trim((string)($_POST['service_name'] ?? ''));
        $qty = (float)($_POST['service_qty'] ?? 0);
        $unitCost = (float)($_POST['service_unit_cost'] ?? 0);
        $notes = trim((string)($_POST['service_notes'] ?? ''));
        if ($stageKey === '' || !array_key_exists($stageKey, $jobStageMap) || $serviceName === '' || $qty <= 0 || $unitCost < 0) {
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=cost_error", 'index.php');
            exit;
        }
        job_add_service_cost($conn, $action_id, (int)($_SESSION['user_id'] ?? 0), $stageKey, $serviceName, $qty, $unitCost, $notes);
        app_safe_redirect("job_details.php?id=" . $action_id . "&msg=service_cost_added", 'index.php');
        exit;
    }

    if (isset($_POST['update_service_cost'])) {
        if (!$canManageCosts) {
            http_response_code(403);
            die('غير مصرح لك بتعديل تكاليف الخدمات.');
        }
        $serviceCostId = (int)($_POST['service_cost_id'] ?? 0);
        $stageKey = trim((string)($_POST['service_stage_key'] ?? ''));
        $serviceName = trim((string)($_POST['service_name'] ?? ''));
        $qty = (float)($_POST['service_qty'] ?? 0);
        $unitCost = (float)($_POST['service_unit_cost'] ?? 0);
        $notes = trim((string)($_POST['service_notes'] ?? ''));
        if ($serviceCostId <= 0 || $stageKey === '' || !array_key_exists($stageKey, $jobStageMap) || $serviceName === '' || $qty <= 0 || $unitCost < 0) {
            app_safe_redirect("job_details.php?id=" . $action_id . "&msg=cost_error", 'index.php');
            exit;
        }
        job_update_service_cost($conn, $action_id, $serviceCostId, $stageKey, $serviceName, $qty, $unitCost, $notes);
        app_safe_redirect("job_details.php?id=" . $action_id . "&msg=service_cost_updated", 'index.php');
        exit;
    }

    if (isset($_POST['delete_service_cost'])) {
        if (!$canManageCosts) {
            http_response_code(403);
            die('غير مصرح لك بحذف تكاليف الخدمات.');
        }
        $serviceCostId = (int)($_POST['service_cost_id'] ?? 0);
        if ($serviceCostId > 0) {
            job_delete_service_cost($conn, $action_id, $serviceCostId);
        }
        app_safe_redirect("job_details.php?id=" . $action_id . "&msg=service_cost_deleted", 'index.php');
        exit;
    }

    $action_msg = '';
    if (isset($_POST['archive_job'])) {
        $action_msg = 'archived';
    } elseif (isset($_POST['reopen_job'])) {
        $action_msg = 'reopened';
    }

    if ($action_msg !== '') {
        if ($action_msg === 'archived') {
            job_archive_transition($conn, $action_id);
        } elseif ($action_msg === 'reopened') {
            job_reopen_transition($conn, $action_id);
        }
        app_safe_redirect("job_details.php?id=" . $action_id . "&msg=" . $action_msg, 'index.php');
        exit;
    }
}
// ---------------------------------------------------------

if ($is_async_job_request && (!empty($_GET['id']) || !empty($_GET['job']))) {
    $job_ref = (string)($_GET['id'] ?? $_GET['job'] ?? '');
    $job_id = job_resolve_id($conn, $job_ref);
    if ($job_id > 0) {
        app_require_job_access($conn, $job_id, false);
        $job = job_load_header_data($conn, $job_id);
        if (is_array($job)) {
            $job_type = (string)($job['job_type'] ?? '');
            $module_map = [
                'print' => 'print.php',
                'carton' => 'carton.php',
                'plastic' => 'plastic.php',
                'web' => 'web.php',
                'social' => 'social.php',
                'design_only' => 'design_only.php',
            ];
            $module_file = __DIR__ . '/modules/' . (string)($module_map[$job_type] ?? 'generic.php');
            if (!is_file($module_file)) {
                $module_file = __DIR__ . '/modules/generic.php';
            }
            $app_module_embedded = true;
            include $module_file;
            exit;
        }
    }
}

require 'header.php';

if((!isset($_GET['id']) || $_GET['id'] === '') && (!isset($_GET['job']) || $_GET['job'] === '')) {
    die("<div class='container page-shell'><div class='royal-alert error'>" . app_h($tr('رابط غير صحيح.', 'Invalid link.')) . "</div></div>");
}

$job_ref = (string)($_GET['id'] ?? $_GET['job'] ?? '');
$job_id = job_resolve_id($conn, $job_ref);
if ($job_id <= 0) {
    die("<div class='container page-shell'><div class='royal-alert error'>" . app_h($tr('العملية غير موجودة.', 'Job not found.')) . "</div></div>");
}
app_require_job_access($conn, $job_id, false);

// جلب البيانات (يدعم MySQLi فقط للعرض لأن أغلب القوالب تعتمد عليه)
// إذا كان نظامك PDO بالكامل، يرجى تحويل هذا الاستعلام
if (isset($conn)) {
    $job = job_load_header_data($conn, $job_id);
} elseif (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT j.*, c.name as client_name, c.phone as client_phone 
            FROM job_orders j 
            LEFT JOIN clients c ON j.client_id = c.id 
            WHERE j.id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
}

if(!$job) {
    die("<div class='container page-shell'><div class='royal-alert error'>" . app_h($tr('العملية غير موجودة.', 'Job not found.')) . "</div></div>");
}

$job_type = $job['job_type'];
$curr = $job['current_stage']; 
$can_manage_acl = app_user_can('jobs.assign') || app_user_can('jobs.manage_all');
$can_manage_costs = app_user_can_any(['jobs.assign', 'jobs.manage_all', 'inventory.stock.adjust', 'finance.transactions.create']);
$editingServiceCostId = isset($_GET['edit_service_cost']) ? (int)$_GET['edit_service_cost'] : 0;
$jobView = job_view_context($conn, $job_id, $can_manage_acl, (string)$job_type, $editingServiceCostId);
$job_assignments = $jobView['job_assignments'];
$assignable_users = $jobView['assignable_users'];
$jobStageMap = $jobView['job_stage_map'];
$jobFinancial = $jobView['job_financial'];
$costWarehouses = $jobView['cost_warehouses'];
$costItems = $jobView['cost_items'];
$serviceCatalogRows = $jobView['service_catalog_rows'];
$serviceCatalogMap = $jobView['service_catalog_map'];
$materialCostRows = $jobView['material_cost_rows'];
$serviceCostRows = $jobView['service_cost_rows'];
$serviceCostEditRow = $jobView['service_cost_edit_row'];
$profitSummary = job_profitability_summary($jobFinancial, $tr);

?>

<style>
    .royal-container { max-width: 1320px; margin: 30px auto; padding: 0 15px; display:grid; gap:18px; }
    .job-hero {
        position:relative;
        overflow:hidden;
        background:
            linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)),
            radial-gradient(circle at top left, rgba(212,175,55,0.12), transparent 34%),
            rgba(18,18,18,0.88);
        border:1px solid rgba(212,175,55,0.16);
        border-radius:24px;
        padding:24px;
        box-shadow:0 18px 38px rgba(0,0,0,0.24);
        backdrop-filter:blur(14px);
    }
    .job-hero::after {
        content:"";
        position:absolute;
        inset-inline-end:-56px;
        inset-block-start:-56px;
        width:160px;
        height:160px;
        border-radius:50%;
        background:radial-gradient(circle, rgba(212,175,55,0.1), transparent 70%);
        pointer-events:none;
    }
    .job-eyebrow {
        display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px;
        background:rgba(212,175,55,0.08); border:1px solid rgba(212,175,55,0.24); color:#f0d684;
        font-size:.76rem; font-weight:700; margin-bottom:14px;
    }
    .job-hero-head {
        display:grid;
        grid-template-columns:1fr;
        gap:18px;
        align-items:start;
    }
    .job-title { margin:0; color:#f7f1dc; font-size:1.85rem; line-height:1.35; }
    .job-subtitle { margin:10px 0 0; color:#a8abb1; line-height:1.8; max-width:760px; }
    .job-hero-kpis {
        display:grid;
        grid-template-columns:1fr;
        gap:10px;
    }
    .job-hero-kpi {
        border-radius:18px;
        border:1px solid rgba(255,255,255,0.08);
        background:rgba(255,255,255,0.035);
        padding:16px;
        min-height:98px;
    }
    .job-hero-kpi .label { color:#9ca0a8; font-size:.74rem; margin-bottom:8px; }
    .job-hero-kpi .value { color:#fff; font-size:1.25rem; font-weight:800; line-height:1.3; }
    .royal-alert { padding: 20px; border-radius: 10px; text-align: center; font-weight: bold; margin-top: 50px; border: 1px solid #333; }
    .royal-alert.error { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border-color: #e74c3c; }
    .royal-alert.success { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border-color: #2ecc71; margin-bottom: 20px; }
    .btn-royal {
        background: linear-gradient(45deg, var(--gold-primary, #d4af37), #b8860b);
        color: #000;
        padding: 8px 20px;
        border: 1px solid rgba(212, 175, 55, 0.6);
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s ease;
    }
    .btn-royal:hover { transform: translateY(-1px); }
    .btn-royal:disabled {
        background: rgba(212, 175, 55, 0.16);
        border-color: rgba(212, 175, 55, 0.38);
        color: #f6d980;
        cursor: not-allowed;
        opacity: 1;
    }
    .missing-module-card { background: var(--panel); border: 1px dashed #555; padding: 40px; text-align: center; border-radius: 15px; margin-top: 50px; }
    .glass-block {
        background:
            linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02)),
            rgba(18,18,18,0.76);
        border:1px solid rgba(255,255,255,0.08);
        border-radius:20px;
        padding:18px;
        box-shadow:0 14px 28px rgba(0,0,0,0.18);
        backdrop-filter:blur(12px);
    }
    .acl-panel {
        margin-bottom: 0;
        background: linear-gradient(135deg, rgba(212,175,55,0.07), rgba(255,255,255,0.02));
        border: 1px solid rgba(212,175,55,0.3);
        border-radius: 20px;
        padding: 18px;
    }
    .acl-row {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr auto;
        gap: 10px;
        align-items: end;
    }
    .acl-input {
        width: 100%;
        background: #111;
        color: #fff;
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        padding: 10px;
        font-family: 'Cairo', sans-serif;
    }
    .acl-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    .acl-chip {
        border: 1px solid #3a3a3a;
        background: #121212;
        color: #ddd;
        border-radius: 999px;
        padding: 6px 10px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
    }
    .acl-chip form { margin: 0; }
    .acl-remove {
        border: none;
        background: transparent;
        color: #ff7f7f;
        cursor: pointer;
        font-size: 0.9rem;
    }
    .acl-confirm-btn {
        min-width: 220px;
        padding: 12px 16px;
        font-size: 0.95rem;
        box-shadow: 0 10px 24px rgba(212, 175, 55, 0.22);
        border: 2px solid rgba(212, 175, 55, 0.65);
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        opacity: 1;
    }
    .acl-confirm-btn.is-ready { box-shadow: 0 12px 26px rgba(46, 204, 113, 0.28); border-color: rgba(46, 204, 113, 0.6); }
    .acl-help {
        margin-top: 8px;
        color: #b3b3b3;
        font-size: 0.82rem;
    }
    .cost-panel {
        margin-bottom: 0;
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.08), rgba(212, 175, 55, 0.08));
        border: 1px solid rgba(212, 175, 55, 0.28);
        border-radius: 20px;
        padding: 18px;
    }
    .job-collapsible-summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        color: #f2deb0;
        font-weight: 800;
        margin: 0 0 14px;
    }
    .job-collapsible-summary::-webkit-details-marker { display: none; }
    .job-collapsible-summary::after {
        content: "⌄";
        color: #d4af37;
        font-size: 1rem;
        transition: transform 0.2s ease;
    }
    .job-collapsible[open] > .job-collapsible-summary::after {
        transform: rotate(180deg);
    }
    .cost-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    .cost-kpi {
        background: #111;
        border: 1px solid #2d2d2d;
        border-radius: 10px;
        padding: 10px 12px;
    }
    .cost-kpi .k-label { color: #9b9b9b; font-size: 0.82rem; margin-bottom: 4px; }
    .cost-kpi .k-value { font-weight: 800; font-size: 1.15rem; color: #fff; }
    .cost-kpi.profit .k-value { color: #2ecc71; }
    .cost-kpi.loss .k-value { color: #e74c3c; }
    .cost-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 10px;
    }
    .cost-box {
        background: #0f0f0f;
        border: 1px solid #2b2b2b;
        border-radius: 10px;
        padding: 12px;
    }
    .cost-box h4 { margin: 0 0 10px; color: #d4af37; }
    .cost-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .cost-input {
        width: 100%;
        background: #090909;
        color: #fff;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 9px 10px;
        font-family: 'Cairo', sans-serif;
    }
    .cost-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .cost-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        min-width: 520px;
    }
    .cost-table th, .cost-table td {
        border-bottom: 1px solid #242424;
        padding: 8px 6px;
        font-size: 0.86rem;
        text-align: right;
    }
    .cost-table th { color: #a9a9a9; }
    .md-btn-neutral {
        border: 1px solid #444;
        background: #1a1a1a;
        color: #ddd;
        border-radius: 8px;
        padding: 5px 9px;
        cursor: pointer;
        font-family: 'Cairo', sans-serif;
    }
    @media (min-width: 901px) {
        .job-hero-head { grid-template-columns:minmax(0,1fr) minmax(240px,0.42fr); }
        .job-hero-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width: 900px) {
        .job-hero-head,
        .job-hero-kpis,
        .acl-row { grid-template-columns: 1fr; }
        .acl-confirm-btn { width: 100%; }
        .cost-grid { grid-template-columns: 1fr; }
        .cost-form-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
        .royal-container {
            gap: 14px;
            margin: 18px auto;
            padding: 0 10px 96px;
            overflow-x: clip;
        }
        .royal-container > * {
            width: 100%;
            max-width: 100%;
            margin-inline: 0;
        }
        .job-module-shell {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: clip;
        }
        .job-hero,
        .glass-block,
        .acl-panel,
        .cost-panel { padding: 12px; border-radius: 14px; }
        .job-title { font-size: 1.25rem; }
        .job-subtitle { display: none; }
        .job-eyebrow { margin-bottom: 10px; font-size: 0.7rem; padding: 5px 10px; }
        .job-hero-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .job-hero-kpi {
            min-width: 0;
            min-height: auto;
            padding: 10px;
            border-radius: 12px;
        }
        .job-hero-kpi .label { font-size: 0.68rem; margin-bottom: 5px; }
        .job-hero-kpi .value { font-size: 0.92rem; }
        .cost-kpis {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .cost-kpi {
            min-width: 0;
            padding: 8px 10px;
            border-radius: 10px;
        }
        .cost-kpi .k-label { font-size: 0.68rem; }
        .cost-kpi .k-value { font-size: 0.92rem; }
        .cost-box h4 { font-size: 1rem; margin-bottom: 8px; }
        .cost-box { padding: 10px; border-radius: 10px; }
        .cost-input,
        .acl-input { padding: 8px 10px; font-size: 0.9rem; }
        .btn-royal,
        .md-btn-neutral { min-height: 42px; font-size: 0.88rem; }
        .job-collapsible-summary {
            font-size: 0.92rem;
            margin-bottom: 10px;
            padding: 4px 0;
        }
        .job-collapsible-summary span:last-child { display: none; }
        .job-actions,
        .acl-actions,
        .cost-actions { flex-direction: column; }
        .job-actions .btn,
        .acl-actions .btn,
        .cost-actions .btn,
        .cost-form-grid input,
        .cost-form-grid select { width: 100%; }
        .cost-table-wrap { margin-inline: 0; overflow: visible; }
        .cost-table {
            min-width: 0;
            width: 100%;
        }
        .cost-table thead { display: none; }
        .cost-table,
        .cost-table tbody,
        .cost-table tr,
        .cost-table td {
            display: block;
            width: 100%;
        }
        .cost-table tr {
            background: #101010;
            border: 1px solid #242424;
            border-radius: 10px;
            padding: 8px;
            margin-bottom: 10px;
        }
        .cost-table td {
            border: 0;
            padding: 6px 0;
            text-align: right;
            min-width: 0;
        }
        .cost-table td::before {
            content: attr(data-label);
            display: block;
            color: #9b9b9b;
            font-size: 0.72rem;
            margin-bottom: 2px;
        }
        .acl-panel { order: 4; }
        .cost-panel { order: 5; }
        .job-module-shell { order: 3; }
    }
</style>

<div class="royal-container">
    <section class="job-hero">
        <div class="job-eyebrow">تفاصيل العملية</div>
        <div class="job-hero-head">
            <div>
                <h1 class="job-title"><?php echo app_h((string)($job['job_name'] ?? ('#' . $job_id))); ?></h1>
                <p class="job-subtitle">
                    <?php echo app_h($tr('عرض مركزي لمراحل العملية، الأعضاء، التكاليف، والربحية مع الحفاظ على مسارات التنفيذ الحالية.', 'Centralized view for stages, members, costs, and profitability while preserving the current execution flows.')); ?>
                </p>
            </div>
            <div class="job-hero-kpis">
                <div class="job-hero-kpi">
                    <div class="label"><?php echo app_h($tr('رقم العملية', 'Job number')); ?></div>
                    <div class="value"><?php echo app_h((string)($job['job_number'] ?: ('#' . $job_id))); ?></div>
                </div>
                <div class="job-hero-kpi">
                    <div class="label"><?php echo app_h($tr('العميل', 'Client')); ?></div>
                    <div class="value"><?php echo app_h((string)($job['client_name'] ?? $tr('غير محدد', 'Not specified'))); ?></div>
                </div>
                <div class="job-hero-kpi">
                    <div class="label"><?php echo app_h($tr('المرحلة الحالية', 'Current stage')); ?></div>
                    <div class="value"><?php echo app_h((string)($jobStageMap[(string)$curr] ?? (string)$curr)); ?></div>
                </div>
                <div class="job-hero-kpi">
                    <div class="label"><?php echo app_h($tr('نوع العملية', 'Operation type')); ?></div>
                    <div class="value"><?php echo app_h((string)($job['job_type'] ?? '-')); ?></div>
                </div>
            </div>
        </div>
    </section>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'archived'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم إنهاء وأرشفة العملية بنجاح.', 'Job archived successfully.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'reopened'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم إعادة فتح العملية للعمل.', 'Job reopened successfully.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'acl_updated'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم تحديث أعضاء العملية بنجاح.', 'Job members updated successfully.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'material_cost_added'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم صرف الخامة وتسجيل تكلفتها على العملية.', 'Material issued and its cost was added to this job.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'material_cost_deleted'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم إلغاء صرف الخامة وإعادة الكمية إلى المخزن.', 'Material issue was reversed and stock returned to the warehouse.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'service_cost_added'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم تسجيل تكلفة الخدمة/المرحلة بنجاح.', 'Service/stage cost recorded successfully.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'service_cost_updated'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم تحديث بند تكلفة الخدمة.', 'Service cost line updated.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'service_cost_deleted'): ?>
        <div class="royal-alert success"><?php echo app_h($tr('تم حذف بند تكلفة الخدمة.', 'Service cost line deleted.')); ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'cost_error'): ?>
        <div class="royal-alert error"><?php echo app_h($tr('تعذر تنفيذ العملية بسبب بيانات غير مكتملة أو رصيد غير كافٍ.', 'The action failed due to invalid data or insufficient stock.')); ?></div>
    <?php endif; ?>

    <div class="job-module-shell">
    <?php
    $module_map = [
        'print'       => 'print.php',
        'carton'      => 'carton.php',
        'plastic'     => 'plastic.php',
        'web'         => 'web.php',
        'social'      => 'social.php',
        'design_only' => 'design_only.php'
    ];
    $module_supported_stages = [
        'print' => ['briefing', 'design', 'client_rev', 'materials', 'pre_press', 'printing', 'finishing', 'delivery', 'accounting', 'completed'],
        'carton' => ['briefing', 'design', 'client_rev', 'pre_press', 'materials', 'printing', 'die_cutting', 'gluing', 'delivery', 'accounting', 'completed'],
        'plastic' => ['briefing', 'design', 'client_rev', 'cylinders', 'extrusion', 'printing', 'cutting', 'delivery', 'accounting', 'completed'],
        'web' => ['briefing', 'ui_design', 'client_rev', 'development', 'testing', 'launch', 'accounting', 'completed'],
        'social' => ['briefing', 'idea_review', 'content_writing', 'content_review', 'designing', 'design_review', 'publishing', 'accounting', 'completed'],
        'design_only' => ['briefing', 'design', 'client_rev', 'handover', 'accounting', 'completed'],
    ];
    $current_stage_key = (string)($job['current_stage'] ?? '');
    $force_generic_for_stage = isset($module_supported_stages[$job_type])
        && $current_stage_key !== ''
        && !in_array($current_stage_key, $module_supported_stages[$job_type], true);

    if (array_key_exists($job_type, $module_map)) {
        $module_file = "modules/" . $module_map[$job_type];
        if (!$force_generic_for_stage && file_exists($module_file)) {
            $app_module_embedded = true;
            include $module_file;
        } elseif (file_exists('modules/generic.php')) {
            $app_module_embedded = true;
            include 'modules/generic.php';
        } else {
            echo "<div class='missing-module-card'><h2 style='color:#e74c3c'>الموديول مفقود</h2></div>";
        }
    } else {
        if (file_exists('modules/generic.php')) {
            $app_module_embedded = true;
            include 'modules/generic.php';
        } else {
            echo "<div class='missing-module-card'><h2 style='color:#f1c40f'>نوع غير معروف</h2></div>";
        }
    }
    ?>
    </div>

    <?php if($can_manage_acl): ?>
        <details class="acl-panel glass-block job-collapsible">
            <summary class="job-collapsible-summary">
                <span><?php echo app_h($tr('صلاحيات دقيقة للعملية', 'Fine permissions for job')); ?> #<?php echo (int)$job_id; ?></span>
                <span style="color:#9a9a9a; font-size:0.82rem;"><?php echo app_h($tr('إدارة الفريق المصرح له فقط', 'Manage authorized team members only')); ?></span>
            </summary>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h3 style="margin:0; color:var(--gold-primary, #d4af37);"><?php echo app_h($tr('صلاحيات دقيقة للعملية', 'Fine permissions for job')); ?> #<?php echo (int)$job_id; ?></h3>
                <span style="color:#9a9a9a; font-size:0.85rem;"><?php echo app_h($tr('إدارة الفريق المصرح له فقط', 'Manage authorized team members only')); ?></span>
            </div>
            <form method="post" class="acl-row">
                <?php echo app_csrf_input(); ?>
                <div>
                    <label style="display:block; color:#bdbdbd; margin-bottom:6px;"><?php echo app_h($tr('المستخدم', 'User')); ?></label>
                    <select class="acl-input" name="assign_user_id" required>
                        <option value=""><?php echo app_h($tr('اختر مستخدمًا', 'Select user')); ?></option>
                        <?php foreach ($assignable_users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>">
                                <?php echo htmlspecialchars((string)$u['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                (<?php echo htmlspecialchars((string)$u['role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; color:#bdbdbd; margin-bottom:6px;"><?php echo app_h($tr('دور الإسناد', 'Assignment role')); ?></label>
                    <select class="acl-input" name="assign_role">
                        <option value="member"><?php echo app_h($tr('عضو فريق', 'Team member')); ?></option>
                        <option value="owner"><?php echo app_h($tr('مالك العملية', 'Job owner')); ?></option>
                        <option value="reviewer"><?php echo app_h($tr('مراجع', 'Reviewer')); ?></option>
                        <option value="finance"><?php echo app_h($tr('مالي', 'Finance')); ?></option>
                    </select>
                </div>
                <div>
                    <button type="submit" id="aclAssignBtn" name="acl_assign_user" class="btn-royal acl-confirm-btn"><i class="fa-solid fa-user-check"></i> <?php echo app_h($tr('تأكيد إضافة العضو', 'Confirm member assignment')); ?></button>
                </div>
            </form>
            <div id="aclAssignHint" class="acl-help"><?php echo app_h($tr('اختر مستخدمًا ثم اضغط زر التأكيد.', 'Select a user then press confirm.')); ?></div>
            <script>
                (function() {
                    const formEl = document.querySelector('form.acl-row');
                    const selectEl = formEl ? formEl.querySelector('select[name="assign_user_id"]') : null;
                    const btn = document.getElementById('aclAssignBtn');
                    const hint = document.getElementById('aclAssignHint');
                    const txtHintReady = <?php echo json_encode($tr('جاهز للتأكيد: اضغط لإضافة العضو المختار.', 'Ready: click to add the selected member.'), JSON_UNESCAPED_UNICODE); ?>;
                    const txtHintSelect = <?php echo json_encode($tr('اختر مستخدمًا ثم اضغط زر التأكيد.', 'Select a user then click confirm.'), JSON_UNESCAPED_UNICODE); ?>;
                    const txtAlertSelect = <?php echo json_encode($tr('يرجى اختيار المستخدم أولاً قبل التأكيد.', 'Please select a user first.'), JSON_UNESCAPED_UNICODE); ?>;
                    if (!formEl || !selectEl || !btn) return;
                    const sync = function() {
                        const hasUser = !!selectEl.value;
                        btn.classList.toggle('is-ready', hasUser);
                        if (hint) {
                            hint.textContent = hasUser
                                ? txtHintReady
                                : txtHintSelect;
                        }
                    };
                    selectEl.addEventListener('change', sync);
                    formEl.addEventListener('submit', function(e) {
                        if (!selectEl.value) {
                            e.preventDefault();
                            alert(txtAlertSelect);
                            return false;
                        }
                    });
                    sync();
                })();
            </script>
            <div class="acl-chips">
                <?php if (empty($job_assignments)): ?>
                    <span style="color:#8f8f8f;"><?php echo app_h($tr('لا يوجد أعضاء مسندون حتى الآن.', 'No assigned members yet.')); ?></span>
                <?php else: ?>
                    <?php foreach ($job_assignments as $member): ?>
                        <div class="acl-chip">
                            <span>
                                <?php echo htmlspecialchars((string)$member['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                •
                                <?php echo htmlspecialchars((string)$member['assigned_role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </span>
                            <form method="post">
                                <?php echo app_csrf_input(); ?>
                                <input type="hidden" name="unassign_user_id" value="<?php echo (int)$member['user_id']; ?>">
                                <button type="submit" name="acl_unassign_user" class="acl-remove" title="<?php echo app_h($tr('إلغاء الإسناد', 'Unassign')); ?>">✕</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </details>
    <?php endif; ?>

    <details class="cost-panel glass-block job-collapsible">
        <summary class="job-collapsible-summary">
            <span><?php echo app_h($tr('تكاليف العملية والربحية', 'Job costing and profitability')); ?> #<?php echo (int)$job_id; ?></span>
            <span style="color:#a7a7a7; font-size:0.82rem;"><?php echo app_h($profitSummary['profit_text']); ?></span>
        </summary>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
            <h3 style="margin:0; color:#8fd3ff;"><?php echo app_h($tr('تكاليف العملية والربحية', 'Job costing and profitability')); ?> #<?php echo (int)$job_id; ?></h3>
            <span style="color:#a7a7a7; font-size:0.86rem;">
                <?php echo app_h($profitSummary['profit_text']); ?>
            </span>
        </div>

        <div class="cost-kpis">
            <?php foreach ($profitSummary['cards'] as $card): ?>
            <div class="cost-kpi <?php echo app_h((string)($card['class'] ?? '')); ?>">
                <div class="k-label"><?php echo app_h((string)($card['label'] ?? '')); ?></div>
                <div class="k-value"><?php echo app_h((string)($card['value'] ?? '')); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cost-grid">
            <div class="cost-box">
                <h4><?php echo app_h($tr('صرف خامات من المخازن للعملية', 'Issue materials from inventory to this job')); ?></h4>
                <?php if ($can_manage_costs): ?>
                <form method="post">
                    <?php echo app_csrf_input(); ?>
                    <div class="cost-form-grid">
                        <select name="material_item_id" class="cost-input" required>
                            <option value=""><?php echo app_h($tr('اختر خامة/صنف', 'Select material/item')); ?></option>
                            <?php foreach ($costItems as $costItem): ?>
                                <option value="<?php echo (int)$costItem['id']; ?>">
                                    <?php echo app_h((string)$costItem['name'] . ' (' . (string)$costItem['item_code'] . ') - Avg: ' . number_format((float)$costItem['avg_unit_cost'], 2)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="material_warehouse_id" class="cost-input" required>
                            <option value=""><?php echo app_h($tr('اختر المخزن', 'Select warehouse')); ?></option>
                            <?php foreach ($costWarehouses as $costWh): ?>
                                <option value="<?php echo (int)$costWh['id']; ?>"><?php echo app_h((string)$costWh['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input name="material_qty" type="number" class="cost-input" min="0.01" step="0.01" placeholder="<?php echo app_h($tr('الكمية', 'Quantity')); ?>" required>
                        <select name="material_stage_key" class="cost-input">
                            <?php foreach ($jobStageMap as $stageKey => $stageLabel): ?>
                                <option value="<?php echo app_h((string)$stageKey); ?>" <?php echo ((string)$stageKey === (string)$curr) ? 'selected' : ''; ?>>
                                    <?php echo app_h((string)$stageLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input name="material_notes" class="cost-input" style="margin-top:8px;" placeholder="<?php echo app_h($tr('ملاحظات إضافية (اختياري)', 'Extra notes (optional)')); ?>">
                    <div style="margin-top:10px;">
                        <button type="submit" name="issue_material" class="btn-royal"><?php echo app_h($tr('صرف وتسجيل التكلفة', 'Issue and post cost')); ?></button>
                    </div>
                </form>
                <?php else: ?>
                    <div style="color:#aaa;"><?php echo app_h($tr('لا تملك صلاحية تسجيل تكاليف الخامات.', 'You do not have permission to record material costs.')); ?></div>
                <?php endif; ?>

                <table class="cost-table">
                    <thead>
                        <tr>
                            <th><?php echo app_h($tr('الخامة', 'Material')); ?></th>
                            <th><?php echo app_h($tr('المخزن', 'Warehouse')); ?></th>
                            <th><?php echo app_h($tr('المرحلة', 'Stage')); ?></th>
                            <th><?php echo app_h($tr('كمية', 'Qty')); ?></th>
                            <th><?php echo app_h($tr('التكلفة', 'Cost')); ?></th>
                            <th><?php echo app_h($tr('إجراء', 'Action')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materialCostRows)): ?>
                            <tr><td colspan="6" style="color:#777;" data-label="<?php echo app_h($tr('الحالة', 'Status')); ?>"><?php echo app_h($tr('لا توجد حركات خامات مسجلة على العملية.', 'No material issues recorded for this job.')); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($materialCostRows as $mRow): ?>
                                <tr>
                                    <td data-label="<?php echo app_h($tr('الخامة', 'Material')); ?>"><?php echo app_h((string)($mRow['item_name'] ?? '')); ?></td>
                                    <td data-label="<?php echo app_h($tr('المخزن', 'Warehouse')); ?>"><?php echo app_h((string)($mRow['warehouse_name'] ?? '')); ?></td>
                                    <td data-label="<?php echo app_h($tr('المرحلة', 'Stage')); ?>"><?php echo app_h((string)($jobStageMap[(string)($mRow['stage_key'] ?? '')] ?? (string)($mRow['stage_key'] ?? '-'))); ?></td>
                                    <td data-label="<?php echo app_h($tr('كمية', 'Qty')); ?>"><?php echo number_format((float)($mRow['qty_used'] ?? 0), 2); ?></td>
                                    <td data-label="<?php echo app_h($tr('التكلفة', 'Cost')); ?>"><?php echo number_format((float)($mRow['total_cost'] ?? 0), 2); ?></td>
                                    <td data-label="<?php echo app_h($tr('إجراء', 'Action')); ?>">
                                        <?php if ($can_manage_costs): ?>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('<?php echo app_h($tr('إلغاء صرف هذه الخامة وإعادة الكمية للمخزن؟', 'Reverse this material issue and return the quantity to stock?')); ?>')">
                                                <?php echo app_csrf_input(); ?>
                                                <input type="hidden" name="material_transaction_id" value="<?php echo (int)($mRow['id'] ?? 0); ?>">
                                                <button type="submit" name="delete_material_cost" class="md-btn-neutral"><?php echo app_h($tr('إلغاء', 'Reverse')); ?></button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#777;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="cost-box">
                <h4><?php echo app_h($tr('تكاليف الخدمات والمراحل', 'Service and stage cost lines')); ?></h4>
                <?php if ($can_manage_costs): ?>
                <form method="post">
                    <?php echo app_csrf_input(); ?>
                    <?php if ($serviceCostEditRow): ?>
                        <input type="hidden" name="service_cost_id" value="<?php echo (int)($serviceCostEditRow['id'] ?? 0); ?>">
                    <?php endif; ?>
                    <div class="cost-form-grid">
                        <select name="service_stage_key" class="cost-input">
                            <?php foreach ($jobStageMap as $stageKey => $stageLabel): ?>
                                <option value="<?php echo app_h((string)$stageKey); ?>" <?php echo ((string)$stageKey === (string)($serviceCostEditRow['stage_key'] ?? $curr)) ? 'selected' : ''; ?>>
                                    <?php echo app_h((string)$stageLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input list="serviceCatalogList" name="service_name" id="serviceNameInput" class="cost-input" placeholder="<?php echo app_h($tr('اسم الخدمة', 'Service name')); ?>" value="<?php echo app_h((string)($serviceCostEditRow['service_name'] ?? '')); ?>" required>
                        <input name="service_qty" type="number" class="cost-input" min="0.01" step="0.01" value="<?php echo app_h((string)($serviceCostEditRow['qty'] ?? '1')); ?>" placeholder="<?php echo app_h($tr('الكمية', 'Qty')); ?>" required>
                        <input name="service_unit_cost" id="serviceUnitCostInput" type="number" class="cost-input" min="0" step="0.01" value="<?php echo app_h((string)($serviceCostEditRow['unit_cost'] ?? '')); ?>" placeholder="<?php echo app_h($tr('تكلفة الوحدة', 'Unit cost')); ?>" required>
                    </div>
                    <input name="service_notes" class="cost-input" style="margin-top:8px;" placeholder="<?php echo app_h($tr('ملاحظات البند (اختياري)', 'Line notes (optional)')); ?>" value="<?php echo app_h((string)($serviceCostEditRow['notes'] ?? '')); ?>">
                    <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="submit" name="<?php echo $serviceCostEditRow ? 'update_service_cost' : 'add_service_cost'; ?>" class="btn-royal"><?php echo app_h($serviceCostEditRow ? $tr('تحديث تكلفة الخدمة', 'Update service cost') : $tr('إضافة تكلفة خدمة', 'Add service cost')); ?></button>
                        <?php if ($serviceCostEditRow): ?>
                            <a href="job_details.php?id=<?php echo (int)$job_id; ?>" class="md-btn-neutral" style="text-decoration:none; display:inline-flex; align-items:center;"><?php echo app_h($tr('إلغاء التعديل', 'Cancel edit')); ?></a>
                        <?php endif; ?>
                    </div>
                    <datalist id="serviceCatalogList">
                        <?php foreach ($serviceCatalogRows as $svcRow): ?>
                            <option
                                value="<?php echo app_h((string)$svcRow['item_label']); ?>"
                                data-price="<?php echo app_h((string)number_format((float)($svcRow['default_unit_price'] ?? 0), 2, '.', '')); ?>"
                            >
                                <?php echo app_h((string)$svcRow['catalog_group']); ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                </form>
                <?php else: ?>
                    <div style="color:#aaa;"><?php echo app_h($tr('لا تملك صلاحية تسجيل تكاليف الخدمات.', 'You do not have permission to record service costs.')); ?></div>
                <?php endif; ?>

                <table class="cost-table">
                    <thead>
                        <tr>
                            <th><?php echo app_h($tr('الخدمة', 'Service')); ?></th>
                            <th><?php echo app_h($tr('المرحلة', 'Stage')); ?></th>
                            <th><?php echo app_h($tr('كمية', 'Qty')); ?></th>
                            <th><?php echo app_h($tr('سعر الوحدة', 'Unit cost')); ?></th>
                            <th><?php echo app_h($tr('التكلفة', 'Cost')); ?></th>
                            <th><?php echo app_h($tr('ملاحظات', 'Notes')); ?></th>
                            <th><?php echo app_h($tr('تحكم', 'Action')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($serviceCostRows)): ?>
                            <tr><td colspan="7" style="color:#777;" data-label="<?php echo app_h($tr('الحالة', 'Status')); ?>"><?php echo app_h($tr('لا توجد تكاليف خدمات مسجلة حتى الآن.', 'No service cost lines recorded yet.')); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($serviceCostRows as $sRow): ?>
                                <tr>
                                    <td data-label="<?php echo app_h($tr('الخدمة', 'Service')); ?>"><?php echo app_h((string)$sRow['service_name']); ?></td>
                                    <td data-label="<?php echo app_h($tr('المرحلة', 'Stage')); ?>"><?php echo app_h((string)($jobStageMap[(string)($sRow['stage_key'] ?? '')] ?? (string)($sRow['stage_key'] ?? '-'))); ?></td>
                                    <td data-label="<?php echo app_h($tr('كمية', 'Qty')); ?>"><?php echo number_format((float)($sRow['qty'] ?? 0), 2); ?></td>
                                    <td data-label="<?php echo app_h($tr('سعر الوحدة', 'Unit cost')); ?>"><?php echo number_format((float)($sRow['unit_cost'] ?? 0), 2); ?></td>
                                    <td data-label="<?php echo app_h($tr('التكلفة', 'Cost')); ?>"><?php echo number_format((float)($sRow['total_cost'] ?? 0), 2); ?></td>
                                    <td data-label="<?php echo app_h($tr('ملاحظات', 'Notes')); ?>"><?php echo app_h((string)($sRow['notes'] ?? '')); ?></td>
                                    <td data-label="<?php echo app_h($tr('تحكم', 'Action')); ?>">
                                        <?php if ($can_manage_costs): ?>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <a href="job_details.php?id=<?php echo (int)$job_id; ?>&edit_service_cost=<?php echo (int)$sRow['id']; ?>" class="md-btn-neutral" style="text-decoration:none; display:inline-flex; align-items:center;"><?php echo app_h($tr('تعديل', 'Edit')); ?></a>
                                            <form method="post" style="margin:0;">
                                                <?php echo app_csrf_input(); ?>
                                                <input type="hidden" name="service_cost_id" value="<?php echo (int)$sRow['id']; ?>">
                                                <button type="submit" name="delete_service_cost" class="md-btn-neutral" onclick="return confirm('<?php echo app_h($tr('حذف هذا البند؟', 'Delete this line?')); ?>')"><?php echo app_h($tr('حذف', 'Delete')); ?></button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <script>
        (function() {
            document.querySelectorAll('.cost-table').forEach((table) => {
                if (table.parentElement && table.parentElement.classList.contains('cost-table-wrap')) return;
                const wrap = document.createElement('div');
                wrap.className = 'cost-table-wrap';
                table.parentNode.insertBefore(wrap, table);
                wrap.appendChild(table);
            });
            const map = <?php echo json_encode($serviceCatalogMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const nameInput = document.getElementById('serviceNameInput');
            const unitCostInput = document.getElementById('serviceUnitCostInput');
            if (!nameInput || !unitCostInput || !map || typeof map !== 'object') return;
            const applyPrice = function() {
                const key = String(nameInput.value || '').trim();
                if (!key) return;
                if (Object.prototype.hasOwnProperty.call(map, key)) {
                    const val = Number(map[key]) || 0;
                    unitCostInput.value = val.toFixed(2);
                }
            };
            nameInput.addEventListener('change', applyPrice);
            nameInput.addEventListener('blur', applyPrice);
        })();
    </script>

</div>

<script>
    (function () {
        const detailsBlocks = document.querySelectorAll('.job-collapsible');
        if (!detailsBlocks.length) return;
        const syncJobCollapsibles = function () {
            const isMobile = window.matchMedia('(max-width: 560px)').matches;
            detailsBlocks.forEach(function (block) {
                if (isMobile) {
                    block.removeAttribute('open');
                } else {
                    block.setAttribute('open', 'open');
                }
            });
        };
        syncJobCollapsibles();
        window.addEventListener('resize', syncJobCollapsibles, { passive: true });
    })();
</script>

<?php include 'footer.php'; ob_end_flush(); ?>
