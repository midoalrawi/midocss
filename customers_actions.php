<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: customers.php"); exit;
}

$action = $_POST['action'] ?? '';
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($action === 'update_customer') {
    if ($id <= 0) { $_SESSION['flash'] = 'طلب تعديل غير صالح.'; header("Location: customers.php"); exit; }
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $note  = trim($_POST['note']  ?? '');

    if ($name === '') { $_SESSION['flash'] = 'الاسم مطلوب.'; header("Location: customers.php"); exit; }

    $stmt = $pdo->prepare("UPDATE customers SET name=?, email=?, note=? WHERE id=?");
    $stmt->execute([$name, $email, $note, $id]);
    $_SESSION['flash'] = 'تم حفظ تعديلات الزبون.';
    header("Location: customers.php"); exit;
}

if ($action === 'delete_customer') {
    if ($id <= 0) { $_SESSION['flash'] = 'طلب حذف غير صالح.'; header("Location: customers.php"); exit; }

    // فك الارتباط عن المفاتيح قبل الحذف لتفادي قيود FK
    $pdo->prepare("UPDATE license_keys SET customer_id=NULL WHERE customer_id=?")->execute([$id]);

    // حذف الزبون
    $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);

    $_SESSION['flash'] = 'تم حذف الزبون.';
    header("Location: customers.php"); exit;
}

$_SESSION['flash'] = 'إجراء غير معروف.';
header("Location: customers.php"); exit;
