<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';
require __DIR__ . '/validate_hwid.php';

function log_action($pdo, $license_id, $message) {
    $stmt = $pdo->prepare("INSERT INTO logs (license_id, type, message) VALUES (?, 'action', ?)");
    $stmt->execute([$license_id, $message]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }

$action = $_POST['action'] ?? '';
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (in_array($action, ['revoke','reactivate','reset_devices','delete_license','update_license','delete_device']) && $id <= 0) {
    $_SESSION['flash'] = 'طلب غير صالح.'; header("Location: dashboard.php"); exit;
}

// جلب بيانات المفتاح
$license = null;
if (in_array($action, ['revoke','reactivate','reset_devices','delete_device','delete_license','update_license'])) {
    $stmt = $pdo->prepare("SELECT lk.*, p.name AS product_name, c.name AS customer_name
                           FROM license_keys lk
                           LEFT JOIN products p ON p.id = lk.product_id
                           LEFT JOIN customers c ON c.id = lk.customer_id
                           WHERE lk.id=?");
    $stmt->execute([$id]);
    $license = $stmt->fetch();
}

if ($action === 'revoke') {
    if (!$license) { $_SESSION['flash']='المفتاح غير موجود.'; header("Location: dashboard.php"); exit; }
    $pdo->prepare("UPDATE license_keys SET status='revoked' WHERE id=?")->execute([$id]);
    log_action($pdo, $id, "License revoked");
    $_SESSION['flash'] = 'تم تعطيل المفتاح.'; header("Location: dashboard.php"); exit;
}

if ($action === 'reactivate') {
    if (!$license) { $_SESSION['flash']='المفتاح غير موجود.'; header("Location: dashboard.php"); exit; }
    $pdo->prepare("UPDATE license_keys SET status='active' WHERE id=?")->execute([$id]);
    log_action($pdo, $id, "License reactivated");
    $_SESSION['flash'] = 'تم تفعيل المفتاح.'; header("Location: dashboard.php"); exit;
}

if ($action === 'reset_devices') {
    if (!$license) { $_SESSION['flash']='المفتاح غير موجود.'; header("Location: dashboard.php"); exit; }
    // حذف كل الأجهزة فعلياً
    $pdo->prepare("DELETE FROM devices WHERE license_id=?")->execute([$id]);
    log_action($pdo, $id, "Devices reset (all devices removed)");
    $_SESSION['flash'] = 'تم حذف كل الأجهزة المرتبطة بالمفتاح.';
    header("Location: dashboard.php"); exit;
}

if ($action === 'delete_device' && isset($_POST['hwid'])) {
    if (!$license) { $_SESSION['flash']='المفتاح غير موجود.'; header("Location: dashboard.php"); exit; }
    $hwid = $_POST['hwid'];
    $pdo->prepare("DELETE FROM devices WHERE license_id=? AND hwid=?")->execute([$id, $hwid]);
    log_action($pdo, $id, "Device deleted | hwid={$hwid}");
    $_SESSION['flash'] = 'تم حذف الجهاز.'; header("Location: dashboard.php"); exit;
}

if ($action === 'delete_license') {
    if (!$license) { $_SESSION['flash']='المفتاح غير موجود.'; header("Location: dashboard.php"); exit; }
    // عدّ الأجهزة قبل الحذف
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE license_id=?");
    $countStmt->execute([$id]);
    $deviceCount = (int)$countStmt->fetchColumn();

    $admin = $_SESSION['admin_username'] ?? 'admin';
    $keyFull = $license['key'] ?? 'unknown';
    $prod    = $license['product_name'] ?? '-';
    $cust    = $license['customer_name'] ?? '-';
    log_action($pdo, $id, "License deleted by {$admin} | key={$keyFull} | product={$prod} | customer={$cust} | devices_removed={$deviceCount}");

    // حذف المفتاح (الأجهزة تُحذف بكاسكيد إذا مفعّل، والـ logs تبقى)
    $pdo->prepare("DELETE FROM license_keys WHERE id=?")->execute([$id]);

    $_SESSION['flash'] = "تم حذف المفتاح وعدد الأجهزة المحذوفة ({$deviceCount}).";
    header("Location: dashboard.php"); exit;
}

if ($action === 'update_license') {
    if (!$license) { $_SESSION['flash']='المفتاح غير موجود.'; header("Location: dashboard.php"); exit; }

    $product_id  = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int)$_POST['product_id'] : null;
    $customer_id = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
    $days        = isset($_POST['days']) && $_POST['days'] !== '' ? (int)$_POST['days'] : null;
    $max_devices = isset($_POST['max_devices']) && $_POST['max_devices'] !== '' ? (int)$_POST['max_devices'] : null;
    $status      = $_POST['status'] ?? null;

    $fields=[]; $params=[];
    if ($product_id !== null) { $fields[]="product_id=?"; $params[]=$product_id; }
    if ($customer_id !== null || array_key_exists('customer_id', $_POST)) { $fields[]="customer_id=?"; $params[]=$customer_id; }
    if ($days !== null) { $fields[]="days=?"; $params[]=$days; }
    if ($max_devices !== null) { $fields[]="max_devices=?"; $params[]=$max_devices; }
    if ($status !== null && $status!=='') { $fields[]="status=?"; $params[]=$status; }

    if ($fields) {
        $params[]=$id;
        $sql="UPDATE license_keys SET ".implode(", ", $fields)." WHERE id=?";
        $pdo->prepare($sql)->execute($params);
        log_action($pdo, $id, "License updated by ".($_SESSION['admin_username'] ?? 'admin'));
        $_SESSION['flash']='تم حفظ التعديلات.';
    }

    // HWID يدوي (استبدال كامل)
    $test_hwid = trim($_POST['test_hwid'] ?? '');
    if ($test_hwid !== '') {
        if (!validate_hwid($test_hwid)) {
            $_SESSION['flash'] = 'صيغة HWID غير صحيحة (UUID مطلوب).';
            header("Location: dashboard.php"); exit;
        }
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM devices WHERE license_id=?")->execute([$id]);
        $pdo->prepare("INSERT INTO devices (license_id, hwid, is_active, activated_at) VALUES (?, ?, 1, NOW())")
            ->execute([$id, $test_hwid]);
        $pdo->commit();
        log_action($pdo, $id, "Device set (manual replace) | hwid={$test_hwid}");
        $_SESSION['flash'] = ($_SESSION['flash'] ?? 'تمت العملية.') . ' | تم استبدال HWID بالكامل.';
    }

    header("Location: dashboard.php"); exit;
}

$_SESSION['flash'] = 'إجراء غير معروف.'; header("Location: dashboard.php"); exit;
