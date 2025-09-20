<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

function log_action($pdo, $license_id, $message) {
    $stmt = $pdo->prepare("INSERT INTO logs (license_id, type, message) VALUES (?, 'action', ?)");
    $stmt->execute([$license_id, $message]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id  = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $customer_id = (isset($_POST['customer_id']) && $_POST['customer_id']!=='') ? (int)$_POST['customer_id'] : null;
    $days        = isset($_POST['days']) ? (int)$_POST['days'] : 30;
    $max_devices = isset($_POST['max_devices']) ? (int)$_POST['max_devices'] : 1;
    $hwid        = trim($_POST['hwid'] ?? ''); // <<— HWID اختياري

    if ($product_id <= 0) { $_SESSION['flash']='الرجاء اختيار المنتج.'; header('Location: create_key.php'); exit; }

    $key = bin2hex(random_bytes(16));

    $stmt = $pdo->prepare("INSERT INTO license_keys (`key`, product_id, customer_id, days, max_devices, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$key, $product_id, $customer_id, $days, $max_devices]);
    $newId = (int)$pdo->lastInsertId();

    // إذا تم إدخال HWID يدوياً نسجله كجهاز فعّال مباشر
    if ($hwid !== '') {
        $stmtDev = $pdo->prepare("INSERT INTO devices (license_id, hwid, is_active, activated_at) VALUES (?, ?, 1, NOW())");
        $stmtDev->execute([$newId, $hwid]);
    }

    $prodName = $pdo->query("SELECT name FROM products WHERE id=".$product_id)->fetchColumn();
    $custName = $customer_id ? ($pdo->query("SELECT name FROM customers WHERE id=".$customer_id)->fetchColumn() ?: '-') : '-';
    $admin    = $_SESSION['admin_username'] ?? 'admin';

    log_action($pdo, $newId, "License created by {$admin} | key={$key} | product={$prodName} | customer={$custName}".($hwid ? " | hwid={$hwid}" : ""));

    $_SESSION['flash']='تم إنشاء المفتاح بنجاح.';
    header('Location: dashboard.php'); exit;
}

// بيانات النموذج
$products  = $pdo->query("SELECT id,name FROM products ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id,name,email FROM customers ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>إنشاء مفتاح جديد</title>
<style>
body{font-family:Arial,sans-serif;background:#f7f7f7;padding:20px}
.card{background:#fff;border:1px solid #ddd;max-width:620px;margin:auto;padding:18px}
label{display:block;margin-top:10px}
input,select{width:100%;padding:8px;margin-top:6px}
.actions{margin-top:15px;display:flex;gap:8px}
button{padding:8px 12px;border:none;background:#007bff;color:#fff;cursor:pointer}
a.btn{display:inline-block;padding:8px 12px;text-decoration:none;border:1px solid #333}
.topnav a{display:inline-block;margin-bottom:10px;padding:6px 10px;background:#6c757d;color:#fff;text-decoration:none;border-radius:4px}
.alert{background:#d1e7dd;color:#0f5132;padding:10px;margin-bottom:10px;border:1px solid #badbcc;border-radius:4px}
</style>
</head>
<body>
<div class="topnav">
  <a href="dashboard.php">⬅ رجوع للوحة المفاتيح</a>
</div>

<?php if(!empty($_SESSION['flash'])): ?>
  <div class="alert"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="card">
  <h2>إنشاء مفتاح جديد</h2>
  <form method="POST">
    <label>المنتج:</label>
    <select name="product_id" required>
      <option value="">-- اختر المنتج --</option>
      <?php foreach($products as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>الزبون (اختياري):</label>
    <select name="customer_id">
      <option value="">-- بدون زبون --</option>
      <?php foreach($customers as $c): ?>
        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?><?= $c['email'] ? ' — '.htmlspecialchars($c['email']) : '' ?></option>
      <?php endforeach; ?>
    </select>

    <label>HWID (UUID) — اختياري:</label>
    <input type="text" name="hwid" placeholder="اتركه فارغاً لجلبه أوتو عند أول تفعيل">

    <label>عدد الأيام (يبدأ من أول تفعيل):</label>
    <input type="number" name="days" value="30" required>

    <label>العدد المسموح للأجهزة:</label>
    <input type="number" name="max_devices" value="1" required>

    <div class="actions">
      <button type="submit">إنشاء</button>
      <a class="btn" href="dashboard.php">إلغاء</a>
    </div>
  </form>
</div>
</body>
</html>
