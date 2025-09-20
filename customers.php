<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// معالجة الإضافة/التحديث/الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '') { $_SESSION['flash']='اكتب اسم الزبون.'; header('Location: customers.php'); exit; }
        $stmt = $pdo->prepare("INSERT INTO customers (name,email) VALUES (?,?)");
        $stmt->execute([$name,$email]);
        $_SESSION['flash']='تمت إضافة الزبون.'; header('Location: customers.php'); exit;
    }
    if ($action === 'update') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($id<=0 || $name===''){ $_SESSION['flash']='بيانات غير صالحة.'; header('Location: customers.php'); exit; }
        $stmt = $pdo->prepare("UPDATE customers SET name=?, email=? WHERE id=?");
        $stmt->execute([$name,$email,$id]);
        $_SESSION['flash']='تم حفظ التعديلات.'; header('Location: customers.php'); exit;
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $_SESSION['flash']='طلب غير صالح.'; header('Location: customers.php'); exit; }
        // تحقّق: هل مرتبط بمفاتيح؟
        $q = $pdo->prepare("SELECT COUNT(*) FROM license_keys WHERE customer_id=?");
        $q->execute([$id]);
        if ((int)$q->fetchColumn() > 0) {
            $_SESSION['flash']='لا يمكن حذف الزبون: مرتبط بمفاتيح ترخيص.'; header('Location: customers.php'); exit;
        }
        $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
        $_SESSION['flash']='تم حذف الزبون.'; header('Location: customers.php'); exit;
    }
}

// جلب الزبائن
$customers = $pdo->query("SELECT id,name,email FROM customers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>الزبائن</title>
<style>
body{font-family:Arial,sans-serif;background:#f7f7f7;padding:20px}
.topnav a{display:inline-block;margin:0 6px 10px 0;padding:6px 10px;background:#6c757d;color:#fff;text-decoration:none;border-radius:4px}
.alert{background:#d1e7dd;color:#0f5132;padding:10px;margin-bottom:10px;border:1px solid #badbcc;border-radius:4px}
.card{background:#fff;border:1px solid #ddd;max-width:720px;margin:0 0 16px 0;padding:14px}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
th{background:#333;color:#fff}
input[type="text"],input[type="email"]{width:100%;padding:8px;box-sizing:border-box}
button{padding:6px 10px;border:none;border-radius:3px;cursor:pointer}
form.inline{display:inline}
.modal{display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:99}
.modal .box{background:#fff;width:520px;max-width:92%;margin:5% auto;padding:16px;border-radius:6px}
.modal h3{margin:6px 0 12px}
.modal label{display:block;text-align:right;margin-top:8px}
.modal input{width:100%;padding:6px;box-sizing:border-box}
</style>
</head>
<body>
<div class="topnav">
  <a href="dashboard.php">📋 الداشبورد</a>
  <a href="products.php">📦 المنتجات</a>
  <a href="logs.php">📜 السجلات</a>
  <a href="logout.php">🚪 خروج</a>
</div>

<?php if(!empty($_SESSION['flash'])): ?>
  <div class="alert"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="card">
  <h3>إضافة زبون</h3>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <label>اسم الزبون</label>
    <input type="text" name="name" placeholder="اكتب الاسم" required>
    <label style="margin-top:8px">البريد (اختياري)</label>
    <input type="email" name="email" placeholder="example@domain.com">
    <div style="margin-top:8px">
      <button style="background:#0d6efd;color:#fff">إضافة</button>
    </div>
  </form>
</div>

<table>
  <tr>
    <th>ID</th>
    <th>الاسم</th>
    <th>البريد</th>
    <th>إجراءات</th>
  </tr>
  <?php foreach($customers as $c): ?>
  <tr>
    <td><?= (int)$c['id'] ?></td>
    <td><?= h($c['name']) ?></td>
    <td><?= h($c['email'] ?? '-') ?></td>
    <td>
      <button style="background:#0d6efd;color:#fff" onclick="openEdit(<?= (int)$c['id'] ?>,'<?= h($c['name']) ?>','<?= h($c['email'] ?? '') ?>')">تعديل</button>
      <form class="inline" method="POST" onsubmit="return confirm('تأكيد حذف الزبون؟ سيتم الحذف فقط إذا لا توجد مفاتيح مرتبطة.');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button style="background:#dc3545;color:#fff">حذف</button>
      </form>
    </td>
  </tr>
  <?php endforeach; if(!$customers): ?>
  <tr><td colspan="4">لا توجد سجلات.</td></tr>
  <?php endif; ?>
</table>

<!-- مودال تعديل الزبون -->
<div id="editModal" class="modal">
  <div class="box">
    <h3>تعديل الزبون</h3>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <label>الاسم</label>
      <input type="text" name="name" id="e_name" required>
      <label>البريد</label>
      <input type="email" name="email" id="e_email" placeholder="example@domain.com">
      <div style="margin-top:10px">
        <button type="submit" style="background:#0d6efd;color:#fff;padding:6px 10px;border:none;border-radius:4px">حفظ</button>
        <button type="button" onclick="closeEdit()" style="padding:6px 10px;border:1px solid #333;border-radius:4px;background:#fff">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id,name,email){
  document.getElementById('e_id').value = id;
  document.getElementById('e_name').value = name || '';
  document.getElementById('e_email').value = email || '';
  document.getElementById('editModal').style.display='block';
}
function closeEdit(){ document.getElementById('editModal').style.display='none'; }
window.addEventListener('keydown', e=>{ if(e.key==='Escape') closeEdit(); });
</script>
</body>
</html>
