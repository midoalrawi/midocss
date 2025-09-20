<?php
require __DIR__.'/auth.php';
require __DIR__.'/config.php';
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// تأكد من جدول الإعدادات
$pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
  skey VARCHAR(64) PRIMARY KEY,
  svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function get_setting($pdo,$key,$def=''){
  $st=$pdo->prepare("SELECT svalue FROM app_settings WHERE skey=?"); $st->execute([$key]);
  $v=$st->fetchColumn(); return $v!==false?$v:$def;
}
function set_setting($pdo,$key,$val){
  $st=$pdo->prepare("INSERT INTO app_settings (skey,svalue) VALUES (?,?)
                     ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)");
  $st->execute([$key,$val]);
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// تغيير اسم الموقع
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_name') {
  $name = trim($_POST['site_name'] ?? '');
  if ($name==='') { $_SESSION['flash']='اكتب اسم الموقع.'; header('Location: settings.php'); exit; }
  set_setting($pdo,'site_name',$name);
  $_SESSION['flash']='تم حفظ اسم الموقع.'; header('Location: settings.php'); exit;
}

// رفع الأيقونة
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='upload_icon') {
  if (!isset($_FILES['icon']) || $_FILES['icon']['error']!==UPLOAD_ERR_OK) {
    $_SESSION['flash']='فشل الرفع.'; header('Location: settings.php'); exit;
  }
  $f = $_FILES['icon'];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $ok = in_array($ext, ['ico','png']);
  if (!$ok) { $_SESSION['flash']='يُقبل فقط ICO أو PNG.'; header('Location: settings.php'); exit; }
  @mkdir(__DIR__.'/assets',0755,true);
  $dest = __DIR__.'/assets/favicon.'.($ext==='ico'?'ico':'png');
  if (!move_uploaded_file($f['tmp_name'],$dest)) { $_SESSION['flash']='تعذر حفظ الملف.'; header('Location: settings.php'); exit; }
  // خزن الامتداد الحالي
  set_setting($pdo,'favicon_ext',$ext);
  $_SESSION['flash']='تم تحديث الأيقونة.'; header('Location: settings.php'); exit;
}

// تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='change_pass') {
  $uid = $_SESSION['admin_id'] ?? 0;
  if ($uid<=0) { $_SESSION['flash']='جلسة غير صالحة.'; header('Location: settings.php'); exit; }
  $old = $_POST['old_pass'] ?? '';
  $new = $_POST['new_pass'] ?? '';
  $rep = $_POST['new_pass2'] ?? '';
  if ($new==='' || $rep==='' || $old==='') { $_SESSION['flash']='أكمل الحقول المطلوبة.'; header('Location: settings.php'); exit; }
  if ($new !== $rep) { $_SESSION['flash']='كلمتا المرور غير متطابقتين.'; header('Location: settings.php'); exit; }

  // جلب بيانات الأدمن
  $st = $pdo->prepare("SELECT id, password_hash, username FROM admins WHERE id=?");
  $st->execute([$uid]); $adm = $st->fetch(PDO::FETCH_ASSOC);
  if (!$adm || !password_verify($old, $adm['password_hash'])) {
    $_SESSION['flash']='كلمة المرور الحالية غير صحيحة.'; header('Location: settings.php'); exit;
  }
  $hash = password_hash($new, PASSWORD_DEFAULT);
  $pdo->prepare("UPDATE admins SET password_hash=? WHERE id=?")->execute([$hash,$uid]);
  $_SESSION['flash']='تم تغيير كلمة المرور.'; header('Location: settings.php'); exit;
}

// قيم العرض
$site_name = get_setting($pdo,'site_name','License Manager');
$favicon_ext = get_setting($pdo,'favicon_ext','');
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>إعدادات النظام</title>
<?php if ($favicon_ext): ?>
<link rel="icon" href="assets/favicon.<?= h($favicon_ext) ?>" type="image/<?= h($favicon_ext==='ico'?'x-icon':'png') ?>">
<?php endif; ?>
<style>
body{font-family:Arial,sans-serif;background:#f7f7f7;padding:20px}
.topnav a{display:inline-block;margin:0 6px 10px 0;padding:6px 10px;background:#6c757d;color:#fff;text-decoration:none;border-radius:4px}
.wrap{max-width:760px;margin:auto}
.card{background:#fff;border:1px solid #ddd;margin:0 0 14px 0;padding:14px;border-radius:6px}
h3{margin:0 0 10px}
label{display:block;margin:6px 0 4px}
input[type="text"],input[type="password"],input[type="file"]{width:100%;padding:8px;box-sizing:border-box}
button{padding:8px 12px;border:none;border-radius:4px;cursor:pointer;background:#0d6efd;color:#fff}
.alert{background:#d1e7dd;color:#0f5132;padding:10px;margin-bottom:10px;border:1px solid #badbcc;border-radius:4px}
.note{color:#666;font-size:12px;margin-top:6px}
</style>
</head>
<body>
<div class="topnav">
  <a href="dashboard.php">📋 الداشبورد</a>
  <a href="products.php">📦 المنتجات</a>
  <a href="customers.php">👤 الزبائن</a>
  <a href="logs.php">📜 السجلات</a>
  <a href="update.php" style="background:#0d6efd">🔄 التحديث</a>
  <a href="logout.php">🚪 خروج</a>
</div>

<div class="wrap">
  <?php if($flash): ?><div class="alert"><?= h($flash) ?></div><?php endif; ?>

  <div class="card">
    <h3>اسم الموقع</h3>
    <form method="POST">
      <input type="hidden" name="action" value="save_name">
      <label>الاسم الظاهر في الواجهة</label>
      <input type="text" name="site_name" value="<?= h($site_name) ?>" required>
      <div style="margin-top:8px"><button>حفظ</button></div>
    </form>
  </div>

  <div class="card">
    <h3>أيقونة الموقع (favicon)</h3>
    <?php if($favicon_ext): ?>
      <div class="note">الأيقونة الحالية: assets/favicon.<?= h($favicon_ext) ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_icon">
      <label>ICO أو PNG</label>
      <input type="file" name="icon" accept=".ico,.png" required>
      <div class="note">الحجم المقترح: 32x32 أو 48x48.</div>
      <div style="margin-top:8px"><button>رفع وتحديث</button></div>
    </form>
  </div>

  <div class="card">
    <h3>تغيير كلمة مرور الأدمن</h3>
    <form method="POST">
      <input type="hidden" name="action" value="change_pass">
      <label>كلمة المرور الحالية</label>
      <input type="password" name="old_pass" required>
      <label>كلمة المرور الجديدة</label>
      <input type="password" name="new_pass" required>
      <label>تأكيد كلمة المرور الجديدة</label>
      <input type="password" name="new_pass2" required>
      <div class="note">سيتم حفظها بشكل مشفر (password_hash).</div>
      <div style="margin-top:8px"><button>تحديث كلمة المرور</button></div>
    </form>
  </div>
</div>
</body>
</html>
