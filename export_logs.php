<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="logs_export.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

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
$where = [];
$params = [];

if ($licenseId > 0) { $where[] = "lg.license_id = ?"; $params[] = $licenseId; }
if ($filter) {
    if ($filter === 'created')  $where[] = "lg.message LIKE 'License created%'";
    if ($filter === 'updated')  $where[] = "lg.message LIKE 'License updated%'";
    if ($filter === 'deleted')  $where[] = "lg.message LIKE 'License deleted%'";
    if ($filter === 'device')   $where[] = "lg.message LIKE 'Device%'";
}
$sql = $sqlBase . ($where ? " WHERE ".implode(" AND ", $where) : "") . " ORDER BY lg.created_at DESC, lg.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function extract_field_from_msg($msg, $field) {
  $re = '/\b'.preg_quote($field,'/').'\s*=\s*([^|]+)(?:\||$)/i';
  if (preg_match($re, (string)$msg, $m)) return trim($m[1]);
  return null;
}
function event_label($msg) {
  $m = (string)$msg;
  if (stripos($m, 'License created') === 0)  return 'تم الإنشاء';
  if (stripos($m, 'License updated') === 0)  return 'تم التعديل';
  if (stripos($m, 'License deleted') === 0)  return 'تم الحذف';
  if (stripos($m, 'Device') === 0)           return 'عملية جهاز';
  return 'عملية';
}
$fp = fopen('php://output', 'w');

// header
fputcsv($fp, ['ID','الحدث','Key','المنتج','الزبون','Type','Message','Created At']);

foreach ($rows as $r) {
  $fullKey  = $r['license_key'] ?? extract_field_from_msg($r['message'] ?? '', 'key') ?? '-';
  $prodName = $r['product_name'] ?? extract_field_from_msg($r['message'] ?? '', 'product') ?? '-';
  $cusName  = $r['customer_name'] ?? extract_field_from_msg($r['message'] ?? '', 'customer') ?? '-';
  $rawMsg   = (string)($r['message'] ?? '');
  $msgClean = preg_split('/\s*\|\s*/', $rawMsg, 2)[0];
  $evt      = event_label($rawMsg);

  fputcsv($fp, [
    (int)$r['id'],
    $evt,
    $fullKey,
    $prodName,
    $cusName,
    (string)($r['type'] ?? ''),
    $msgClean,
    (string)($r['created_at'] ?? '')
  ]);
}
fclose($fp);
exit;
