<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

if (!isset($_GET['id'])) { header('Location: dashboard.php'); exit; }
$id = (int)$_GET['id'];

// جلب بيانات المفتاح قبل الحذف
$stmt = $pdo->prepare("SELECT `key` FROM license_keys WHERE id=?");
$stmt->execute([$id]);
$key = $stmt->fetchColumn();

if (!$key) { $_SESSION['flash']='المفتاح غير موجود.'; header('Location: dashboard.php'); exit; }

// احسب عدد الأجهزة قبل الحذف
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE license_id=?");
$countStmt->execute([$id]);
$deviceCount = (int)$countStmt->fetchColumn();

// احذف المفتاح (ON DELETE CASCADE يمسح الأجهزة المرتبطة)
$stmt = $pdo->prepare("DELETE FROM license_keys WHERE id=?");
$stmt->execute([$id]);

// سجل العملية في اللوج
$admin = $_SESSION['admin_username'] ?? 'admin';
$msg = "License deleted by {$admin} | key={$key}";
if ($deviceCount > 0) $msg .= " | devices_removed={$deviceCount}";
$pdo->prepare("INSERT INTO logs (license_id, type, message) VALUES (?, 'action', ?)")->execute([$id, $msg]);

$_SESSION['flash'] = "تم حذف المفتاح وكل أجهزته ({$deviceCount}).";
header('Location: dashboard.php');
exit;
