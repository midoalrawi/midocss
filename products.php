<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// معالجة الإضافة/الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['flash'] = 'اكتب اسم المنتج.';
            header('Location: products.php'); exit;
        }
        $stmt = $pdo->prepare("INSERT INTO products (name) VALUES (?)");
        $stmt->execute([$name]);
        $_SESSION['flash'] = 'تمت إضافة المنتج.';
        header('Location: products.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $_SESSION['flash'] = 'طلب غير صالح.'; header('Location: products.php'); exit; }
        // تحقّق: هل مرتبط بمفاتيح؟
        $q = $pdo->prepare("SELECT COUNT(*) FROM license_keys WHERE product_id=?");
        $q->execute([$id]);
        if ((int)$q->fetchColumn() > 0) {
            $_SESSION['flash'] = 'لا يمكن حذف المنتج: مرتبط بمفاتيح ترخيص.';
            header('Location: products.php'); exit;
        }
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        $_SESSION['flash'] = 'تم حذف المنتج.';
        header('Location: products.php'); exit;
    }
}

// جلب المنتجات
$products = $pdo->query("SELECT id, name FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>المنتجات</title>
<style>
body{font-family:Arial,sans-serif;background:#f7f7f7;padding:20px}
.topnav a{display:inline-block;margin:0 6px 10px 0;padding:6px 10px;background:#6c757d;color:#fff;text-decoration:none;border-radius:4px}
.alert{background:#d1e7dd;color:#0f5132;padding:10px;margin-bottom:10px;border:1px solid #badbcc;border-radius:4px}
.card{background:#fff;border:1px solid #ddd;max-width:620px;margin:0 0 16px 0;padding:14px}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
th{background:#333;color:#fff}
input[type="text"]{width:100%;padding:8px;box-sizing:border-box}
button{padding:6px 10px;border:none;border-radius:3px;cursor:pointer}
form.inline{display:inline}
</style>
</head>
<body>
<div class="topnav">
  <a href="dashboard.php">📋 الداشبورد</a>
  <a href="customers.php">👤 الزبائن</a>
  <a href="logs.php">📜 السجلات</a>
  <a href="logout.php">🚪 خروج</a>
</div>

<?php if(!empty($_SESSION['flash'])): ?>
  <div class="alert"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="card">
  <h3>إضافة منتج</h3>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <label>اسم المنتج</label>
    <input type="text" name="name" placeholder="اكتب اسم المنتج" required>
    <div style="margin-top:8px">
      <button style="background:#0d6efd;color:#fff">إضافة</button>
    </div>
  </form>
</div>

<table>
  <tr>
    <th>ID</th>
    <th>اسم المنتج</th>
    <th>إجراءات</th>
  </tr>
  <?php foreach($products as $p): ?>
  <tr>
    <td><?= (int)$p['id'] ?></td>
    <td><?= h($p['name']) ?></td>
    <td>
      <form class="inline" method="POST" onsubmit="return confirm('تأكيد حذف المنتج؟ سيتم الحذف فقط إذا لا توجد مفاتيح مرتبطة.');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button style="background:#dc3545;color:#fff">حذف</button>
      </form>
    </td>
  </tr>
  <?php endforeach; if(!$products): ?>
  <tr><td colspan="3">لا توجد منتجات.</td></tr>
  <?php endif; ?>
</table>
</body>
</html>
