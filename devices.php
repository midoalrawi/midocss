<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

$licenseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($licenseId <= 0) { $_SESSION['flash']='يرجى اختيار مفتاح لعرض الأجهزة.'; header('Location: dashboard.php'); exit; }

// معالجة POST (تعديل / حذف فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'edit') {
        $oldhw = trim($_POST['oldhw'] ?? '');
        $hwid  = trim($_POST['hwid'] ?? '');
        if ($oldhw === '' || $hwid === '') { $_SESSION['flash']='قيمة HWID المطلوبة.'; header("Location: devices.php?id={$licenseId}"); exit; }
        $pdo->prepare("UPDATE devices SET hwid=? WHERE license_id=? AND hwid=?")->execute([$hwid, $licenseId, $oldhw]);
        $pdo->prepare("INSERT INTO logs (license_id,type,message) VALUES (?, 'action', ?)")->execute([$licenseId, "Device hwid changed from {$oldhw} to {$hwid}"]);
        $_SESSION['flash']='تم تعديل الـ HWID.';
        header("Location: devices.php?id={$licenseId}"); exit;
    }
    if ($action === 'delete') {
        $delhw = trim($_POST['delhw'] ?? '');
        if ($delhw === '') { $_SESSION['flash']='HWID مطلوب للحذف.'; header("Location: devices.php?id={$licenseId}"); exit; }
        $pdo->prepare("DELETE FROM devices WHERE license_id=? AND hwid=?")->execute([$licenseId, $delhw]);
        $pdo->prepare("INSERT INTO logs (license_id,type,message) VALUES (?, 'action', ?)")->execute([$licenseId, "Device deleted | hwid={$delhw}"]);
        $_SESSION['flash']='تم حذف الجهاز.';
        header("Location: devices.php?id={$licenseId}"); exit;
    }
}

// جلب بيانات المفتاح والأجهزة
$license = $pdo->prepare("SELECT id, `key` FROM license_keys WHERE id=?");
$license->execute([$licenseId]); $lic = $license->fetch();
$devices = $pdo->prepare("SELECT * FROM devices WHERE license_id=? ORDER BY activated_at DESC");
$devices->execute([$licenseId]); $devs = $devices->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="utf-8">
<title>أجهزة المفتاح #<?= (int)$licenseId ?></title>
<style>
body{font-family:Arial;padding:18px;background:#f7f7f7}
.topnav a{margin-right:8px;text-decoration:none;padding:6px 8px;background:#6c757d;color:#fff;border-radius:4px}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
th{background:#333;color:#fff}
form.inline{display:inline}
</style>
</head>
<body>
<div class="topnav">
  <a href="dashboard.php">⬅ رجوع للوحة المفاتيح</a>
</div>

<h2>أجهزة المفتاح: <?= htmlspecialchars($lic['key'] ?? 'غير معروف') ?></h2>
<?php if (!empty($_SESSION['flash'])): ?><div style="background:#d1e7dd;color:#0f5132;padding:8px;margin-bottom:10px;border:1px solid #badbcc;border-radius:4px;"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>

<h3>الأجهزة المرتبطة</h3>
<table>
<tr><th>HWID (UUID)</th><th>فعال؟</th><th>تفعيل بتاريخ</th><th>إجراءات</th></tr>
<?php foreach($devs as $d): ?>
<tr>
  <td style="direction:ltr"><?= htmlspecialchars($d['hwid']) ?></td>
  <td><?= $d['is_active']? 'نعم':'لا' ?></td>
  <td><?= htmlspecialchars($d['activated_at'] ?? '-') ?></td>
  <td>
    <button onclick="openEdit('<?= addslashes($d['hwid']) ?>')">✏ تعديل</button>
    <form method="POST" class="inline" onsubmit="return confirm('تأكيد حذف الجهاز؟');">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="delhw" value="<?= htmlspecialchars($d['hwid']) ?>">
      <button type="submit" style="background:#dc3545;color:#fff">حذف</button>
    </form>
  </td>
</tr>
<?php endforeach; if (!$devs): ?>
<tr><td colspan="4">لا توجد أجهزة مسجلة.</td></tr>
<?php endif; ?>
</table>

<!-- نافذة تعديل -->
<div id="editBox" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.4)">
  <div style="background:#fff;width:400px;margin:10% auto;padding:16px;border-radius:6px">
    <h3>تعديل HWID</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" id="oldhw" name="oldhw">
      <label>HWID جديد:</label>
      <input id="newhw" name="hwid" required>
      <div style="margin-top:8px"><button type="submit">حفظ</button> <button type="button" onclick="closeEdit()">إلغاء</button></div>
    </form>
  </div>
</div>

<script>
function openEdit(hwid){
  document.getElementById('oldhw').value=hwid;
  document.getElementById('newhw').value=hwid;
  document.getElementById('editBox').style.display='block';
}
function closeEdit(){document.getElementById('editBox').style.display='none';}
</script>
</body>
</html>
