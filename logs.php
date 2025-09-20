<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

$filter    = $_GET['filter'] ?? '';
$licenseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sqlBase = "
  SELECT lg.id, lg.license_id, lg.type, lg.message, lg.created_at,
         lk.`key` AS license_key,
         p.name AS product_name,
         c.name AS customer_name
    FROM logs lg
    LEFT JOIN license_keys lk ON lk.id = lg.license_id
    LEFT JOIN products p ON p.id = lk.product_id
    LEFT JOIN customers c ON c.id = lk.customer_id
";
$where = []; $params = [];
if ($licenseId > 0) { $where[] = "lg.license_id = ?"; $params[] = $licenseId; }
if ($filter) {
  if ($filter==='created') $where[] = "lg.message LIKE 'License created%'";
  if ($filter==='updated') $where[] = "lg.message LIKE 'License updated%'";
  if ($filter==='deleted') $where[] = "lg.message LIKE 'License deleted%'";
  if ($filter==='device')  $where[] = "lg.message LIKE 'Device%'";
}
$sql = $sqlBase.($where ? " WHERE ".implode(" AND ", $where) : "")." ORDER BY lg.created_at DESC, lg.id DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$logs = $stmt->fetchAll();

function extract_field_from_msg($msg, $field){
  $re='/\b'.preg_quote($field,'/').'\s*=\s*([^|]+)(?:\||$)/i';
  if(preg_match($re,(string)$msg,$m)) return trim($m[1]);
  return null;
}
function event_label($msg){
  $m=(string)$msg;
  if(stripos($m,'License created')===0) return 'تم الإنشاء';
  if(stripos($m,'License updated')===0) return 'تم التعديل';
  if(stripos($m,'License deleted')===0) return 'تم الحذف';
  if(stripos($m,'Device')===0)          return 'عملية جهاز';
  return 'عملية';
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>كل السجلات</title>
<style>
body{font-family:Arial, sans-serif;background:#f7f7f7;padding:20px;}
.topnav a{display:inline-block;margin:0 6px 10px 0;padding:6px 10px;background:#6c757d;color:#fff;text-decoration:none;border-radius:4px;}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}
.toolbar .btn{background:#0d6efd;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none}
.filter-form select{padding:6px}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
th{background:#333;color:#fff}
code{background:#f1f1f1;padding:2px 4px;border-radius:3px}
td.msg{text-align:left;direction:ltr}
</style>
</head>
<body>
<div class="topnav">
  <a href="dashboard.php">⬅ رجوع للوحة المفاتيح</a>
  <a href="logs.php">📜 كل السجلات</a>
</div>

<div class="toolbar">
  <form class="filter-form" method="get" action="logs.php">
    <label>فلترة:</label>
    <select name="filter" onchange="this.form.submit()">
      <option value="">عرض الكل</option>
      <option value="created" <?= $filter==='created'?'selected':'' ?>>تم الإنشاء</option>
      <option value="updated" <?= $filter==='updated'?'selected':'' ?>>تم التعديل</option>
      <option value="deleted" <?= $filter==='deleted'?'selected':'' ?>>تم الحذف</option>
      <option value="device"  <?= $filter==='device'?'selected':''  ?>>عمليات الأجهزة</option>
    </select>
    <?php if ($licenseId>0): ?><input type="hidden" name="id" value="<?= (int)$licenseId ?>"><?php endif; ?>
  </form>

  <a class="btn" href="export_logs.php<?= $filter?('?filter='.$filter):'' ?><?= $licenseId?($filter?'&':'?').'id='.$licenseId:'' ?>">⬇ تصدير CSV</a>
</div>

<table>
<tr>
  <th>ID</th>
  <th>الحدث</th>
  <th>Key</th>
  <th>المنتج</th>
  <th>الزبون</th>
  <th>Type</th>
  <th>Message</th>
  <th>Created At</th>
</tr>
<?php foreach($logs as $row): 
  $fullKey  = $row['license_key']   ?? extract_field_from_msg($row['message']??'','key')      ?? '-';
  $prodName = $row['product_name']  ?? extract_field_from_msg($row['message']??'','product')  ?? '-';
  $custName = $row['customer_name'] ?? extract_field_from_msg($row['message']??'','customer') ?? '-';
  $rawMsg   = (string)($row['message'] ?? '');
  $msgClean = preg_split('/\s*\|\s*/',$rawMsg,2)[0];
  $evt      = event_label($rawMsg);
?>
<tr>
  <td><?= (int)$row['id'] ?></td>
  <td><?= htmlspecialchars($evt) ?></td>
  <td><?= $fullKey!=='-'?'<code>'.htmlspecialchars($fullKey).'</code>':'-' ?></td>
  <td><?= htmlspecialchars($prodName) ?></td>
  <td><?= htmlspecialchars($custName) ?></td>
  <td><?= htmlspecialchars((string)($row['type']??'')) ?></td>
  <td class="msg"><?= htmlspecialchars($msgClean) ?></td>
  <td><?= htmlspecialchars((string)($row['created_at']??'')) ?></td>
</tr>
<?php endforeach; if(!$logs): ?>
<tr><td colspan="8">لا توجد سجلات لعرضها.</td></tr>
<?php endif; ?>
</table>
</body>
</html>
