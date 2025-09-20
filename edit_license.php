<?php
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo "Missing license id"; exit; }

// جلب بيانات المفتاح
$stmt = $pdo->prepare("SELECT id, product_id, customer_id, `key`, days, max_devices, status, note, expires_at FROM license_keys WHERE id=?");
$stmt->execute([$id]);
$lic = $stmt->fetch();
if (!$lic) { http_response_code(404); echo "License not found"; exit; }

// جلب المنتجات والزبائن للقوائم
$products  = $pdo->query("SELECT id,name FROM products ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id,name,email FROM customers ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>تعديل مفتاح #<?= htmlspecialchars($lic['id']) ?></title>
<style>
body { font-family: Arial, sans-serif; background:#f7f7f7; padding:20px; }
.card { background:#fff; border:1px solid #ddd; max-width:520px; margin:auto; padding:18px; }
label { display:block; margin-top:10px; }
input, select, textarea { width:100%; padding:8px; margin-top:6px; }
.actions { margin-top:15px; display:flex; gap:8px; }
button { padding:8px 12px; border:none; background:#007bff; color:#fff; cursor:pointer; }
a.btn { display:inline-block; padding:8px 12px; text-decoration:none; border:1px solid #333; }
.small { font-size:12px; color:#555; }
</style>
</head>
<body>
<div class="card">
  <h2>تعديل مفتاح #<?= htmlspecialchars($lic['id']) ?></h2>
  <p class="small">المفتاح: <code><?= htmlspecialchars($lic['key']) ?></code></p>
  <p class="small">تاريخ الانتهاء: <?= $lic['expires_at'] ? htmlspecialchars($lic['expires_at']) : '-' ?></p>

  <form method="POST" action="actions.php">
    <input type="hidden" name="action" value="update_license">
    <input type="hidden" name="id" value="<?= (int)$lic['id'] ?>">

    <label>المنتج:</label>
    <select name="product_id" required>
      <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= ($lic['product_id']==$p['id'])?'selected':'' ?>>
          <?= htmlspecialchars($p['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>الزبون (اختياري):</label>
    <select name="customer_id">
      <option value="" <?= $lic['customer_id'] ? '' : 'selected' ?>>-- بدون زبون --</option>
      <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>" <?= ($lic['customer_id']==$c['id'])?'selected':'' ?>>
          <?= htmlspecialchars($c['name']) ?><?= $c['email'] ? ' — '.htmlspecialchars($c['email']) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>عدد الأيام (يُحسب من أول تفعيل):</label>
    <input type="number" name="days" value="<?= (int)$lic['days'] ?>" required>

    <label>العدد المسموح للأجهزة:</label>
    <input type="number" name="max_devices" value="<?= (int)$lic['max_devices'] ?>" required>

    <label>الحالة:</label>
    <select name="status">
      <?php foreach (['active','revoked','expired','disabled'] as $s): ?>
        <option value="<?= $s ?>" <?= $lic['status']===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>

    <label>ملاحظات:</label>
    <textarea name="note" rows="3"><?= htmlspecialchars($lic['note'] ?? '') ?></textarea>

    <div class="actions">
      <button type="submit">حفظ</button>
      <a class="btn" href="dashboard.php">إلغاء</a>
    </div>
  </form>
</div>
</body>
</html>
