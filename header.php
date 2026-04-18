<?php
// header.php - (Royal Responsive Header V20.0 - PWA & Navigation)

require_once 'config.php';
app_start_session();
app_handle_lang_switch($conn);

$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'client_review.php', 'view_quote.php', 'print_invoice.php'];

if (!isset($_SESSION['user_id']) && !in_array($current_page, $public_pages)) {
    app_safe_redirect('login.php');
}

if (app_is_saas_gateway() && app_current_tenant_id() <= 0 && $current_page !== 'login.php') {
    app_safe_redirect('login.php');
}

$current_id = $_SESSION['user_id'] ?? 0;
if ($current_id) {
    $current_id = (int)$current_id;
    $userSelect = "id, full_name, role, profile_pic";
    if (app_table_has_column($conn, 'users', 'allow_caps')) {
        $userSelect .= ", allow_caps";
    }
    if (app_table_has_column($conn, 'users', 'deny_caps')) {
        $userSelect .= ", deny_caps";
    }
    $stmt_user = $conn->prepare("SELECT $userSelect FROM users WHERE id = ? LIMIT 1");
    $stmt_user->bind_param("i", $current_id);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if (!$user_data) {
        session_unset();
        session_destroy();
        app_safe_redirect('login.php');
    }

    $avatar = !empty($user_data['profile_pic'])
        ? $user_data['profile_pic'] . '?t=' . time()
        : 'https://ui-avatars.com/api/?name=' . rawurlencode($user_data['full_name']) . '&background=d4af37&color=000';
    $role = $user_data['role'] ?? 'employee';
    $name = $user_data['full_name'] ?? 'User';
    app_set_session_permission_caps($user_data['allow_caps'] ?? '', $user_data['deny_caps'] ?? '');
} else {
    $role = 'guest'; $name = 'Guest'; $avatar = '';
}

$app_name = app_setting_get($conn, 'app_name', 'Arab Eagles');
$app_logo = app_brand_logo_path($conn, 'assets/img/Logo.png');
$theme_color = app_normalize_hex_color(app_setting_get($conn, 'theme_color', '#d4af37'));
$app_ui_theme = app_ui_theme($conn, (int)$current_id);
$app_ui_vars = app_ui_theme_css_vars($app_ui_theme);
$theme_color = (string)($app_ui_vars['--ae-gold'] ?? $theme_color);
$accent_mode = app_setting_get($conn, 'accent_mode', 'adaptive');
if (!in_array($accent_mode, ['adaptive', 'focus', 'minimal'], true)) {
    $accent_mode = 'adaptive';
}
$pricing_enabled = app_setting_get($conn, 'pricing_enabled', '0') === '1';
if ($current_id) {
    $avatar = !empty($user_data['profile_pic'])
        ? $user_data['profile_pic'] . '?t=' . time()
        : 'https://ui-avatars.com/api/?name=' . rawurlencode($user_data['full_name']) . '&background=' . rawurlencode(ltrim($theme_color, '#')) . '&color=000';
}

$name_first = trim(explode(' ', trim((string)$name))[0] ?? 'User');
$name_safe = app_h($name);
$name_first_safe = app_h($name_first !== '' ? $name_first : 'User');
$role_safe = app_h($role);
$avatar_safe = app_h($avatar);
$app_name_safe = app_h($app_name !== '' ? $app_name : 'Arab Eagles');
$app_logo_safe = app_h($app_logo);
$theme_color_safe = app_h($theme_color);
$accent_mode_safe = app_h($accent_mode);
$app_lang = app_current_lang($conn);
$app_dir = app_lang_dir($app_lang);
$is_lang_en = ($app_lang === 'en');
$lang_ar_url = app_lang_switch_url('ar');
$lang_en_url = app_lang_switch_url('en');
$allTranslations = app_translations();
$transMap = isset($allTranslations[$app_lang]) && is_array($allTranslations[$app_lang])
    ? $allTranslations[$app_lang]
    : [];

$t_home = app_t('nav.home', 'الرئيسية');
$t_new_job = app_t('nav.jobs.new', 'أمر شغل');
$t_more = app_t('nav.more', 'المزيد');
$t_master_data = app_t('nav.data.master', 'البيانات الأولية');
$t_users = app_t('nav.users', 'الموظفين');
$t_profile = app_t('nav.profile', 'حسابي');
$t_logout = app_t('nav.logout', 'خروج');
$t_finance = app_t('nav.finance', 'الإدارة المالية');
$t_invoices = app_t('nav.invoices', 'الفواتير');
$t_finance_reports = app_t('nav.finance_reports', 'التقارير المالية');
$t_inventory = app_t('nav.inventory', 'المخزون');
$t_warehouses = app_t('nav.warehouses', 'المخازن');
$t_stock = app_t('nav.stock', 'حركة المخزون');
$t_quotes = app_t('nav.quotes', 'عروض الأسعار');
$t_clients = app_t('nav.clients', 'العملاء');
$t_suppliers = app_t('nav.suppliers', 'الموردين');
$t_customization = app_t('nav.customization', 'التخصيص والصيانة');
$t_backup = app_t('nav.backup', 'النسخ');
$t_install = app_t('nav.install_app', 'تثبيت التطبيق');
$t_license_center = app_t('nav.license_center', 'مركز الترخيص');
$t_license_subscriptions = app_t('nav.license_subscriptions', 'اشتراكات العملاء');
$t_saas_center = app_t('nav.saas_center', 'مركز SaaS');
$t_system_status = app_t('nav.system_status', app_tr('حالة النظام', 'System Status'));
$t_whats_new = app_t('nav.whats_new', app_tr('ما الجديد', "What's New"));
$t_pricing = app_t('nav.pricing', app_tr('تسعير الطباعة', 'Print Pricing'));
$t_menu = app_t('nav.menu', 'القائمة');
$t_lang = app_t('common.lang', 'اللغة');
$t_ar = app_t('common.ar', 'العربية');
$t_en = app_t('common.en', 'English');
$is_admin = (strtolower((string)$role) === 'admin');
$is_super_user = app_is_super_user();
$is_owner_license_hub = (app_license_edition() === 'owner') && $is_super_user;
$is_owner_saas_hub = $is_owner_license_hub && app_is_owner_hub() && app_saas_mode_enabled();
$show_system_status_nav = $is_admin && app_license_edition() !== 'owner';
$can_pricing_view = $is_admin || app_user_can('pricing.view');
$can_pricing_settings = $is_admin || app_user_can('pricing.settings');
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$support_unread_count = 0;
$support_recent_notifications = [];
$support_notify_back = (string)($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
if ($current_user_id > 0) {
    $support_unread_count = app_support_notifications_unread_count($conn, $current_user_id);
    $support_recent_notifications = app_support_notifications_recent($conn, $current_user_id, 8);
}
$can_create_job = app_user_can_any(['jobs.create', 'jobs.manage_all']);
$can_finance_access = app_user_can('finance.view');
$can_finance_tx = app_user_can_any(['finance.transactions.view', 'finance.transactions.create', 'finance.transactions.update', 'finance.transactions.delete']);
$can_invoices = app_user_can('invoices.view');
$can_finance_reports = app_user_can('finance.reports.view');
$can_inventory = app_user_can('inventory.view');
$can_warehouses = app_user_can('inventory.warehouses.view');
$can_stock_adjust = app_user_can('inventory.stock.adjust');
$is_more_active = in_array($current_page, [
    'finance.php',
    'invoices.php',
    'finance_reports.php',
    'inventory.php',
    'warehouses.php',
    'adjust_stock.php',
    'quotes.php',
    'clients.php',
    'suppliers.php',
    'master_data.php',
    'license_center.php',
    'license_subscriptions.php',
    'saas_center.php',
    'cloud_bridge.php',
    'system_status.php',
    'whats_new.php',
], true);
$runtime_i18n = [];
if ($app_lang === 'en') {
    $runtime_i18n = [
        'انتهت صلاحية الجلسة. قم بتحديث الصفحة ثم حاول مرة أخرى.' => 'Session expired. Refresh the page and try again.',
        'صلاحيات الوصول غير كافية' => 'Insufficient access permissions',
        'عذراً، الوصول مخصص للمدير فقط.' => 'Sorry, access is restricted to administrators.',
        'غير مصرح لك بالدخول' => 'You are not authorized to access this section',
        'غير مصرح لك بالدخول إلى لوحة العمليات.' => 'You are not authorized to access the dashboard.',
        'غير مصرح لك بالدخول إلى الإدارة المالية.' => 'You are not authorized to access finance management.',
        'غير مصرح لك بالدخول إلى الفواتير.' => 'You are not authorized to access invoices.',
        'غير مصرح لك بالدخول إلى التقارير المالية.' => 'You are not authorized to access finance reports.',
        'الرئيسية' => 'Home',
        'أمر شغل' => 'Work Order',
        'المزيد' => 'More',
        'القائمة' => 'Menu',
        'اللغة' => 'Language',
        'العربية' => 'Arabic',
        'حسابي' => 'My Profile',
        'خروج' => 'Logout',
        'الموظفين' => 'Users',
        'البيانات الأولية' => 'Master Data',
        'مركز الترخيص' => 'License Center',
        'اشتراكات العملاء' => 'Client Subscriptions',
        'حالة النظام' => 'System Status',
        'التخصيص والصيانة' => 'Customization & Maintenance',
        'النسخ' => 'Backups',
        'الإدارة المالية' => 'Finance Management',
        'الفواتير' => 'Invoices',
        'التقارير المالية' => 'Finance Reports',
        'المخزون' => 'Inventory',
        'المخازن' => 'Warehouses',
        'حركة المخزون' => 'Stock Movement',
        'عروض الأسعار' => 'Quotations',
        'العملاء' => 'Clients',
        'الموردين' => 'Suppliers',
        'تثبيت التطبيق' => 'Install App',
        'تثبيت النظام' => 'Install System',
        'تثبيت نظام' => 'Install',
        'لتجربة أفضل وأسرع، قم بتثبيت النظام كتطبيق على جهازك الآن.' => 'For a faster experience, install the system as an app on your device.',
        'تثبيت الآن' => 'Install now',
        'لاحقاً' => 'Later',
        'تثبيت التطبيق على آيفون' => 'Install app on iPhone',
        'إضافة إلى الشاشة الرئيسية' => 'Add to Home Screen',
        'حفظ' => 'Save',
        'حفظ التغييرات' => 'Save changes',
        'حفظ البيانات' => 'Save data',
        'تحديث البيانات' => 'Update data',
        'تحديث' => 'Update',
        'تعديل' => 'Edit',
        'حذف' => 'Delete',
        'إضافة' => 'Add',
        'إضافة جديد' => 'Add new',
        'إضافة عضو' => 'Add member',
        'إجراءات' => 'Actions',
        'بحث' => 'Search',
        'بحث...' => 'Search...',
        'إعادة تعيين' => 'Reset',
        'تصفية' => 'Filter',
        'رجوع' => 'Back',
        'عودة' => 'Back',
        'إغلاق' => 'Close',
        'إلغاء' => 'Cancel',
        'تأكيد' => 'Confirm',
        'موافق' => 'OK',
        'نعم' => 'Yes',
        'لا' => 'No',
        'اختياري' => 'Optional',
        'مطلوب' => 'Required',
        'عرض' => 'View',
        'معاينة' => 'Preview',
        'اعتماد' => 'Approve',
        'رفض' => 'Reject',
        'تحميل' => 'Download',
        'طباعة' => 'Print',
        'إرسال' => 'Send',
        'تفاصيل' => 'Details',
        'الكل' => 'All',
        'الأرشيف' => 'Archive',
        'أرشفة' => 'Archive',
        'إعادة فتح' => 'Reopen',
        'العمليات الجارية' => 'Active Jobs',
        'المتأخرة' => 'Late Jobs',
        'بانتظار العميل' => 'Waiting for client',
        'مطلوب تعديل' => 'Revision needed',
        'تم الاعتماد' => 'Approved',
        'عملية نشطة' => 'Active Jobs',
        'جاري الاتصال...' => 'Connecting...',
        'تنبيهات الإدارة' => 'Management Alerts',
        'طلب تسعير' => 'Quotation request',
        'كل الأقسام' => 'All departments',
        'العدد' => 'Count',
        'إجمالي النتائج' => 'Total results',
        'السابق' => 'Previous',
        'التالي' => 'Next',
        'الوارد' => 'Incoming',
        'الصادر' => 'Outgoing',
        'نوع الحركة' => 'Transaction type',
        'المبلغ' => 'Amount',
        'تاريخ الحركة' => 'Transaction date',
        'البيان / التفاصيل' => 'Description / details',
        'تأكيد وحفظ العملية' => 'Confirm and save transaction',
        'نوع العملية' => 'Operation type',
        'الوصف' => 'Description',
        'الرصيد' => 'Balance',
        'الرصيد الحالي' => 'Current balance',
        'الرصيد المتاح' => 'Available balance',
        'رقم الفاتورة' => 'Invoice number',
        'إجمالي' => 'Total',
        'الإجمالي' => 'Total',
        'المتبقي' => 'Remaining',
        'المدفوع' => 'Paid',
        'المستحق' => 'Due',
        'الملاحظات' => 'Notes',
        'ملاحظة' => 'Note',
        'إضافة منتج جديد' => 'Add new item',
        'تعديل بيانات المنتج' => 'Edit item',
        'العودة للمخزون' => 'Back to inventory',
        'إضافة مخزن جديد' => 'Add new warehouse',
        'تعديل المخزن' => 'Edit warehouse',
        'العودة إلى قائمة المخازن' => 'Back to warehouses',
        'اسم المنتج' => 'Item name',
        'اسم الصنف' => 'Item name',
        'الكمية' => 'Quantity',
        'الوحدة' => 'Unit',
        'السعر' => 'Price',
        'سعر الوحدة' => 'Unit price',
        'الحد الأدنى' => 'Minimum stock',
        'الحد الأدنى للمخزون' => 'Minimum stock level',
        'الحالة' => 'Status',
        'نشط' => 'Active',
        'غير نشط' => 'Inactive',
        'متاح' => 'Available',
        'غير متاح' => 'Unavailable',
        'تفعيل' => 'Enable',
        'تعطيل' => 'Disable',
        'تمكين' => 'Enable',
        'نوع المستند' => 'Document type',
        'التاريخ' => 'Date',
        'من' => 'From',
        'إلى' => 'To',
        'الاسم' => 'Name',
        'اسم المستخدم' => 'Username',
        'كلمة المرور' => 'Password',
        'البريد الإلكتروني' => 'Email',
        'الهاتف' => 'Phone',
        'الدور' => 'Role',
        'المسؤول' => 'Administrator',
        'مدير' => 'Manager',
        'محاسب' => 'Accountant',
        'موظف' => 'Employee',
        'إضافة مستخدم' => 'Add user',
        'تعديل مستخدم' => 'Edit user',
        'إدارة المستخدمين' => 'Users management',
        'صلاحيات' => 'Permissions',
        'فتح الصلاحيات' => 'Allow permissions',
        'غلق الصلاحيات' => 'Deny permissions',
        'نوع الصلاحية' => 'Permission type',
        'السماح' => 'Allow',
        'منع' => 'Deny',
        'إدارة الفريق المصرح له فقط' => 'Manage authorized team members only',
        'صلاحيات دقيقة للعملية' => 'Fine permissions for job',
        'المستخدم' => 'User',
        'اختر مستخدمًا' => 'Select user',
        'اختر مستخدماً' => 'Select user',
        'دور الإسناد' => 'Assignment role',
        'عضو فريق' => 'Team member',
        'مالك العملية' => 'Job owner',
        'مراجع' => 'Reviewer',
        'مالي' => 'Finance',
        'تأكيد إضافة العضو' => 'Confirm member assignment',
        'اختر مستخدمًا ثم اضغط زر التأكيد.' => 'Select a user then press confirm.',
        'اختر مستخدماً ثم اضغط زر التأكيد.' => 'Select a user then press confirm.',
        'جاهز للتأكيد: اضغط لإضافة العضو المختار.' => 'Ready to confirm: click to add the selected member.',
        'لا يوجد أعضاء مسندون حتى الآن.' => 'No assigned members yet.',
        'إلغاء الإسناد' => 'Unassign',
        'ملف العملية' => 'Job file',
        'تعليق داخلي' => 'Internal comment',
        'إضافة التعليق' => 'Add comment',
        'المرحلة الحالية' => 'Current stage',
        'المرحلة التالية' => 'Next stage',
        'المرحلة السابقة' => 'Previous stage',
        'تغيير المرحلة' => 'Change stage',
        'تراجع للمرحلة السابقة' => 'Return to previous stage',
        'سبب التراجع' => 'Return reason',
        'ملفات المرحلة الحالية' => 'Current stage files',
        'كل ملفات العملية' => 'All job files',
        'لا توجد ملفات لهذه المرحلة حتى الآن.' => 'No files uploaded for this stage yet.',
        'تم تحديث أعضاء العملية بنجاح.' => 'Job members updated successfully.',
        'تم إنهاء وأرشفة العملية بنجاح.' => 'Job archived successfully.',
        'تم إعادة فتح العملية للعمل.' => 'Job reopened successfully.',
        'رابط غير صحيح.' => 'Invalid link.',
        'العملية غير موجودة.' => 'Job not found.',
        'البيانات' => 'Data',
        'لوحة تحكم' => 'Dashboard',
        'مركز التخصيص والصيانة الذكي' => 'Smart customization and maintenance center',
        'العميل' => 'Client',
        'المورد' => 'Supplier',
        'المراجعة' => 'Review',
        'التصميم' => 'Design',
        'الإنتاج' => 'Production',
        'التسليم' => 'Delivery',
        'الحسابات' => 'Accounts',
        'الخامات' => 'Materials',
        'الطباعة' => 'Printing',
        'الكرتون' => 'Carton',
        'البلاستيك' => 'Plastic',
        'السوشيال' => 'Social',
        'المواقع' => 'Web',
        'التصميم فقط' => 'Design only',
        'اليوم' => 'Today',
        'هذا الأسبوع' => 'This week',
        'هذا الشهر' => 'This month',
        'لا توجد بيانات' => 'No data available',
        'لا توجد نتائج' => 'No results found',
        'تم الحفظ بنجاح' => 'Saved successfully',
        'تم التحديث بنجاح' => 'Updated successfully',
        'تم الحذف بنجاح' => 'Deleted successfully',
        'حدث خطأ' => 'An error occurred',
        'خطأ اتصال' => 'Connection error',
        'جاري التحميل...' => 'Loading...',
        'جارٍ التحميل...' => 'Loading...',
        'الرجاء الانتظار...' => 'Please wait...',
        'لا يوجد' => 'None',
        'غير محدد' => 'Not specified',
        'حركة/تحويل' => 'Movement/Transfer',
        'تسجيل حركة مالية' => 'Record financial transaction',
        '💸 صرف راتب / سلفة موظف' => '💸 Payroll / Employee advance',
        '📦 تسجيل فاتورة مشتريات / سداد مورد' => '📦 Purchase invoice / supplier payment',
        'تكرار حركة مالية 🔁' => 'Duplicate financial transaction 🔁',
        'تم نسخ بيانات الحركة السابقة، يرجى مراجعة المبلغ والتاريخ ثم الحفظ.' => 'Previous transaction was copied. Review amount/date then save.',
        'رمز التحقق غير صالح، حدّث الصفحة وحاول مجددًا.' => 'Invalid verification token. Refresh the page and try again.',
        'المبلغ يجب أن يكون أكبر من صفر.' => 'Amount must be greater than zero.',
        'خطأ في الحفظ:' => 'Save error:',
        'مستقر' => 'Stable',
        'تحت الضغط' => 'Under pressure',
        'خطر نقدي' => 'Cash risk',
        'إجمالي القبض (الوارد)' => 'Total incoming receipts',
        'إجمالي الصرف (الصادر)' => 'Total outgoing payments',
        'صافي الخزينة الحالي' => 'Current treasury net',
        'الذكاء المالي المباشر' => 'Live finance insights',
        'الحالة:' => 'Status:',
        'صافي الشهر الحالي' => 'Current month net',
        'مستحقات الرواتب' => 'Payroll due',
        'مستحقات الموردين' => 'Suppliers due',
        'متوقع التحصيل' => 'Expected collection',
        'مدى التغطية النقدية' => 'Cash coverage runway',
        'غير كافٍ للحساب' => 'Insufficient data',
        'يوم' => 'day',
        'تم توزيع المبلغ آلياً على الفواتير القديمة (FIFO).' => 'Amount was auto-allocated to older invoices (FIFO).',
        'تم سداد فواتير المورد القديمة آلياً (FIFO).' => 'Older supplier invoices were auto-settled (FIFO).',
        'تم صرف الرواتب المتأخرة آلياً (FIFO).' => 'Delayed payroll was auto-paid (FIFO).',
        'قبض (إيداع في الخزينة)' => 'Receipt (cash in)',
        'صرف (سحب من الخزينة)' => 'Payment (cash out)',
        'تصنيف المصروف' => 'Expense category',
        'مصروفات عامة / نثرية' => 'General / petty expenses',
        'سداد لمورد (مشتريات)' => 'Supplier payment (purchases)',
        'راتب شهري' => 'Monthly salary',
        'سلفة موظف' => 'Employee advance',
        'المبلغ (EGP)' => 'Amount (EGP)',
        'العميل (مصدر التوريد)' => 'Client (source)',
        '-- اختر العميل (اختياري) --' => '-- Select client (optional) --',
        'المورد / الشركة (جهة الصرف)' => 'Supplier / company (payee)',
        '-- اختر المورد --' => '-- Select supplier --',
        'الموظف المستفيد' => 'Beneficiary employee',
        '-- اختر الموظف --' => '-- Select employee --',
        'تخصيص لراتب محدد (اختياري)' => 'Allocate to specific payroll (optional)',
        '-- 🤖 صرف آلي للأقدم (FIFO) --' => '-- 🤖 Auto-pay oldest first (FIFO) --',
        'ربط بفاتورة محددة (اختياري)' => 'Link to specific invoice (optional)',
        '-- 🤖 سداد آلي للأقدم (FIFO) --' => '-- 🤖 Auto-settle oldest first (FIFO) --',
        'اكتب وصفاً واضحاً للحركة المالية...' => 'Write a clear description for this transaction...',
        'تحديث البيانات 🔄' => 'Update data 🔄',
        'الوارد (قبض)' => 'Incoming (receipt)',
        'الصادر (صرف)' => 'Outgoing (payment)',
        'ابحث في السجل السريع...' => 'Search quick journal...',
        'عام' => 'General',
        'موردين' => 'Suppliers',
        'رواتب' => 'Payroll',
        'سلف' => 'Advances',
        'رقم:' => 'No:',
        'فاتورة #' => 'Invoice #',
        'فاتورة مبيعات #' => 'Sales invoice #',
        'فاتورة مشتريات #' => 'Purchase invoice #',
        'راتب شهر ' => 'Payroll for ',
        '(متبقي:' => '(Remaining:',
        'نسخ بيانات هذه العملية' => 'Duplicate this transaction',
        'تكرار' => 'Duplicate',
        'تنبيه: سيتم حذف العملية وإعادة حساب الفواتير المرتبطة بها. هل أنت متأكد؟' => 'Warning: this will delete the transaction and recalculate linked invoices. Continue?',
        'لا توجد حركات مالية مسجلة حتى الآن' => 'No financial transactions recorded yet',
        'خاص جداً' => 'Highly restricted',
        'تراجع عن العملية' => 'Undo transaction',
        'أمر تشغيل ذكي' => 'Smart Work Order',
        'أمر الشغل' => 'Work Order',
        'إطلاق أمر الشغل' => 'Launch Work Order',
        'البيانات الأساسية' => 'Basic Information',
        'القسم الفني' => 'Technical Section',
        'نوع العملية (القسم)' => 'Operation Type (Department)',
        '-- حدد القسم --' => '-- Select Department --',
        'العميل' => 'Client',
        '-- اختر العميل --' => '-- Select Client --',
        'اسم العملية' => 'Operation Name',
        'تاريخ التسليم' => 'Delivery Date',
        'حالة التصميم' => 'Design Status',
        'يحتاج تصميم (مرحلة أولى)' => 'Design Required (initial stage)',
        'التصميم جاهز (تخطي للتجهيز)' => 'Design Ready (skip to briefing)',
        'فريق العملية' => 'Operation Team',
        'لا يوجد مستخدمون متاحون للإسناد حالياً.' => 'No users available for assignment right now.',
        'عضو فريق' => 'Team Member',
        'مراجع' => 'Reviewer',
        'مصمم' => 'Designer',
        'إنتاج' => 'Production',
        'تأكيد اختيار الأعضاء' => 'Confirm Team Selection',
        'لم يتم اختيار أعضاء بعد.' => 'No members selected yet.',
        'تم تغيير الاختيارات. اضغط "تأكيد اختيار الأعضاء".' => 'Selection changed. Click "Confirm team selection".',
        'تم تأكيد الفريق:' => 'Team confirmed:',
        'يرجى الضغط على زر "تأكيد اختيار الأعضاء" قبل إطلاق أمر الشغل.' => 'Please click "Confirm team selection" before launching the work order.',
        'تفاصيل طلب التصميم' => 'Design Request Details',
        'عدد البنود المطلوبة *' => 'Required Item Count *',
        'نطاق التصميم:' => 'Design Scope:',
        'نطاق إضافي (افصل بفاصلة)' => 'Additional scope (comma-separated)',
        'مخرجات التسليم:' => 'Delivery Outputs:',
        'مخرجات إضافية (افصل بفاصلة)' => 'Additional deliverables (comma-separated)',
        'مواصفات الطباعة' => 'Print Specifications',
        'الكمية المطلوبة (نسخة/فرخ)' => 'Required Quantity (copies/sheets)',
        'العدد المطلوب' => 'Required quantity',
        'نوع الورق' => 'Paper Type',
        '--- أخرى (حدد) ---' => '--- Other (specify) ---',
        'اكتب نوع الورق...' => 'Enter paper type...',
        'الوزن (جرام)' => 'Weight (gsm)',
        'عدد الألوان' => 'Number of Colors',
        'مصدر الورق / المورد' => 'Paper Source / Supplier',
        'اسم المورد أو المخزن' => 'Supplier or warehouse name',
        'الماكينة' => 'Machine',
        'الجهة المطبوعة' => 'Printed Side',
        'اختياري' => 'Optional',
        'مقاس الورق (سم)' => 'Paper Size (cm)',
        'مقاس القص (سم)' => 'Cut Size (cm)',
        'عرض' => 'Width',
        'طول' => 'Length',
        'طريقة الطباعة' => 'Print Mode',
        'وجه واحد' => 'Single Side',
        'وجهين' => 'Double Side',
        'طبع وقلب بنسة' => 'Work-and-turn (gripper)',
        'طبع وقلب ديل' => 'Work-and-turn (tail)',
        'عدد الزنكات' => 'Number of Plates',
        'حالة الزنكات' => 'Plate Status',
        'جديدة' => 'New',
        'مستخدمة' => 'Used',
        'الخامات المطلوبة:' => 'Required Materials:',
        'خامات إضافية (افصل بينها بفاصلة)' => 'Additional materials (comma-separated)',
        'العمليات التكميلية:' => 'Finishing Operations:',
        'خدمات ما بعد الطباعة:' => 'Post-Press Services:',
        'خدمات إضافية (افصل بينها بفاصلة)' => 'Additional services (comma-separated)',
        'تفاصيل ما بعد الطباعة (أماكن البصمة/الكفراج، مقاسات، عدد النسخ...)' => 'Post-press details (emboss/deboss area, sizes, copies...)',
        'مواصفات الكرتون' => 'Carton Specifications',
        'الكمية المطلوبة (علبة)' => 'Required Quantity (boxes)',
        'نوع الورق الخارجي' => 'Outer Paper Type',
        'عدد طبقات الكرتون' => 'Carton Layer Count',
        'نوع الفلوت' => 'Flute Type',
        '-- اختر --' => '-- Select --',
        'تفاصيل الطبقات والأوزان:' => 'Layer and weight details:',
        'اكتب تفاصيل كل طبقة هنا (مثال: E-Flute + كرافت 150جم)' => 'Write each layer detail here (e.g. E-Flute + Kraft 150gsm)',
        'مقاس القص النهائي' => 'Final Cut Size',
        'مقاس العلبة النهائي (طول × عرض × ارتفاع)' => 'Final Box Size (L × W × H)',
        'نوع / رقم الفورمة' => 'Die Type / Number',
        'نوع الغراء أو الإقفال' => 'Glue or Lock Type',
        'خامات الكرتون:' => 'Carton Materials:',
        'التشطيب:' => 'Finishing:',
        'خدمات ما بعد التشغيل:' => 'Post-Operation Services:',
        'تفاصيل التشطيب المطلوبة (مواضع البصمة/الكفراج، عدد نقاط اللصق، تعليمات التجميع...)' => 'Required finishing details (emboss/deboss positions, glue points, assembly notes...)',
        'مواصفات البلاستيك' => 'Plastic Specifications',
        'الكمية المطلوبة (كجم/قطعة)' => 'Required Quantity (kg/piece)',
        'الوزن أو العدد' => 'Weight or quantity',
        'نوع الخامة' => 'Material Type',
        'نوع المنتج' => 'Product Type',
        'السمك (ميكرون)' => 'Thickness (micron)',
        'عرض الفيلم (سم)' => 'Film Width (cm)',
        'المعالجة' => 'Treatment',
        'بدون' => 'None',
        'وجهين' => 'Double Side',
        'طول القص' => 'Cut Length',
        'نوع اللحام / القفل' => 'Sealing / Closure Type',
        'عدد السلندرات' => 'Cylinder Count',
        'حالتها' => 'Status',
        'نوع اليد / الهاندل' => 'Handle Type',
        'خصائص إضافية:' => 'Additional Features:',
        'خصائص إضافية (افصل بينها بفاصلة)' => 'Additional features (comma-separated)',
        'حملة تسويق إلكتروني' => 'Digital Marketing Campaign',
        'الهدف من الحملة' => 'Campaign Goal',
        'الوعي بالعلامة التجارية (Awareness)' => 'Brand Awareness (Awareness)',
        'التفاعل (Engagement)' => 'Engagement (Engagement)',
        'زيارات الموقع (Traffic)' => 'Website Traffic (Traffic)',
        'تجميع بيانات عملاء (Leads)' => 'Lead Generation (Leads)',
        'مبيعات مباشرة (Sales)' => 'Direct Sales (Sales)',
        'تحميل تطبيق (App Promotion)' => 'App Promotion (App)',
        'عدد البوستات/الفيديوهات' => 'Number of posts/videos',
        'المنصات المستهدفة (اختر ما يناسبك):' => 'Target Platforms (choose what fits):',
        'الجمهور المستهدف (باختصار)' => 'Target Audience (brief)',
        'مثال: نساء، مهتمين بالموضة، الرياض...' => 'Example: women, fashion audience, sports...',
        'الميزانية الإعلانية المقترحة (اختياري)' => 'Suggested Ad Budget (optional)',
        'مثال: 5000 ريال' => 'Example: 5000 SAR',
        'أنواع المحتوى:' => 'Content Types:',
        'وتيرة النشر' => 'Publishing Frequency',
        'مثال: 4 منشورات أسبوعياً' => 'Example: 4 posts weekly',
        'مؤشرات القياس KPI' => 'KPI Metrics',
        'تطوير موقع إلكتروني' => 'Website Development',
        'نوع الموقع' => 'Website Type',
        'تعريفي (Corporate)' => 'Corporate Website',
        'متجر إلكتروني (E-Commerce)' => 'E-Commerce Store',
        'تطبيق جوال' => 'Mobile App',
        'النطاق (Domain)' => 'Domain',
        'الاستضافة (Hosting)' => 'Hosting',
        'عدد الصفحات المتوقع' => 'Expected Page Count',
        'مثال: 8' => 'Example: 8',
        'تكاملات مطلوبة' => 'Required Integrations',
        'مزايا الموقع:' => 'Website Features:',
        'الثيم / الشكل المطلوب' => 'Theme / Required Style',
        'بيانات عملية مخصصة' => 'Custom Operation Data',
        'الكمية' => 'Quantity',
        'نطاق التنفيذ' => 'Execution Scope',
        'مثال: تنفيذ حملة ميدانية أو تجهيز فعالية' => 'Example: field campaign or event setup',
        'تفاصيل العملية' => 'Operation Details',
        'أدخل أي تفاصيل مخصصة لهذا النوع من العمليات...' => 'Enter any custom details for this operation type...',
        'مرفقات وملاحظات' => 'Attachments & Notes',
        'ملفات مساعدة (يمكن اختيار أكثر من ملف)' => 'Supporting files (multiple allowed)',
        'ملاحظات عامة' => 'General Notes',
        'أي تفاصيل إضافية...' => 'Any additional details...',
        'الطباعة' => 'Printing',
        'الكرتون' => 'Carton',
        'البلاستيك' => 'Plastic',
        'السوشيال' => 'Social',
        'المواقع' => 'Web',
        'التصميم فقط' => 'Design Only',
        'موديول ديناميكي لنوع العملية الحالي' => 'Dynamic module for the current operation type',
        'المرحلة الحالية' => 'Current Stage',
        'تعليق داخلي' => 'Internal Comment',
        'أضف ملاحظة للفريق...' => 'Add a note for the team...',
        'إضافة التعليق' => 'Add Comment',
        'المرحلة السابقة' => 'Previous Stage',
        'المرحلة التالية' => 'Next Stage',
        'تجاوز إداري للمرحلة' => 'Administrative Stage Override',
        'تغيير المرحلة' => 'Change Stage',
        'رجوع للمرحلة السابقة' => 'Return to Previous Stage',
        'ملفات المرحلة الحالية' => 'Current Stage Files',
        'كل ملفات العملية' => 'All Job Files',
        'لا توجد ملفات مرفوعة لهذه العملية.' => 'No uploaded files for this job.',
        'معلومات العملية' => 'Job Information',
        'ملف العملية' => 'Job File',
        'تفاصيل الطلب' => 'Request Details',
        'مرحلة التجهيز' => 'Briefing Stage',
        'ورشة التصميم' => 'Design Studio',
        'مراجعة العميل' => 'Client Review',
        'تسليم الملفات النهائية' => 'Final File Handover',
        'قسم الحسابات' => 'Accounting Section',
        'مكتملة ومؤرشفة' => 'Completed & Archived',
        'إعادة فتح' => 'Reopen',
        'رفع الملفات' => 'Upload Files',
        'إرسال للمراجعة' => 'Send for Review',
        'إرسال واتساب' => 'Send via WhatsApp',
        'بانتظار الرفع' => 'Waiting for Upload',
        'معتمد' => 'Approved',
        'مرفوض' => 'Rejected',
        'قيد الانتظار' => 'Pending',
        'هناك بنود مرفوضة، يجب إعادتها للتصميم للتعديل.' => 'Some items are rejected and must return to design for revision.',
        'جميع التصاميم معتمدة!' => 'All designs are approved!',
        'بانتظار رد العميل على باقي البنود...' => 'Waiting for client response on remaining items...',
        'العملية لدى الإدارة المالية لإغلاق الحساب.' => 'This operation is currently with finance for account closure.',
        'لا توجد ملفات.' => 'No files.',
        'لا توجد مرفقات حالياً' => 'No attachments currently',
        'لا توجد تفاصيل إضافية' => 'No additional details',
        'لا توجد ملاحظات' => 'No notes',
        'تمرير جبري' => 'Forced Forward',
        'تراجع جبري' => 'Forced Back',
        'طباعة أمر الشغل' => 'Print Work Order',
        'حفظ وبدء التصميم' => 'Save and Start Design',
        'حفظ ورفع التصاميم (بدون إرسال)' => 'Save and Upload Designs (without sending)',
        'اعتماد' => 'Approve',
        'تأكيد' => 'Confirm',
        'مشاركة' => 'Share',
        'ملف مساعد' => 'Supporting File',
        'بروفة' => 'Proof',
        'ملف سلندر' => 'Cylinder File',
        'دفعة مقدمة' => 'Advance Payment',
    ];

    $ar_trans = (isset($allTranslations['ar']) && is_array($allTranslations['ar'])) ? $allTranslations['ar'] : [];
    $en_trans = (isset($allTranslations['en']) && is_array($allTranslations['en'])) ? $allTranslations['en'] : [];
    foreach ($en_trans as $key => $en_value) {
        if (!is_string($en_value) || $en_value === '') {
            continue;
        }
        $ar_value = $ar_trans[$key] ?? null;
        if (!is_string($ar_value) || $ar_value === '' || $ar_value === $en_value) {
            continue;
        }
        if (!array_key_exists($ar_value, $runtime_i18n)) {
            $runtime_i18n[$ar_value] = $en_value;
        }
    }
}

?>
<!DOCTYPE html>
<html dir="<?php echo app_h($app_dir); ?>" lang="<?php echo app_h($app_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo $app_name_safe; ?> ERP</title>
    
    <link rel="manifest" href="manifest.php">
    <meta name="theme-color" content="<?php echo $theme_color_safe; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo $app_name_safe; ?>">
    <link rel="apple-touch-icon" href="assets/img/icon-192x192.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/brand.css">
    <link rel="stylesheet" href="style.css">
    
    <style>
        :root {
            --nav-height: 70px;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --nav-total-height: calc(var(--nav-height) + var(--safe-top));
            --gold-primary: <?php echo $theme_color_safe; ?>;
            --gold-soft: color-mix(in srgb, <?php echo $theme_color_safe; ?> 18%, transparent);
            --gold-border: color-mix(in srgb, <?php echo $theme_color_safe; ?> 36%, transparent);
            --shell-panel: <?php echo app_h($app_ui_vars['--card-bg']); ?>;
            --shell-panel-strong: <?php echo app_h($app_ui_vars['--card-strong']); ?>;
            --bg-main: <?php echo app_h($app_ui_vars['--bg']); ?>;
            --bg-alt: <?php echo app_h($app_ui_vars['--bg-alt']); ?>;
            --text-main: <?php echo app_h($app_ui_vars['--text']); ?>;
            --text-muted: <?php echo app_h($app_ui_vars['--muted']); ?>;
            --line-main: <?php echo app_h($app_ui_vars['--border']); ?>;
            --font-main: <?php echo $app_lang === 'en' ? "'Poppins', 'Cairo', sans-serif" : "'Cairo', sans-serif"; ?>;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            margin: 0;
            font-family: var(--font-main);
            color: var(--text-main);
            background:
                radial-gradient(circle at top right, color-mix(in srgb, <?php echo $theme_color_safe; ?> 12%, transparent), transparent 28%),
                radial-gradient(circle at top left, rgba(255,255,255,0.05), transparent 22%),
                linear-gradient(180deg, var(--bg-alt) 0%, color-mix(in srgb, var(--bg-main) 88%, #000 12%) 18%, var(--bg-main) 100%);
            padding-top: var(--nav-total-height);
            overflow-x: hidden;
        }
        @supports (padding: max(0px)) {
            body {
                padding-top: max(var(--nav-total-height), calc(var(--nav-height) + env(safe-area-inset-top)));
                padding-bottom: max(var(--safe-bottom), env(safe-area-inset-bottom));
            }
        }
        body.no-scroll { overflow: hidden; }
        a { text-decoration: none; }

        button,
        [role="button"],
        .nav-item,
        .nav-group-btn,
        .lang-switch,
        .mobile-menu-btn,
        .hamburger {
            touch-action: manipulation;
        }

        .container { max-width: 1300px; margin: 0 auto; padding: 8px 20px 20px; }
        body > .container,
        body > .royal-container,
        body > .form-container,
        body > .md-wrap { margin-top: 0 !important; }

        .main-navbar {
            position: fixed;
            top: 0;
            inset-inline: 0;
            height: var(--nav-total-height);
            z-index: 3000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--safe-top) 18px 0;
            background:
                linear-gradient(180deg, rgba(8, 10, 12, 0.98), rgba(8, 10, 12, 0.9)),
                linear-gradient(90deg, color-mix(in srgb, <?php echo $theme_color_safe; ?> 5%, transparent), rgba(255,255,255,0.01));
            border-bottom: 1px solid color-mix(in srgb, <?php echo $theme_color_safe; ?> 24%, transparent);
            box-shadow: 0 14px 32px rgba(0,0,0,0.42);
            backdrop-filter: blur(18px) saturate(125%);
            direction: <?php echo app_h($app_dir); ?>;
            overflow: visible;
            isolation: isolate;
        }
        body.brand-shell > .main-navbar { z-index: 6000; }
        .main-navbar::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, color-mix(in srgb, <?php echo $theme_color_safe; ?> 70%, transparent), transparent);
        }
        .main-navbar::after {
            content: '';
            position: absolute;
            inset: auto 14px 9px;
            height: 28px;
            border-radius: 999px;
            background: radial-gradient(circle at center, var(--gold-soft), transparent 70%);
            pointer-events: none;
            filter: blur(16px);
            opacity: 0.9;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 190px;
            color: var(--text-main);
            font-weight: 900;
            padding: 6px 8px 6px 6px;
            border-radius: 18px;
            position: relative;
        }
        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--gold-border);
            box-shadow: 0 0 18px var(--gold-soft);
            background: linear-gradient(145deg, var(--gold-soft), var(--shell-panel-strong));
            flex-shrink: 0;
        }
        .brand-icon img { width: 100%; height: 100%; object-fit: cover; }
        .brand-name {
            color: color-mix(in srgb, var(--gold-primary) 58%, #fff 42%);
            letter-spacing: 0.3px;
            text-shadow: 0 0 10px var(--gold-soft);
            font-size: 1.05rem;
            line-height: 1;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
            padding-inline: 10px;
            overflow: visible;
        }
        .nav-item,
        .nav-group-btn {
            height: 44px;
            padding: 0 15px;
            border-radius: 14px;
            border: 1px solid var(--line-main);
            background: linear-gradient(180deg, color-mix(in srgb, var(--shell-panel) 88%, #fff 4%), color-mix(in srgb, var(--shell-panel-strong) 92%, #fff 2%));
            color: var(--text-main);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
            transition: 0.22s ease;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .nav-item i,
        .nav-group-btn i { color: var(--gold-primary); font-size: 0.95rem; }
        .nav-item:hover,
        .nav-group-btn:hover {
            border-color: var(--gold-border);
            background: linear-gradient(180deg, color-mix(in srgb, var(--gold-primary) 12%, transparent), color-mix(in srgb, var(--gold-primary) 5%, transparent));
            color: var(--text-main);
            transform: translateY(-1px);
        }
        .nav-item.active,
        .nav-group-btn.active {
            border-color: color-mix(in srgb, var(--gold-primary) 62%, transparent);
            background: linear-gradient(140deg, color-mix(in srgb, var(--gold-primary) 22%, transparent), color-mix(in srgb, var(--gold-primary) 10%, transparent));
            color: color-mix(in srgb, var(--gold-primary) 50%, #fff 50%);
            box-shadow: 0 0 0 1px color-mix(in srgb, var(--gold-primary) 20%, transparent) inset;
        }
        .btn-new-job {
            background: linear-gradient(130deg, color-mix(in srgb, var(--gold-primary) 92%, #fff 8%), color-mix(in srgb, var(--gold-primary) 82%, #6a4600 18%));
            border-color: color-mix(in srgb, var(--gold-primary) 90%, transparent);
            color: #111 !important;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--gold-primary) 28%, transparent);
        }
        .btn-new-job i { color: #111; }

        .nav-group {
            position: relative;
            z-index: 3200;
        }
        .nav-group.open { z-index: 5005; }
        .nav-group-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            min-width: 250px;
            max-height: min(70vh, 520px);
            overflow: auto;
            padding: 8px;
            background: linear-gradient(180deg, color-mix(in srgb, var(--shell-panel) 98%, #000 2%), color-mix(in srgb, var(--shell-panel-strong) 98%, #000 2%));
            border: 1px solid color-mix(in srgb, var(--gold-primary) 18%, transparent);
            border-radius: 18px;
            box-shadow: 0 22px 46px rgba(0,0,0,0.56);
            backdrop-filter: blur(18px);
            right: 0;
            left: auto;
            z-index: 5006;
        }
        html[dir="rtl"] .nav-group-menu {
            right: auto;
            left: 0;
        }
        .nav-group.open .nav-group-menu { display: block; }
        .nav-group-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 12px;
            border-radius: 10px;
            color: color-mix(in srgb, var(--text-main) 88%, transparent);
            font-size: 0.9rem;
            border: 1px solid transparent;
            margin-bottom: 4px;
        }
        .nav-group-link:hover {
            border-color: var(--gold-border);
            background: var(--gold-soft);
            color: var(--text-main);
        }
        .nav-group-link.active {
            border-color: color-mix(in srgb, var(--gold-primary) 58%, transparent);
            background: color-mix(in srgb, var(--gold-primary) 15%, transparent);
            color: var(--gold-primary);
        }

        .nav-tools {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 320px;
            justify-content: flex-end;
        }
        .shell-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            height: 42px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--gold-primary) 28%, transparent);
            background: linear-gradient(180deg, color-mix(in srgb, var(--shell-panel) 88%, #fff 4%), color-mix(in srgb, var(--shell-panel-strong) 92%, #fff 2%));
            color: var(--text-main);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .shell-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #54d48a;
            box-shadow: 0 0 0 4px rgba(84,212,138,0.12), 0 0 12px rgba(84,212,138,0.5);
            flex-shrink: 0;
        }
        .shell-status-copy {
            display: inline-flex;
            flex-direction: column;
            line-height: 1.05;
        }
        .shell-status-label {
            font-size: 0.66rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .shell-status-value {
            font-size: 0.82rem;
            color: color-mix(in srgb, var(--gold-primary) 45%, #fff 55%);
            font-weight: 800;
            white-space: nowrap;
        }

        .lang-switch-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            padding: 0 10px;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--gold-primary) 42%, transparent);
            background: linear-gradient(180deg, color-mix(in srgb, var(--shell-panel) 88%, #fff 4%), color-mix(in srgb, var(--shell-panel-strong) 92%, #fff 2%));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .lang-pill-label {
            font-size: 0.78rem;
            font-weight: 800;
            color: #dedede;
        }
        .lang-switch { position: relative; width: 42px; height: 24px; display: inline-flex; }
        .lang-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .lang-slider {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            border: 1px solid #4a4a4a;
            background: #2a2a2a;
            cursor: pointer;
            transition: 0.2s;
        }
        .lang-slider::before {
            content: '';
            position: absolute;
            top: 2px;
            inset-inline-start: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #afafaf;
            transition: 0.2s;
        }
        .lang-switch input:checked + .lang-slider {
            border-color: rgba(212,175,55,0.84);
            background: rgba(212,175,55,0.22);
        }
        .lang-switch input:checked + .lang-slider::before {
            background: var(--gold-primary);
            transform: translateX(18px);
        }
        html[dir="rtl"] .lang-switch input:checked + .lang-slider::before { transform: translateX(-18px); }

        #installAppBtn {
            display: none;
            height: 42px;
            border: 1px solid rgba(46, 204, 113, 0.56);
            border-radius: 12px;
            padding: 0 12px;
            background: rgba(46,204,113,0.16);
            color: #d4ffd6;
            font-weight: 700;
            cursor: pointer;
            align-items: center;
            gap: 6px;
        }

        .support-bell {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .support-bell-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid color-mix(in srgb, var(--gold-primary) 42%, transparent);
            background: color-mix(in srgb, var(--shell-panel) 92%, #fff 3%);
            color: var(--text-main);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .support-bell.open .support-bell-btn,
        .support-bell-btn:hover {
            border-color: var(--gold-border);
            color: var(--gold-primary);
            background: color-mix(in srgb, var(--gold-primary) 10%, transparent);
        }
        .support-bell-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #d54545;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 800;
            border: 1px solid rgba(255,255,255,0.25);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .support-bell-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            left: auto;
            width: min(360px, 92vw);
            max-height: min(70vh, 520px);
            overflow: auto;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--gold-primary) 18%, transparent);
            background: linear-gradient(180deg, color-mix(in srgb, var(--shell-panel) 98%, #000 2%), color-mix(in srgb, var(--shell-panel-strong) 98%, #000 2%));
            box-shadow: 0 20px 40px rgba(0,0,0,0.58);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: 0.2s;
            z-index: 5006;
            padding: 10px;
            backdrop-filter: blur(18px);
        }
        html[dir="rtl"] .support-bell-menu {
            right: auto;
            left: 0;
        }
        .support-bell.open .support-bell-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .support-bell-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }
        .support-bell-title {
            color: var(--gold-primary);
            font-weight: 800;
            font-size: 0.9rem;
        }
        .support-bell-markall {
            border: 1px solid color-mix(in srgb, var(--line-main) 75%, #444 25%);
            border-radius: 9px;
            background: color-mix(in srgb, var(--shell-panel) 92%, #1d1d1d 8%);
            color: color-mix(in srgb, var(--text-main) 88%, transparent);
            font-size: 0.75rem;
            padding: 5px 8px;
            cursor: pointer;
        }
        .support-note {
            border: 1px solid color-mix(in srgb, var(--line-main) 70%, #2d2d2d 30%);
            background: color-mix(in srgb, var(--shell-panel-strong) 92%, #111216 8%);
            border-radius: 10px;
            padding: 8px;
            margin-bottom: 7px;
        }
        .support-note.is-unread {
            border-color: color-mix(in srgb, var(--gold-primary) 50%, transparent);
            background: var(--gold-soft);
        }
        .support-note-main {
            display: block;
            color: color-mix(in srgb, var(--text-main) 92%, transparent);
            text-decoration: none;
            margin-bottom: 6px;
        }
        .support-note-main:hover .support-note-subject {
            color: var(--gold-primary);
        }
        .support-note-subject {
            font-size: 0.84rem;
            font-weight: 800;
            line-height: 1.35;
            margin-bottom: 4px;
        }
        .support-note-body {
            font-size: 0.78rem;
            color: color-mix(in srgb, var(--text-main) 72%, transparent);
            line-height: 1.45;
            margin-bottom: 4px;
        }
        .support-note-time {
            font-size: 0.72rem;
            color: var(--text-muted);
        }
        .support-note-action {
            display: flex;
            justify-content: flex-end;
        }
        .support-note-read-btn {
            border: 1px solid color-mix(in srgb, var(--line-main) 70%, #3a3a3a 30%);
            background: color-mix(in srgb, var(--shell-panel) 92%, #171717 8%);
            color: color-mix(in srgb, var(--text-main) 82%, transparent);
            border-radius: 8px;
            padding: 4px 7px;
            font-size: 0.72rem;
            cursor: pointer;
        }
        .support-bell-empty {
            border: 1px dashed color-mix(in srgb, var(--line-main) 70%, #303030 30%);
            border-radius: 10px;
            color: var(--text-muted);
            padding: 10px;
            text-align: center;
            font-size: 0.82rem;
        }
        .support-bell-footer {
            display: block;
            margin-top: 8px;
            border: 1px solid color-mix(in srgb, var(--line-main) 70%, #3c3c3c 30%);
            border-radius: 10px;
            padding: 8px;
            text-decoration: none;
            color: color-mix(in srgb, var(--text-main) 92%, transparent);
            text-align: center;
            font-weight: 700;
            font-size: 0.82rem;
        }
        .support-bell-footer:hover {
            color: var(--gold-primary);
            border-color: var(--gold-border);
            background: var(--gold-soft);
        }

        .user-profile {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            padding: 3px 5px 3px 8px;
            border: 1px solid color-mix(in srgb, var(--gold-primary) 28%, transparent);
            border-radius: 999px;
            cursor: pointer;
            background: linear-gradient(180deg, color-mix(in srgb, var(--shell-panel) 88%, #fff 4%), color-mix(in srgb, var(--shell-panel-strong) 92%, #fff 2%));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .user-profile.open { z-index: 5005; }
        .avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid color-mix(in srgb, var(--gold-primary) 80%, transparent);
            object-fit: cover;
        }
        .user-info { text-align: start; }
        .u-name { display: block; font-size: 0.82rem; font-weight: 800; color: var(--text-main); line-height: 1.15; }
        .u-role { display: block; font-size: 0.66rem; color: var(--gold-primary); font-weight: 700; }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            left: auto;
            width: 220px;
            padding: 8px;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--gold-primary) 18%, transparent);
            background: linear-gradient(180deg, color-mix(in srgb, var(--shell-panel) 98%, #000 2%), color-mix(in srgb, var(--shell-panel-strong) 98%, #000 2%));
            box-shadow: 0 20px 40px rgba(0,0,0,0.58);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: 0.2s;
            z-index: 5006;
            backdrop-filter: blur(18px);
        }
        html[dir="rtl"] .dropdown-menu {
            right: auto;
            left: 0;
        }
        .user-profile:hover .dropdown-menu,
        .user-profile.open .dropdown-menu,
        .user-profile:focus-within .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .dd-item {
            display: flex;
            align-items: center;
            gap: 9px;
            border-radius: 9px;
            padding: 10px 12px;
            color: color-mix(in srgb, var(--text-main) 84%, transparent);
            font-size: 0.88rem;
            border: 1px solid transparent;
            margin-bottom: 4px;
        }
        .dd-item:hover {
            border-color: var(--gold-border);
            background: var(--gold-soft);
            color: var(--text-main);
        }

        .hamburger {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--gold-border);
            background: color-mix(in srgb, var(--gold-primary) 6%, transparent);
            color: var(--gold-primary);
            font-size: 1rem;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .mobile-overlay {
            position: fixed;
            top: var(--nav-total-height);
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.68);
            backdrop-filter: blur(3px);
            z-index: 3001;
            opacity: 0;
            visibility: hidden;
            transition: 0.2s;
        }
        body.brand-shell > .mobile-overlay { z-index: 6001; }
        .mobile-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .mobile-sidebar {
            position: fixed;
            top: var(--nav-total-height);
            bottom: 0;
            width: min(360px, 88vw);
            background:
                radial-gradient(circle at top, rgba(212,175,55,0.1), transparent 32%),
                linear-gradient(180deg, #121212 0%, #0b0b0b 100%);
            border-inline-start: 1px solid rgba(212, 175, 55, 0.28);
            z-index: 3002;
            overflow-y: auto;
            padding: 16px 14px 18px;
            transition: transform 0.25s ease;
            transform: translateX(110%);
            right: 0;
            left: auto;
            box-shadow: 0 24px 48px rgba(0,0,0,0.55);
        }
        body.brand-shell > .mobile-sidebar { z-index: 6002; }
        html[dir="rtl"] .mobile-sidebar {
            right: auto;
            left: 0;
            border-inline-start: none;
            border-inline-end: 1px solid rgba(212, 175, 55, 0.28);
            transform: translateX(-110%);
        }
        .mobile-sidebar.open { transform: translateX(0); }
        html[dir="rtl"] .mobile-sidebar.open { transform: translateX(0); }

        .m-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 1px solid #262626;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .m-header .brand-logo {
            min-width: 0;
            gap: 10px;
            font-size: 1.85rem;
            font-weight: 900;
            color: #fff;
        }
        .close-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid #3e3e3e;
            color: #dfdfdf;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: #171717;
        }
        .m-link {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            padding: 12px 13px;
            border-radius: 11px;
            border: 1px solid #2f2f2f;
            background: #171717;
            color: #dddddd;
            margin-bottom: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            transition: 0.2s;
        }
        .m-link i {
            width: 20px;
            text-align: center;
            color: var(--gold-primary);
        }
        .m-link:hover {
            border-color: rgba(212,175,55,0.48);
            background: rgba(212,175,55,0.08);
            color: #fff;
        }
        .m-link.active {
            border-color: rgba(212,175,55,0.66);
            background: rgba(212,175,55,0.12);
            color: #ffefbf;
        }
        .m-link-cta {
            border-color: rgba(212,175,55,0.66);
            background: rgba(212,175,55,0.12);
        }

        .mobile-profile-card {
            text-align: center;
            margin-bottom: 16px;
            border: 1px dashed #333;
            border-radius: 14px;
            padding: 14px 10px 12px;
            background: rgba(255, 255, 255, 0.01);
        }
        .mobile-profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 2px solid var(--gold-primary);
            margin: 0 auto 10px;
            object-fit: cover;
            box-shadow: 0 10px 24px rgba(212,175,55,0.15);
        }
        .mobile-profile-name {
            color: #fff;
            font-weight: 800;
            line-height: 1.2;
        }
        .mobile-profile-role {
            color: var(--gold-primary);
            font-size: 0.78rem;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .lang-mobile-switch {
            justify-content: space-between;
            gap: 10px;
        }
        .lang-mobile-switch .lang-mobile-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            font-weight: 700;
        }
        #installAppBtnMobile {
            width: 100%;
            border: none;
            background: #2ecc71;
            color: #fff;
            display: none;
            justify-content: flex-start;
            text-align: start;
        }
        #installAppBtnMobile i { color: #fff; }
        .mobile-logout {
            margin-top: 12px;
            color: #ffb8b8;
        }
        .mobile-logout i { color: #ff7a7a; }

        .pwa-modal {
            display: none;
            position: fixed;
            bottom: 0;
            inset-inline: 0;
            background: #1a1a1a;
            border-top: 3px solid var(--gold-primary);
            padding: 16px;
            z-index: 3500;
        }
        body.brand-shell > .pwa-modal { z-index: 6500; }
        .pwa-content { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 980px; margin: 0 auto; flex-wrap: wrap; }
        .pwa-text h4 { margin: 0; color: #fff; }
        .pwa-text p { margin: 4px 0 0; color: #b2b2b2; font-size: 0.87rem; }
        .pwa-actions { display: flex; gap: 8px; }
        .pwa-btn-install, .pwa-btn-close {
            border-radius: 8px;
            border: 1px solid #444;
            background: #202020;
            color: #fff;
            padding: 8px 14px;
            cursor: pointer;
            font-family: var(--font-main);
        }
        .pwa-btn-install {
            border-color: rgba(212,175,55,0.72);
            background: rgba(212,175,55,0.2);
            color: #f7e2a0;
        }

        .ios-prompt {
            display: none;
            position: fixed;
            bottom: 18px;
            left: 50%;
            transform: translateX(-50%);
            width: min(420px, 90vw);
            border-radius: 12px;
            border: 1px solid #3e3e3e;
            background: #1f1f1f;
            z-index: 3500;
            padding: 14px;
        }
        body.brand-shell > .ios-prompt { z-index: 6500; }

        @media (max-width: 1200px) {
            .brand-name { font-size: 0.98rem; }
            .nav-item, .nav-group-btn { padding: 0 12px; }
            .nav-tools { min-width: 180px; }
            .shell-status { display: none; }
        }

        @media (min-width: 1025px) {
            .mobile-overlay,
            .mobile-sidebar {
                display: none !important;
            }
        }

        @media (max-width: 1024px) {
            .main-navbar {
                padding: var(--safe-top) 12px 0;
                backdrop-filter: none;
                box-shadow: 0 6px 16px rgba(0,0,0,0.35);
            }
            .brand-logo {
                min-width: auto;
            }
            .nav-links { display: none; }
            .lang-switch-wrap.desktop-only { display: none !important; }
            .shell-status { display: none !important; }
            .user-profile .user-info { display: none; }
            .user-profile .dropdown-menu { display: none !important; }
            .hamburger { display: inline-flex; }
            .nav-tools { min-width: auto; gap: 8px; }
            #installAppBtn { display: none !important; }
            .mobile-overlay { backdrop-filter: none; }
            .mobile-sidebar { box-shadow: 0 12px 24px rgba(0,0,0,0.45); }
            .support-bell-menu,
            .dropdown-menu,
            .m-link,
            .mobile-sidebar {
                transition: none !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body class="brand-shell" data-accent-mode="<?php echo $accent_mode_safe; ?>">

<?php if(isset($_SESSION['user_id'])): ?>

    <nav class="main-navbar">
        <a href="dashboard.php" class="brand-logo">
            <div class="brand-icon"><img src="<?php echo $app_logo_safe; ?>" alt="logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fa-solid fa-eagle\'></i>';"></div>
            <span class="brand-name"><?php echo $app_name_safe; ?></span>
        </a>

        <div class="nav-links">
            <a href="dashboard.php" class="nav-item <?php echo $current_page=='dashboard.php'?'active':''; ?>" aria-label="<?php echo app_h($t_home); ?>"><i class="fa-solid fa-house"></i> <?php echo app_h($t_home); ?></a>
            <?php if($can_create_job): ?><a href="add_job.php" class="nav-item btn-new-job" aria-label="<?php echo app_h($t_new_job); ?>"><i class="fa-solid fa-plus"></i> <?php echo app_h($t_new_job); ?></a><?php endif; ?>
            <div class="nav-group">
                <button type="button" class="nav-group-btn <?php echo $is_more_active ? 'active' : ''; ?>" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-table-cells-large"></i> <?php echo app_h($t_more); ?></button>
                <div class="nav-group-menu">
                    <?php if($can_finance_access || $can_finance_tx): ?>
                        <a href="finance.php" class="nav-group-link <?php echo $current_page==='finance.php'?'active':''; ?>"><i class="fa-solid fa-coins"></i> <?php echo app_h($t_finance); ?></a>
                    <?php endif; ?>
                    <?php if($can_invoices): ?>
                        <a href="invoices.php?tab=sales" class="nav-group-link <?php echo $current_page==='invoices.php'?'active':''; ?>"><i class="fa-solid fa-file-invoice"></i> <?php echo app_h($t_invoices); ?></a>
                    <?php endif; ?>
                    <?php if($can_finance_reports): ?>
                        <a href="finance_reports.php" class="nav-group-link <?php echo $current_page==='finance_reports.php'?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> <?php echo app_h($t_finance_reports); ?></a>
                    <?php endif; ?>
                    <?php if($can_inventory): ?>
                        <a href="inventory.php" class="nav-group-link <?php echo $current_page==='inventory.php'?'active':''; ?>"><i class="fa-solid fa-boxes-stacked"></i> <?php echo app_h($t_inventory); ?></a>
                    <?php endif; ?>
                    <?php if($can_warehouses): ?>
                        <a href="warehouses.php" class="nav-group-link <?php echo $current_page==='warehouses.php'?'active':''; ?>"><i class="fa-solid fa-warehouse"></i> <?php echo app_h($t_warehouses); ?></a>
                    <?php endif; ?>
                    <?php if($can_stock_adjust): ?>
                        <a href="adjust_stock.php" class="nav-group-link <?php echo $current_page==='adjust_stock.php'?'active':''; ?>"><i class="fa-solid fa-right-left"></i> <?php echo app_h($t_stock); ?></a>
                    <?php endif; ?>
                    <?php if(in_array($role, ['admin', 'manager', 'sales', 'accountant'])): ?><a href="quotes.php" class="nav-group-link <?php echo $current_page==='quotes.php'?'active':''; ?>"><i class="fa-solid fa-file-contract"></i> <?php echo app_h($t_quotes); ?></a><?php endif; ?>
                    <?php if(in_array($role, ['admin', 'sales', 'manager', 'purchasing'])): ?>
                        <a href="clients.php" class="nav-group-link <?php echo $current_page==='clients.php'?'active':''; ?>"><i class="fa-solid fa-users"></i> <?php echo app_h($t_clients); ?></a>
                        <a href="suppliers.php" class="nav-group-link <?php echo $current_page==='suppliers.php'?'active':''; ?>"><i class="fa-solid fa-truck-field"></i> <?php echo app_h($t_suppliers); ?></a>
                    <?php endif; ?>
                    <?php if($can_pricing_view || $can_pricing_settings): ?>
                        <?php $pricing_link = $pricing_enabled && $can_pricing_view ? 'pricing_module.php' : 'master_data.php?tab=pricing'; ?>
                        <a href="<?php echo $pricing_link; ?>" class="nav-group-link <?php echo $current_page==='pricing_module.php'?'active':''; ?>">
                            <i class="fa-solid fa-calculator"></i> <?php echo app_h($t_pricing); ?>
                            <?php if (!$pricing_enabled && $can_pricing_settings): ?><span style="margin-inline-start:6px;color:#c9c9c9;font-size:0.8rem;"><?php echo app_h($is_lang_en ? '(disabled)' : '(غير مفعل)'); ?></span><?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <?php if($is_admin): ?>
                        <a href="master_data.php" class="nav-group-link <?php echo $current_page==='master_data.php'?'active':''; ?>"><i class="fa-solid fa-database"></i> <?php echo app_h($t_master_data); ?></a>
                        <?php if($show_system_status_nav): ?><a href="system_status.php" class="nav-group-link <?php echo in_array($current_page,['system_status.php','cloud_bridge.php'],true)?'active':''; ?>"><i class="fa-solid fa-shield-halved"></i> <?php echo app_h($t_system_status); ?></a><?php endif; ?>
                        <a href="whats_new.php" class="nav-group-link <?php echo $current_page==='whats_new.php'?'active':''; ?>"><i class="fa-solid fa-sparkles"></i> <?php echo app_h($t_whats_new); ?></a>
                        <?php if($is_super_user): ?><a href="license_center.php" class="nav-group-link <?php echo $current_page==='license_center.php'?'active':''; ?>"><i class="fa-solid fa-key"></i> <?php echo app_h($t_license_center); ?></a><?php endif; ?>
                        <?php if($is_owner_license_hub): ?><a href="license_subscriptions.php" class="nav-group-link <?php echo $current_page==='license_subscriptions.php'?'active':''; ?>"><i class="fa-solid fa-id-card-clip"></i> <?php echo app_h($t_license_subscriptions); ?></a><?php endif; ?>
                        <?php if($is_owner_saas_hub): ?><a href="saas_center.php" class="nav-group-link <?php echo $current_page==='saas_center.php'?'active':''; ?>"><i class="fa-solid fa-building-shield"></i> <?php echo app_h($t_saas_center); ?></a><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="nav-tools">
            <div class="shell-status desktop-only" aria-hidden="true">
                <span class="shell-status-dot"></span>
                <span class="shell-status-copy">
                    <span class="shell-status-label"><?php echo app_h(app_tr('حالة النظام', 'System Status')); ?></span>
                    <span class="shell-status-value"><?php echo app_h(app_tr('متصل', 'Connected')); ?></span>
                </span>
            </div>
            <div class="lang-switch-wrap desktop-only" title="<?php echo app_h($t_lang); ?>">
                <span class="lang-pill-label">AR</span>
                <label class="lang-switch">
                    <input
                        type="checkbox"
                        id="langToggleDesktop"
                        data-ar-url="<?php echo app_h($lang_ar_url); ?>"
                        data-en-url="<?php echo app_h($lang_en_url); ?>"
                        <?php echo $is_lang_en ? 'checked' : ''; ?>
                    >
                    <span class="lang-slider"></span>
                </label>
                <span class="lang-pill-label">EN</span>
            </div>
            <button id="installAppBtn" onclick="installPWA()"><i class="fa-solid fa-download"></i> <?php echo app_h($t_install); ?></button>

            <div class="support-bell" id="supportBellRoot">
                <button type="button" class="support-bell-btn" id="supportBellBtn" aria-haspopup="true" aria-expanded="false" title="<?php echo app_h(app_tr('إشعارات الدعم', 'Support notifications')); ?>">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($support_unread_count > 0): ?>
                        <span class="support-bell-badge"><?php echo (int)min(99, $support_unread_count); ?></span>
                    <?php endif; ?>
                </button>
                <div class="support-bell-menu" id="supportBellMenu">
                    <div class="support-bell-head">
                        <span class="support-bell-title"><?php echo app_h(app_tr('إشعارات الدعم', 'Support Notifications')); ?></span>
                        <?php if ($support_unread_count > 0): ?>
                            <form method="post" action="support_notifications.php">
                                <input type="hidden" name="_csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
                                <input type="hidden" name="action" value="mark_all">
                                <input type="hidden" name="back" value="<?php echo app_h($support_notify_back); ?>">
                                <button type="submit" class="support-bell-markall"><?php echo app_h(app_tr('تعليم الكل كمقروء', 'Mark all read')); ?></button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($support_recent_notifications)): ?>
                        <div class="support-bell-empty"><?php echo app_h(app_tr('لا توجد إشعارات حالياً.', 'No notifications yet.')); ?></div>
                    <?php else: ?>
                        <?php foreach ($support_recent_notifications as $sn): ?>
                            <?php
                                $noteId = (int)($sn['id'] ?? 0);
                                $ticketId = (int)($sn['ticket_id'] ?? 0);
                                $noteRead = (int)($sn['is_read'] ?? 0) === 1;
                                $noteLink = $ticketId > 0 ? ('license_center.php?ticket=' . $ticketId) : 'license_center.php';
                                $noteTime = trim((string)($sn['created_at'] ?? ''));
                            ?>
                            <div class="support-note <?php echo $noteRead ? '' : 'is-unread'; ?>">
                                <a href="<?php echo app_h($noteLink); ?>" class="support-note-main">
                                    <div class="support-note-subject"><?php echo app_h((string)($sn['title'] ?? '')); ?></div>
                                    <div class="support-note-body"><?php echo app_h((string)($sn['message'] ?? '')); ?></div>
                                    <div class="support-note-time"><?php echo app_h($noteTime); ?></div>
                                </a>
                                <?php if (!$noteRead && $noteId > 0): ?>
                                    <div class="support-note-action">
                                        <form method="post" action="support_notifications.php">
                                            <input type="hidden" name="_csrf_token" value="<?php echo app_h(app_csrf_token()); ?>">
                                            <input type="hidden" name="action" value="mark_one">
                                            <input type="hidden" name="notification_id" value="<?php echo $noteId; ?>">
                                            <input type="hidden" name="back" value="<?php echo app_h($support_notify_back); ?>">
                                            <button type="submit" class="support-note-read-btn"><?php echo app_h(app_tr('قراءة', 'Read')); ?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <a href="license_center.php" class="support-bell-footer"><?php echo app_h($is_owner_license_hub ? app_tr('فتح مركز العمليات والدعم', 'Open Operations & Support Center') : app_tr('فتح مركز الترخيص والدعم', 'Open License & Support Center')); ?></a>
                </div>
            </div>
            
            <div class="user-profile">
                <div class="user-info"><span class="u-name"><?php echo $name_first_safe; ?></span><span class="u-role"><?php echo $role_safe; ?></span></div>
                <img src="<?php echo $avatar_safe; ?>" class="avatar-circle">
                <div class="dropdown-menu">
                    <a href="profile.php" class="dd-item"><i class="fa-solid fa-user"></i> <?php echo app_h($t_profile); ?></a>
                    <?php if($is_admin): ?>
                        <a href="users.php" class="dd-item"><i class="fa-solid fa-users-gear"></i> <?php echo app_h($t_users); ?></a>
                        <a href="master_data.php" class="dd-item"><i class="fa-solid fa-database"></i> <?php echo app_h($t_master_data); ?></a>
                        <?php if($show_system_status_nav): ?><a href="system_status.php" class="dd-item"><i class="fa-solid fa-shield-halved"></i> <?php echo app_h($t_system_status); ?></a><?php endif; ?>
                        <a href="whats_new.php" class="dd-item"><i class="fa-solid fa-sparkles"></i> <?php echo app_h($t_whats_new); ?></a>
                        <?php if($is_super_user): ?><a href="license_center.php" class="dd-item"><i class="fa-solid fa-key"></i> <?php echo app_h($t_license_center); ?></a><?php endif; ?>
                        <?php if($is_owner_license_hub): ?><a href="license_subscriptions.php" class="dd-item"><i class="fa-solid fa-id-card-clip"></i> <?php echo app_h($t_license_subscriptions); ?></a><?php endif; ?>
                        <?php if($is_owner_saas_hub): ?><a href="saas_center.php" class="dd-item"><i class="fa-solid fa-building-shield"></i> <?php echo app_h($t_saas_center); ?></a><?php endif; ?>
                        <a href="system_upgrade.php" class="dd-item"><i class="fa-solid fa-sliders"></i> <?php echo app_h($t_customization); ?></a>
                        <a href="backup.php" class="dd-item"><i class="fa-solid fa-database"></i> <?php echo app_h($t_backup); ?></a>
                    <?php endif; ?>
                    <a href="logout.php" class="dd-item"><i class="fa-solid fa-power-off"></i> <?php echo app_h($t_logout); ?></a>
                </div>
            </div>
            <div class="hamburger" onclick="toggleMenu()"><i class="fa-solid fa-bars-staggered"></i></div>
        </div>
    </nav>

    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMenu()"></div>
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="m-header">
            <div class="brand-logo"><div class="brand-icon"><img src="<?php echo $app_logo_safe; ?>" alt="logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fa-solid fa-eagle\'></i>';"></div> <?php echo app_h($t_menu); ?></div>
            <div class="close-btn" onclick="toggleMenu()">×</div>
        </div>
        <div class="mobile-profile-card">
            <img src="<?php echo $avatar_safe; ?>" class="mobile-profile-avatar">
            <div class="mobile-profile-name"><?php echo $name_safe; ?></div>
            <div class="mobile-profile-role"><?php echo $role_safe; ?></div>
        </div>

        <a href="dashboard.php" class="m-link <?php echo $current_page=='dashboard.php'?'active':''; ?>"><i class="fa-solid fa-house"></i> <?php echo app_h($t_home); ?></a>
        <?php if($can_create_job): ?><a href="add_job.php" class="m-link m-link-cta <?php echo $current_page==='add_job.php'?'active':''; ?>"><i class="fa-solid fa-plus"></i> <?php echo app_h($t_new_job); ?></a><?php endif; ?>
        <?php if(in_array($role, ['admin', 'manager', 'sales'])): ?><a href="quotes.php" class="m-link <?php echo $current_page==='quotes.php'?'active':''; ?>"><i class="fa-solid fa-file-contract"></i> <?php echo app_h($t_quotes); ?></a><?php endif; ?>
        <?php if($can_finance_access || $can_finance_tx): ?>
            <a href="finance.php" class="m-link <?php echo $current_page==='finance.php'?'active':''; ?>"><i class="fa-solid fa-coins"></i> <?php echo app_h($t_finance); ?></a>
        <?php endif; ?>
        <?php if($can_invoices): ?>
            <a href="invoices.php?tab=sales" class="m-link <?php echo $current_page==='invoices.php'?'active':''; ?>"><i class="fa-solid fa-file-invoice"></i> <?php echo app_h($t_invoices); ?></a>
        <?php endif; ?>
        <?php if($can_finance_reports): ?>
            <a href="finance_reports.php" class="m-link <?php echo $current_page==='finance_reports.php'?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> <?php echo app_h($t_finance_reports); ?></a>
        <?php endif; ?>
        <?php if($can_inventory): ?>
            <a href="inventory.php" class="m-link <?php echo $current_page==='inventory.php'?'active':''; ?>"><i class="fa-solid fa-boxes-stacked"></i> <?php echo app_h($t_inventory); ?></a>
        <?php endif; ?>
        <?php if($can_warehouses): ?>
            <a href="warehouses.php" class="m-link <?php echo $current_page==='warehouses.php'?'active':''; ?>"><i class="fa-solid fa-warehouse"></i> <?php echo app_h($t_warehouses); ?></a>
        <?php endif; ?>
        <?php if($can_stock_adjust): ?>
            <a href="adjust_stock.php" class="m-link <?php echo $current_page==='adjust_stock.php'?'active':''; ?>"><i class="fa-solid fa-right-left"></i> <?php echo app_h($t_stock); ?></a>
        <?php endif; ?>
        <?php if(in_array($role, ['admin', 'sales', 'manager'])): ?>
            <a href="clients.php" class="m-link <?php echo $current_page==='clients.php'?'active':''; ?>"><i class="fa-solid fa-users"></i> <?php echo app_h($t_clients); ?></a>
            <a href="suppliers.php" class="m-link <?php echo $current_page==='suppliers.php'?'active':''; ?>"><i class="fa-solid fa-truck-field"></i> <?php echo app_h($t_suppliers); ?></a>
        <?php endif; ?>
        <?php if($can_pricing_view || $can_pricing_settings): ?>
            <?php $pricing_link = $pricing_enabled && $can_pricing_view ? 'pricing_module.php' : 'master_data.php?tab=pricing'; ?>
            <a href="<?php echo $pricing_link; ?>" class="m-link <?php echo $current_page==='pricing_module.php'?'active':''; ?>">
                <i class="fa-solid fa-calculator"></i> <?php echo app_h($t_pricing); ?>
                <?php if (!$pricing_enabled && $can_pricing_settings): ?><span style="margin-inline-start:6px;color:#c9c9c9;font-size:0.8rem;"><?php echo app_h($is_lang_en ? '(disabled)' : '(غير مفعل)'); ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if($is_admin): ?>
            <a href="users.php" class="m-link <?php echo $current_page==='users.php'?'active':''; ?>"><i class="fa-solid fa-users-gear"></i> <?php echo app_h($t_users); ?></a>
            <a href="master_data.php" class="m-link <?php echo $current_page==='master_data.php'?'active':''; ?>"><i class="fa-solid fa-database"></i> <?php echo app_h($t_master_data); ?></a>
            <?php if($show_system_status_nav): ?><a href="system_status.php" class="m-link <?php echo in_array($current_page,['system_status.php','cloud_bridge.php'],true)?'active':''; ?>"><i class="fa-solid fa-shield-halved"></i> <?php echo app_h($t_system_status); ?></a><?php endif; ?>
            <a href="whats_new.php" class="m-link <?php echo $current_page==='whats_new.php'?'active':''; ?>"><i class="fa-solid fa-sparkles"></i> <?php echo app_h($t_whats_new); ?></a>
            <?php if($is_super_user): ?><a href="license_center.php" class="m-link <?php echo $current_page==='license_center.php'?'active':''; ?>"><i class="fa-solid fa-key"></i> <?php echo app_h($t_license_center); ?></a><?php endif; ?>
            <?php if($is_owner_license_hub): ?><a href="license_subscriptions.php" class="m-link <?php echo $current_page==='license_subscriptions.php'?'active':''; ?>"><i class="fa-solid fa-id-card-clip"></i> <?php echo app_h($t_license_subscriptions); ?></a><?php endif; ?>
            <?php if($is_owner_saas_hub): ?><a href="saas_center.php" class="m-link <?php echo $current_page==='saas_center.php'?'active':''; ?>"><i class="fa-solid fa-building-shield"></i> <?php echo app_h($t_saas_center); ?></a><?php endif; ?>
            <a href="system_upgrade.php" class="m-link <?php echo $current_page==='system_upgrade.php'?'active':''; ?>"><i class="fa-solid fa-sliders"></i> <?php echo app_h($t_customization); ?></a>
        <?php endif; ?>

        <div class="m-link lang-mobile-switch">
            <span class="lang-mobile-meta"><i class="fa-solid fa-language"></i> <?php echo app_h($t_lang); ?></span>
            <div class="lang-switch-wrap" title="<?php echo app_h($t_lang); ?>">
                <span class="lang-pill-label">AR</span>
                <label class="lang-switch">
                    <input
                        type="checkbox"
                        id="langToggleMobile"
                        data-ar-url="<?php echo app_h($lang_ar_url); ?>"
                        data-en-url="<?php echo app_h($lang_en_url); ?>"
                        <?php echo $is_lang_en ? 'checked' : ''; ?>
                    >
                    <span class="lang-slider"></span>
                </label>
                <span class="lang-pill-label">EN</span>
            </div>
        </div>
        
        <button id="installAppBtnMobile" class="m-link" onclick="installPWA()">
            <i class="fa-solid fa-download"></i> <?php echo app_h($t_install); ?>
        </button>

        <a href="logout.php" class="m-link mobile-logout"><i class="fa-solid fa-power-off"></i> <?php echo app_h($t_logout); ?></a>
    </div>

    <div id="pwaInstallModal" class="pwa-modal">
        <div class="pwa-content">
            <div style="font-size:3rem; color:var(--gold-primary);"><i class="fa-solid fa-mobile-screen-button"></i></div>
            <div class="pwa-text">
                <h4><?php echo app_h(app_tr('تثبيت نظام ', 'Install ')); ?><?php echo $app_name_safe; ?></h4>
                <p><?php echo app_h(app_tr('لتجربة أفضل وأسرع، قم بتثبيت النظام كتطبيق على جهازك الآن.', 'For a faster and better experience, install the system as an app on your device.')); ?></p>
            </div>
            <div class="pwa-actions">
                <button class="pwa-btn-install" onclick="installPWA()"><?php echo app_h(app_tr('تثبيت الآن', 'Install now')); ?></button>
                <button class="pwa-btn-close" onclick="closePwaModal()"><?php echo app_h(app_tr('لاحقاً', 'Later')); ?></button>
            </div>
        </div>
    </div>

    <div id="iosInstallPrompt" class="ios-prompt">
        <div style="text-align:start; cursor:pointer; color:#888;" onclick="document.getElementById('iosInstallPrompt').style.display='none'">×</div>
        <h4 style="color:var(--gold-primary); margin-top:0;"><?php echo app_h(app_tr('تثبيت التطبيق على آيفون', 'Install app on iPhone')); ?></h4>
        <p style="color:#ccc; font-size:0.9rem;"><?php echo app_h(app_tr('لتثبيت التطبيق، اضغط على زر المشاركة', 'To install the app, tap the share button')); ?> <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MCA1MCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgNTAgNTAiPjxwYXRoIGQ9Ik0zMC4zIDEzLjdMMjUgOC40bC01LjMgNS4zLTEuNC0xLjRMMjUgNC4ybDYuNyA4LjF6IiBmaWxsPSIjNDQ4YWZmIi8+PHBhdGggZD0iTTI0IDZ2MThoMnYtMTh6IiBmaWxsPSIjNDQ4YWZmIi8+PHBhdGggZD0iTTM1IDM0djhIMTV2LThoLTJ2MTBoMjR2LTEweiIgZmlsbD0iIzQ0OGFmZiIvPjwvc3ZnPg==" style="width:20px; vertical-align:middle;"> <?php echo app_h(app_tr('في الأسفل، ثم اختر', 'at the bottom, then choose')); ?> <strong>"<?php echo app_h(app_tr('إضافة إلى الشاشة الرئيسية', 'Add to Home Screen')); ?>"</strong> <i class="fa-regular fa-square-plus"></i>.</p>
    </div>

    <script>
        const csrfToken = <?php echo json_encode(app_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const runtimeI18n = <?php echo json_encode($runtime_i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const runtimeI18nEntries = (() => {
            if (!runtimeI18n || typeof runtimeI18n !== "object") return [];

            function aeEscapeRegExp(value) {
                return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
            }

            function aeBuildTranslatorEntry(source, target) {
                const from = typeof source === "string" ? source.trim() : "";
                const to = typeof target === "string" ? target : "";
                if (!from || !to || from === to) return null;

                // Very short Arabic tokens (e.g. "لا", "من") corrupt words when replaced globally.
                if (/[\u0600-\u06FF]/.test(from) && from.length < 3) return null;

                const escaped = aeEscapeRegExp(from);
                const singleToken = /^[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FFA-Za-z0-9_]+$/.test(from);
                if (!singleToken) {
                    return {
                        from,
                        to,
                        boundary: false,
                        regex: new RegExp(escaped, "g"),
                    };
                }

                const nonWord = "[^\\u0600-\\u06FF\\u0750-\\u077F\\u08A0-\\u08FFA-Za-z0-9_]";
                return {
                    from,
                    to,
                    boundary: true,
                    regex: new RegExp(`(^|${nonWord})(${escaped})(?=$|${nonWord})`, "g"),
                };
            }

            return Object.entries(runtimeI18n)
                .map(([from, to]) => aeBuildTranslatorEntry(from, to))
                .filter((entry) => entry !== null)
                .sort((a, b) => b.from.length - a.from.length);
        })();
        // server‑generated translation map keyed by translation keys
        const APP_TRANS = <?php echo json_encode($transMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const desktopNavMedia = window.matchMedia("(min-width: 1025px)");
        const isMobileRuntime = window.matchMedia("(max-width: 1024px)").matches;

        /**
         * lookup translator used in client code when explicit keys are available
         * @param {string} key translation key
         * @param {string} [fallback] fallback value when key not found
         */
        function t(key, fallback) {
            if (!key || typeof key !== 'string') return fallback || key || '';
            if (Object.prototype.hasOwnProperty.call(APP_TRANS, key)) {
                return APP_TRANS[key];
            }
            if (typeof fallback !== "undefined" && fallback !== null) {
                return fallback;
            }
            return key;
        }

        function aeTranslateString(input) {
            if (!input || typeof input !== "string") return input;
            if (!runtimeI18nEntries.length) return input;
            let out = input;
            for (const entry of runtimeI18nEntries) {
                entry.regex.lastIndex = 0;
                if (entry.boundary) {
                    out = out.replace(entry.regex, (_, prefix) => `${prefix}${entry.to}`);
                } else {
                    out = out.replace(entry.regex, entry.to);
                }
            }
            return out;
        }

        function aeApplyRuntimeI18n(root = document) {
            if (!runtimeI18nEntries.length || !root) return;
            const textWalker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
                acceptNode(node) {
                    if (!node || !node.nodeValue || !/[ء-ي]/.test(node.nodeValue)) return NodeFilter.FILTER_REJECT;
                    const parent = node.parentElement;
                    if (!parent) return NodeFilter.FILTER_REJECT;
                    const tag = parent.tagName;
                    if (tag === "SCRIPT" || tag === "STYLE" || tag === "NOSCRIPT") return NodeFilter.FILTER_REJECT;
                    return NodeFilter.FILTER_ACCEPT;
                }
            });

            const textNodes = [];
            while (textWalker.nextNode()) textNodes.push(textWalker.currentNode);
            for (const node of textNodes) {
                node.nodeValue = aeTranslateString(node.nodeValue);
            }

            if (typeof root.querySelectorAll === "function") {
                root.querySelectorAll("input,textarea,button,a,label,option,span,div,h1,h2,h3,h4,p,small,strong,th,td").forEach((el) => {
                    ["placeholder", "title", "aria-label", "data-label", "data-title"].forEach((attr) => {
                        const val = el.getAttribute(attr);
                        if (val && /[ء-ي]/.test(val)) {
                            el.setAttribute(attr, aeTranslateString(val));
                        }
                    });
                });
            }
        }

        function setupRuntimeI18n() {
            if (!runtimeI18nEntries.length || !document.body) return;
            if (isMobileRuntime) {
                // Mobile mode: run one pass + short-lived observer to avoid long CPU drain.
                window.setTimeout(() => aeApplyRuntimeI18n(document.body), 250);
                if (typeof MutationObserver !== "function") return;
                const mobileObserver = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            if (!(node instanceof Node)) return;
                            if (node.nodeType === Node.TEXT_NODE) {
                                const parent = node.parentElement;
                                if (parent) aeApplyRuntimeI18n(parent);
                                return;
                            }
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                aeApplyRuntimeI18n(node);
                            }
                        });
                    });
                });
                mobileObserver.observe(document.body, { childList: true, subtree: true });
                window.setTimeout(() => {
                    try { mobileObserver.disconnect(); } catch (e) {}
                }, 12000);
                return;
            }
            aeApplyRuntimeI18n(document.body);

            if (typeof MutationObserver !== "function") return;
            let scheduled = false;
            const observer = new MutationObserver((mutations) => {
                if (scheduled) return;
                scheduled = true;
                window.requestAnimationFrame(() => {
                    scheduled = false;
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            if (!(node instanceof Node)) return;
                            if (node.nodeType === Node.TEXT_NODE) {
                                const parent = node.parentElement;
                                if (parent) aeApplyRuntimeI18n(parent);
                                return;
                            }
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                aeApplyRuntimeI18n(node);
                            }
                        });
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        function ensurePostFormCsrfToken(form) {
            if (!form || String(form.method || "").toLowerCase() !== "post") return;
            let tokenField = form.querySelector('input[name="_csrf_token"]');
            if (!tokenField) {
                tokenField = document.createElement("input");
                tokenField.type = "hidden";
                tokenField.name = "_csrf_token";
                form.appendChild(tokenField);
            }
            tokenField.value = csrfToken;
        }

        function toggleMenu(forceState) {
            const sidebar = document.getElementById("mobileSidebar");
            const overlay = document.getElementById("mobileOverlay");
            if (!sidebar || !overlay) return;
            const shouldOpenRaw = typeof forceState === "boolean"
                ? forceState
                : !sidebar.classList.contains("open");
            const shouldOpen = shouldOpenRaw && !desktopNavMedia.matches;
            sidebar.classList.toggle("open", shouldOpen);
            overlay.classList.toggle("open", shouldOpen);
            document.body.classList.toggle("no-scroll", shouldOpen);
        }

        function syncNavigationByViewport() {
            if (desktopNavMedia.matches) {
                toggleMenu(false);
            }
        }

        function bindLanguageToggles() {
            const toggles = Array.from(document.querySelectorAll("#langToggleDesktop, #langToggleMobile"));
            if (!toggles.length) return;

            const syncAll = (state, source) => {
                for (const toggle of toggles) {
                    if (toggle !== source) {
                        toggle.checked = state;
                    }
                }
            };

            for (const toggle of toggles) {
                toggle.addEventListener("change", function () {
                    const goEn = !!this.checked;
                    syncAll(goEn, this);
                    const targetUrl = goEn ? this.dataset.enUrl : this.dataset.arUrl;
                    if (targetUrl) {
                        window.location.href = targetUrl;
                    }
                });
            }
        }

        function setupDesktopMoreMenu() {
            const navGroup = document.querySelector(".nav-group");
            const navBtn = navGroup ? navGroup.querySelector(".nav-group-btn") : null;
            if (!navGroup || !navBtn) return;

            const updateAria = () => {
                const expanded = navGroup.classList.contains("open");
                navBtn.setAttribute("aria-expanded", expanded ? "true" : "false");
            };

            navBtn.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                navGroup.classList.toggle("open");
                updateAria();
            });

            document.addEventListener("click", (event) => {
                const target = event.target instanceof Node ? event.target : null;
                if (!target || !navGroup.contains(target)) {
                    navGroup.classList.remove("open");
                    updateAria();
                }
            });

            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") {
                    navGroup.classList.remove("open");
                    updateAria();
                }
            });
        }

        function setupUserDropdown() {
            const profile = document.querySelector(".user-profile");
            if (!profile) return;

            profile.addEventListener("click", (event) => {
                const target = event.target instanceof Element ? event.target : null;
                if (target && target.closest(".dropdown-menu a")) {
                    profile.classList.remove("open");
                    return;
                }
                profile.classList.toggle("open");
            });

            document.addEventListener("click", (event) => {
                const target = event.target instanceof Node ? event.target : null;
                if (!target || !profile.contains(target)) {
                    profile.classList.remove("open");
                }
            });

            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") {
                    profile.classList.remove("open");
                }
            });
        }

        function setupSupportBell() {
            const root = document.getElementById("supportBellRoot");
            const btn = document.getElementById("supportBellBtn");
            if (!root || !btn) return;

            const updateAria = () => {
                const expanded = root.classList.contains("open");
                btn.setAttribute("aria-expanded", expanded ? "true" : "false");
            };

            btn.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                root.classList.toggle("open");
                updateAria();
            });

            document.addEventListener("click", (event) => {
                const target = event.target instanceof Node ? event.target : null;
                if (!target || !root.contains(target)) {
                    root.classList.remove("open");
                    updateAria();
                }
            });

            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") {
                    root.classList.remove("open");
                    updateAria();
                }
            });
        }

        function setupMobileSidebarLinks() {
            document.querySelectorAll(".mobile-sidebar a.m-link").forEach((link) => {
                link.addEventListener("click", () => {
                    toggleMenu(false);
                });
            });
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(ensurePostFormCsrfToken);
            setupDesktopMoreMenu();
            setupUserDropdown();
            setupSupportBell();
            setupMobileSidebarLinks();
            bindLanguageToggles();
            setupRuntimeI18n();
            syncNavigationByViewport();

            if (typeof desktopNavMedia.addEventListener === "function") {
                desktopNavMedia.addEventListener("change", syncNavigationByViewport);
            } else if (typeof desktopNavMedia.addListener === "function") {
                desktopNavMedia.addListener(syncNavigationByViewport);
            }

            // navigation shadow indicators for overflow
            const navLinks = document.querySelector('.nav-links');
            if (navLinks) {
                const updateShadows = () => {
                    const isOverflowing = navLinks.scrollWidth > navLinks.clientWidth + 1;
                    navLinks.classList.toggle('is-overflowing', isOverflowing);
                    if (!isOverflowing) {
                        navLinks.classList.remove('scrolled-start', 'scrolled-end');
                        return;
                    }
                    navLinks.classList.toggle('scrolled-start', navLinks.scrollLeft <= 1);
                    navLinks.classList.toggle('scrolled-end', navLinks.scrollLeft + navLinks.clientWidth >= navLinks.scrollWidth - 1);
                };
                let resizeRaf = null;
                const onResize = () => {
                    if (resizeRaf !== null) {
                        window.cancelAnimationFrame(resizeRaf);
                    }
                    resizeRaf = window.requestAnimationFrame(() => {
                        resizeRaf = null;
                        updateShadows();
                    });
                };
                navLinks.addEventListener('scroll', updateShadows, { passive: true });
                window.addEventListener('resize', onResize, { passive: true });
                updateShadows();
            }

            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") {
                    toggleMenu(false);
                }
            });
        });
        document.addEventListener("submit", (event) => {
            const form = event.target instanceof HTMLFormElement ? event.target : null;
            ensurePostFormCsrfToken(form);
        }, true);

        let deferredPrompt;
        const installBtn = document.getElementById("installAppBtn");
        const installBtnMobile = document.getElementById("installAppBtnMobile");
        const pwaModal = document.getElementById("pwaInstallModal");
        const iosPrompt = document.getElementById("iosInstallPrompt");

        function isIos() {
            const userAgent = window.navigator.userAgent.toLowerCase();
            return /iphone|ipad|ipod/.test(userAgent);
        }

        function isInStandalone() {
            return ("standalone" in window.navigator) && (window.navigator.standalone);
        }

        window.addEventListener("beforeinstallprompt", (event) => {
            event.preventDefault();
            deferredPrompt = event;
            if (installBtn) installBtn.style.display = "flex";
            if (installBtnMobile) installBtnMobile.style.display = "flex";
            window.setTimeout(() => {
                if (pwaModal) pwaModal.style.display = "block";
            }, 3000);
        });

        if (isIos() && !isInStandalone()) {
            window.setTimeout(() => {
                if (iosPrompt) iosPrompt.style.display = "block";
            }, 4000);
        }

        async function installPWA() {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            if (choice && choice.outcome === "accepted") {
                if (installBtn) installBtn.style.display = "none";
                if (installBtnMobile) installBtnMobile.style.display = "none";
                if (pwaModal) pwaModal.style.display = "none";
            }
            deferredPrompt = null;
        }

        function closePwaModal() {
            if (pwaModal) pwaModal.style.display = "none";
        }

        const shouldAutoLicenseSync = <?php echo $current_id > 0 ? 'true' : 'false'; ?>;
        const licenseSyncEndpoint = <?php echo json_encode(rtrim(SYSTEM_URL, '/') . '/api/license/sync/'); ?>;
        const cloudDataSyncEndpoint = <?php echo json_encode(rtrim(SYSTEM_URL, '/') . '/api/cloud/sync/local/'); ?>;
        const licenseSyncToken = <?php echo json_encode(app_csrf_token()); ?>;
        let licenseSyncPending = false;
        let licenseLastSyncAt = 0;
        let cloudSyncPending = false;
        let cloudLastSyncAt = 0;

        function syncLicenseState(reason = "heartbeat") {
            if (!shouldAutoLicenseSync) return;
            if (licenseSyncPending || !licenseSyncEndpoint || !licenseSyncToken) return;
            if (typeof navigator !== "undefined" && navigator.onLine === false) return;

            const now = Date.now();
            if (reason !== "online_reconnect" && (now - licenseLastSyncAt) < 15000) return;

            licenseSyncPending = true;
            const payload = new URLSearchParams();
            payload.set("_csrf_token", licenseSyncToken);
            payload.set("reason", reason);

            fetch(licenseSyncEndpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-Requested-With": "XMLHttpRequest"
                },
                cache: "no-store",
                body: payload.toString()
            })
                .then((response) => response.ok ? response.json() : null)
                .then((data) => {
                    licenseLastSyncAt = Date.now();
                    if (!data || data.allowed !== false) return;
                    const blockedPage = (window.location.pathname || "").split("/").pop() || "";
                    if (blockedPage === "license_center.php" || blockedPage === "logout.php") return;
                    window.location.href = "license_center.php?locked=1";
                })
                .catch(() => {})
                .finally(() => {
                    licenseSyncPending = false;
                });
        }

        function syncCloudDataState(reason = "heartbeat") {
            if (!shouldAutoLicenseSync) return;
            if (cloudSyncPending || !cloudDataSyncEndpoint || !licenseSyncToken) return;
            if (typeof navigator !== "undefined" && navigator.onLine === false) return;

            const now = Date.now();
            if (reason !== "online_reconnect" && (now - cloudLastSyncAt) < 20000) return;

            cloudSyncPending = true;
            const payload = new URLSearchParams();
            payload.set("_csrf_token", licenseSyncToken);
            payload.set("reason", reason);

            fetch(cloudDataSyncEndpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-Requested-With": "XMLHttpRequest"
                },
                cache: "no-store",
                body: payload.toString()
            })
                .then((response) => response.ok ? response.json() : null)
                .then(() => {
                    cloudLastSyncAt = Date.now();
                })
                .catch(() => {})
                .finally(() => {
                    cloudSyncPending = false;
                });
        }

        if (shouldAutoLicenseSync) {
            window.addEventListener("online", () => {
                syncLicenseState("online_reconnect");
                syncCloudDataState("online_reconnect");
            });
            document.addEventListener("visibilitychange", () => {
                if (!document.hidden) {
                    syncLicenseState("tab_visible");
                    syncCloudDataState("tab_visible");
                }
            });
            window.setInterval(() => syncLicenseState("heartbeat"), 45000);
            window.setInterval(() => syncCloudDataState("heartbeat"), 60000);
        }

        if ("serviceWorker" in navigator) {
            window.addEventListener("load", () => {
                navigator.serviceWorker.register("service-worker.js")
                    .then(() => console.log("Service Worker Registered"))
                    .catch((err) => console.log("Service Worker Failed", err));
            });
        }
    </script>

<?php endif; ?>
