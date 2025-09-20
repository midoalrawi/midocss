<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// جلب المنتجات والزبائن للنموذج (للمودال)
$products  = $pdo->query("SELECT id,name FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$customers = $pdo->query("SELECT id,name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// جلب المفاتيح
$licenses = $pdo->query("
  SELECT lk.id, lk.`key`, lk.status, lk.days, lk.max_devices, lk.expires_at, lk.product_id, lk.customer_id,
         p.name AS product_name, c.name AS customer_name
  FROM license_keys lk
  LEFT JOIN products p ON p.id = lk.product_id
  LEFT JOIN customers c ON c.id = lk.customer_id
  ORDER BY lk.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// HWIDs لكل مفتاح
$hwidsStmt = $pdo->query("
  SELECT license_id, GROUP_CONCAT(hwid ORDER BY activated_at DESC SEPARATOR ', ') AS hwids
  FROM devices
  GROUP BY license_id
");
$hwidsMap = [];
foreach ($hwidsStmt as $r) { $hwidsMap[(int)$r['license_id']] = $r['hwids']; }

// تنبيه القريب انتهاء
$now = new DateTime('now');
$expiringSoonCount = 0;
foreach ($licenses as $x) {
    if (!empty($x['expires_at'])) {
        $expAt = new DateTime($x['expires_at']);
        if ($expAt >= $now) {
            $daysLeft = (int)$now->diff($expAt)->format('%a');
            if ($daysLeft <= 3) $expiringSoonCount++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>لوحة المفاتيح</title>
<style>
body{font-family:Arial,sans-serif;background:#f7f7f7;padding:20px}
.topnav a{display:inline-block;margin:0 6px 10px 0;padding:6px 10px;background:#6c757d;color:#fff;text-decoration:none;border-radius:4px}
.alert{background:#d1e7dd;color:#0f5132;padding:10px;margin-bottom:10px;border:1px solid #badbcc;border-radius:4px}
.alert-warn{background:#fff3cd;color:#664d03;border:1px solid #ffecb5}
.tools{display:flex;gap:10px;align-items:center;margin:10px 0}
.tools input[type="text"]{flex:1;padding:8px;border:1px solid #ccc;border-radius:4px}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #ccc;padding:8px;text-align:center;vertical-align:middle}
th{background:#333;color:#fff}
button.small{font-size:12px;padding:4px 8px;margin:1px;border:none;border-radius:3px;cursor:pointer}
.actions form{display:inline}
.badge{padding:3px 6px;border-radius:4px;color:#fff}
.badge.active{background:#198754}
.badge.revoked{background:#dc3545}
.badge.expired{background:#6c757d}
.row-expiring{background:#fff3cd}
.row-expired{background:#f8d7da}
code{direction:ltr}
.modal{display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:99}
.modal .box{background:#fff;width:520px;max-width:92%;margin:5% auto;padding:16px;border-radius:6px}
.modal h3{margin:6px 0 12px}
.modal label{display:block;text-align:right;margin-top:8px}
.modal input,.modal select{width:100%;padding:6px;box-sizing:border-box}
.modal .row{display:flex;gap:8px}
.modal .row > div{flex:1}
.nothing{display:none;padding:12px;text-align:center;background:#fff;border:1px solid #ccc;border-top:0}
</style>
</head>
<body>
<div class="topnav">
  <a href="create_key.php">➕ إنشاء مفتاح</a>
  <a href="products.php">📦 المنتجات</a>
  <a href="customers.php">👤 الزبائن</a>
  <a href="logs.php">📜 السجلات</a>
  <a href="logout.php">🚪 خروج</a>
  <a href="settings.php">⚙️ الإعدادات</a>
  <a href="update.php" style="background:#0d6efd;">🔄 تحقق من التحديث</a>
</div>

<?php if(!empty($_SESSION['flash'])): ?>
  <div class="alert"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<?php if ($expiringSoonCount > 0): ?>
  <div class="alert-warn">تنبيه: لديك <?= (int)$expiringSoonCount ?> مفتاح/مفاتيح تنتهي صلاحيتها خلال ≤ 3 أيام.</div>
<?php endif; ?>

<div class="tools">
  <input id="q" type="text" placeholder="بحث فوري: المنتج، الزبون، UUID، أو جزء من المفتاح..." autofocus>
</div>

<table id="tbl">
  <thead>
  <tr>
    <th>ID</th>
    <th>Key</th>
    <th>المنتج</th>
    <th>الزبون</th>
    <th>الحالة</th>
    <th>Days</th>
    <th>Max Devices</th>
    <th>HWIDs (UUIDs)</th>
    <th>ينتهي خلال</th>
    <th>Logs</th>
    <th>إجراءات</th>
  </tr>
  </thead>
  <tbody>
  <?php foreach($licenses as $l): ?>
  <?php
    $st  = (string)($l['status'] ?? '');
    $cls = $st==='active'?'active':($st==='revoked'?'revoked':'expired');
    $hwids = $hwidsMap[(int)$l['id']] ?? '-';
    $rowClass = ''; $expiresDisp='-';
    if (!empty($l['expires_at'])) {
        $expAt = new DateTime($l['expires_at']);
        if ($expAt < $now) { $rowClass='row-expired'; $expiresDisp='منتهي'; }
        else {
            $daysLeft = (int)$now->diff($expAt)->format('%a');
            $hoursLeft = (int)$now->diff($expAt)->h + $daysLeft*24;
            $expiresDisp = ($daysLeft>=1)?($daysLeft.' يوم'):($hoursLeft.' ساعة');
            if ($daysLeft <= 3) $rowClass='row-expiring';
        }
    }
    $searchBlob = strtolower(trim(($l['product_name'] ?? '').' '.($l['customer_name'] ?? '').' '.($l['key'] ?? '').' '.($hwids ?? '')));
  ?>
  <tr class="<?= $rowClass ?>" data-search="<?= h($searchBlob) ?>">
    <td><?= (int)$l['id'] ?></td>
    <td><code><?= h($l['key']) ?></code></td>
    <td><?= h($l['product_name'] ?? '-') ?></td>
    <td><?= h($l['customer_name'] ?? '-') ?></td>
    <td><span class="badge <?= $cls ?>"><?= h($st) ?></span></td>
    <td><?= (int)$l['days'] ?></td>
    <td><?= (int)$l['max_devices'] ?></td>
    <td style="text-align:left;direction:ltr"><?= h($hwids) ?></td>
    <td><?= h($expiresDisp) ?></td>
    <td><a class="small" href="logs.php?id=<?= (int)$l['id'] ?>">عرض</a></td>
    <td class="actions">
      <button class="small" style="background:#0d6efd;color:#fff"
        onclick="openEdit(<?= (int)$l['id'] ?>,'<?= (int)$l['product_id'] ?>','<?= (int)$l['customer_id'] ?>','<?= (int)$l['days'] ?>','<?= (int)$l['max_devices'] ?>','<?= h($st) ?>')">تعديل</button>

      <form method="POST" action="actions.php" style="display:inline">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <?php if ($st==='active'): ?>
          <input type="hidden" name="action" value="revoke">
          <button class="small" style="background:#ffc107">تعطيل</button>
        <?php else: ?>
          <input type="hidden" name="action" value="reactivate">
          <button class="small" style="background:#198754;color:#fff">تفعيل</button>
        <?php endif; ?>
      </form>

      <form method="POST" action="actions.php" onsubmit="return confirm('تأكيد تصفير الأجهزة؟');" style="display:inline">
        <input type="hidden" name="action" value="reset_devices">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <button class="small" style="background:#0dcaf0">تصفير أجهزة</button>
      </form>

      <form method="POST" action="actions.php" onsubmit="return confirm('تأكيد حذف المفتاح وكل أجهزته؟');" style="display:inline">
        <input type="hidden" name="action" value="delete_license">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <button class="small" style="background:#dc3545;color:#fff">حذف</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div id="noRows" class="nothing">لا توجد نتائج مطابقة.</div>

<!-- مودال التعديل (يتضمن حقل HWID اختياري) -->
<div id="editModal" class="modal">
  <div class="box">
    <h3>تعديل مفتاح</h3>
    <form method="POST" action="actions.php">
      <input type="hidden" name="action" value="update_license">
      <input type="hidden" name="id" id="e_id">
      <div class="row">
        <div>
          <label>المنتج</label>
          <select name="product_id" id="e_product">
            <option value="">— بدون تغيير —</option>
            <?php foreach($products as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>الزبون</label>
          <select name="customer_id" id="e_customer">
            <option value="">— بدون تغيير —</option>
            <?php foreach($customers as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Days</label>
          <input type="number" name="days" id="e_days" placeholder="— بدون تغيير —">
        </div>
        <div>
          <label>Max Devices</label>
          <input type="number" name="max_devices" id="e_max" placeholder="— بدون تغيير —">
        </div>
      </div>

      <label>الحالة</label>
      <select name="status" id="e_status">
        <option value="">— بدون تغيير —</option>
        <option value="active">active</option>
        <option value="revoked">revoked</option>
      </select>

      <label>HWID (UUID) — اختياري للاختبار اليدوي</label>
      <input type="text" name="test_hwid" id="e_hwid" placeholder="550e8400-e29b-41d4-a716-446655440000">

      <div style="margin-top:10px">
        <button type="submit" style="background:#0d6efd;color:#fff;padding:6px 10px;border:none;border-radius:4px">حفظ</button>
        <button type="button" onclick="closeEdit()" style="padding:6px 10px;border:1px solid #333;border-radius:4px;background:#fff">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<script>
// فلترة فورية بدون إنتر
const q = document.getElementById('q');
const tbl = document.getElementById('tbl').getElementsByTagName('tbody')[0];
const noRows = document.getElementById('noRows');

function filterRows() {
  const term = q.value.trim().toLowerCase();
  let shown = 0;
  for (const tr of tbl.rows) {
    const hay = tr.getAttribute('data-search') || '';
    const match = term === '' || hay.indexOf(term) !== -1;
    tr.style.display = match ? '' : 'none';
    if (match) shown++;
  }
  noRows.style.display = shown ? 'none' : 'block';
}
q.addEventListener('input', filterRows);
document.addEventListener('DOMContentLoaded', filterRows);

// مودال التعديل
function openEdit(id,product_id,customer_id,days,max_devices,status){
  document.getElementById('e_id').value = id;
  document.getElementById('e_product').value  = product_id || '';
  document.getElementById('e_customer').value = customer_id || '';
  document.getElementById('e_days').value     = days || '';
  document.getElementById('e_max').value      = max_devices || '';
  document.getElementById('e_status').value   = status || '';
  document.getElementById('e_hwid').value     = '';
  document.getElementById('editModal').style.display='block';
}
function closeEdit(){ document.getElementById('editModal').style.display='none'; }
window.addEventListener('keydown', e=>{ if(e.key==='Escape') closeEdit(); });
</script>
</body>
</html>
